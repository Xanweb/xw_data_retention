<?php

namespace Xw\DataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Entity\Express\Entry;
use Concrete\Core\Entity\File\File;
use Concrete\Core\Entity\Statistics\UsageTracker\FileUsageRecord;
use Concrete\Core\File\Filesystem;
use Concrete\Core\Logging\LoggerFactory;
use Doctrine\ORM\EntityManagerInterface;

/** Restricts file deletion to the explicitly authorized, direct parent folder. */
final class Uploads
{
    private function database()
    {
        return app('database')->connection();
    }

    public function isAllowedFolder(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $filesystem = app(Filesystem::class);
        $folder = $filesystem->getFolder($id);
        $root = $filesystem->getRootFolder();
        // Check the database as well: Core may still cache a deleted tree node.
        return $folder && $root
            && (int) $folder->getTreeID() === (int) $root->getTreeID()
            && (int) $this->database()->fetchOne('SELECT treeNodeParentID FROM TreeNodes WHERE treeNodeID = ?', [$id]) > 0;
    }

    public function collect(Entry $entry): array
    {
        $ids = [];
        foreach ($entry->getAttributes() as $attribute) {
            if ($attribute->getAttributeTypeObject()->getAttributeTypeHandle() === 'image_file') {
                $value = $attribute->getValueObject();
                if ($value && $value->getFileID()) {
                    $ids[(int) $value->getFileID()] = true;
                }
            }
        }
        return array_keys($ids);
    }

    public function warning(string $message, array $context = []): void
    {
        try {
            app(LoggerFactory::class)->createLogger('xw_data_retention')->warning($message, $context);
        } catch (\Throwable $loggingError) {
            error_log('xw_data_retention: ' . $message);
        }
    }

    /** Called only after the owning expired entry has been deleted. */
    public function delete(array $ids, int $folderID): int
    {
        $count = 0;
        $db = $this->database();
        $em = app(EntityManagerInterface::class);
        foreach (array_unique($ids) as $id) {
            $file = null;
            $level = $db->getTransactionNestingLevel();
            try {
                if (!$this->isAllowedFolder($folderID)
                    || (int) $db->fetchOne('SELECT folderTreeNodeID FROM Files WHERE fID = ?', [$id]) !== $folderID) {
                    continue;
                }
                // All Express entities count, including those outside Form block retention.
                if ($db->fetchOne('SELECT 1 FROM atFile f
                    INNER JOIN ExpressEntityEntryAttributeValues a ON a.avID = f.avID
                    INNER JOIN ExpressEntityEntries e ON e.exEntryID = a.exEntryID
                    WHERE f.fID = ? LIMIT 1', [$id])) {
                    continue;
                }
                if ($em->getRepository(FileUsageRecord::class)->findOneBy(['file_id' => $id])) {
                    continue;
                }
                // Keep Core file entities managed: other loaded attribute values may reference them.
                $file = $em->find(File::class, $id);
                if ($file) {
                    // Core removes every version, original and thumbnail, and fires its events.
                    if ($file->delete()) {
                        ++$count;
                    } else {
                        while ($db->getTransactionNestingLevel() > $level) {
                            $db->rollBack();
                        }
                        $this->warning(t('Uploaded file %d was not deleted because a Core event prevented deletion.', $id));
                    }
                }
            } catch (\Throwable $error) {
                while ($db->getTransactionNestingLevel() > $level) {
                    $db->rollBack();
                }
                $this->warning(t('Could not delete uploaded file %d: %s', $id, $error->getMessage()), ['file_id' => $id]);
            } finally {
                // Core returns early on an event veto without closing its transaction.
                while ($db->getTransactionNestingLevel() > $level) {
                    $db->rollBack();
                }
            }
        }
        return $count;
    }

    /** Predict distinct files after the currently expired, scoped entries are removed. */
    public function preview(array $settings, array $entityIDs, \DateTimeImmutable $cutoff): array
    {
        $result = ['uploaded_files' => 0, 'unlinked_files' => 0, 'upload_folder_missing' => false];
        $folderID = (int) ($settings['express_upload_folder_id'] ?? 0);
        if ($folderID === 0) {
            return $result;
        }
        if (!$this->isAllowedFolder($folderID)) {
            $result['upload_folder_missing'] = true;
            return $result;
        }
        $db = $this->database();
        $result['unlinked_files'] = (int) $db->fetchOne('SELECT COUNT(*) FROM Files f
            WHERE f.folderTreeNodeID = ? AND NOT EXISTS (
                SELECT 1 FROM atFile af
                INNER JOIN ExpressEntityEntryAttributeValues a ON a.avID = af.avID
                INNER JOIN ExpressEntityEntries e ON e.exEntryID = a.exEntryID
                WHERE af.fID = f.fID
            )', [$folderID]);
        if (empty($settings['delete_express_uploads']) || !$entityIDs || $settings['express'] === 0) {
            return $result;
        }
        $in = implode(',', array_fill(0, count($entityIDs), '?'));
        $date = $cutoff->format('Y-m-d H:i:s');
        $result['uploaded_files'] = (int) $db->fetchOne('SELECT COUNT(DISTINCT f.fID) FROM Files f
            INNER JOIN atFile af ON af.fID = f.fID
            INNER JOIN ExpressEntityEntryAttributeValues a ON a.avID = af.avID
            INNER JOIN AttributeKeys ak ON ak.akID = a.akID
            INNER JOIN AttributeTypes at ON at.atID = ak.atID
            INNER JOIN ExpressEntityEntries e ON e.exEntryID = a.exEntryID
            WHERE f.folderTreeNodeID = ? AND at.atHandle = ?
                AND e.exEntryEntityID IN (' . $in . ') AND e.exEntryDateCreated < ?
                AND NOT EXISTS (
                    SELECT 1 FROM atFile otherFile
                    INNER JOIN ExpressEntityEntryAttributeValues otherValue ON otherValue.avID = otherFile.avID
                    INNER JOIN ExpressEntityEntries otherEntry ON otherEntry.exEntryID = otherValue.exEntryID
                    WHERE otherFile.fID = f.fID AND NOT (
                        otherEntry.exEntryEntityID IN (' . $in . ') AND otherEntry.exEntryDateCreated < ?
                    )
                )
                AND NOT EXISTS (SELECT 1 FROM FileUsageRecord usageRecord WHERE usageRecord.file_id = f.fID)',
            array_merge([$folderID, 'image_file'], $entityIDs, [$date], $entityIDs, [$date]));
        return $result;
    }
}
