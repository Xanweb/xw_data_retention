<?php

namespace Xw\DataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Entity\Express\Entry;
use Concrete\Core\Entity\Express\Form;
use Concrete\Core\Express\Entry\Manager as EntryManager;
use Doctrine\ORM\EntityManagerInterface;

/** Deletes stored form submissions and, when selected, CMS database logs. */
final class Cleanup
{
    private const FORM_BATCH_SIZE = 200;
    private const LOG_BATCH_SIZE = 5000;
    private const CLI_TIME_LIMIT_SECONDS = 300;

    private function database()
    {
        return app('database')->connection();
    }

    private function expressEntityIDs(): array
    {
        $db = $this->database();
        $em = app(EntityManagerInterface::class);
        $ids = [];
        foreach ($db->fetchAllAssociative('SELECT DISTINCT exFormID FROM btExpressForm WHERE exFormID IS NOT NULL') as $row) {
            $form = $em->find(Form::class, $row['exFormID']);
            if ($form && $form->getEntity()) {
                $ids[(string) $form->getEntity()->getID()] = true;
            }
        }
        return array_keys($ids);
    }

    private function expressWhere(array $ids): array
    {
        $ids = array_values($ids);
        if (!$ids) {
            return ['1 = 0', []];
        }
        return ['exEntryEntityID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }

    private function formDeadline(): float
    {
        $maxExecutionTime = (int) ini_get('max_execution_time');
        $seconds = $maxExecutionTime > 0 ? max(1, (int) floor($maxExecutionTime / 2)) : self::CLI_TIME_LIMIT_SECONDS;
        return microtime(true) + $seconds;
    }

    public function preview(?array $settings = null): array
    {
        $policy = app(Policy::class);
        $settings = $settings ?? $policy->get();
        $db = $this->database();
        $result = ['express' => 0, 'legacy' => 0, 'logs' => 0];
        if ($settings['express'] > 0) {
            [$where, $params] = $this->expressWhere($this->expressEntityIDs());
            $params[] = $policy->cutoff($settings['express'])->format('Y-m-d H:i:s');
            $result['express'] = (int) $db->fetchOne('SELECT COUNT(*) FROM ExpressEntityEntries WHERE ' . $where . ' AND exEntryDateCreated < ?', $params);
        }
        if ($settings['legacy'] > 0) {
            $result['legacy'] = (int) $db->fetchOne(
                'SELECT COUNT(*) FROM btFormAnswerSet WHERE created < ?',
                [$policy->cutoff($settings['legacy'])->format('Y-m-d H:i:s')]
            );
        }
        if ($settings['logs'] > 0) {
            $result['logs'] = (int) $db->fetchOne(
                'SELECT COUNT(*) FROM Logs WHERE time < ?',
                [$policy->cutoff($settings['logs'])->getTimestamp()]
            );
        }
        return $result;
    }

    public function run(): array
    {
        $policy = app(Policy::class);
        $settings = $policy->get();
        $count = ['express' => 0, 'legacy' => 0, 'logs' => 0];
        $deadline = $this->formDeadline();

        if ($settings['express'] > 0) {
            $db = $this->database();
            $em = app(EntityManagerInterface::class);
            $manager = app(EntryManager::class);
            $ids = $this->expressEntityIDs();
            [$where, $params] = $this->expressWhere($ids);
            $cutoff = $policy->cutoff($settings['express']);
            $params[] = $cutoff->format('Y-m-d H:i:s');
            while (microtime(true) < $deadline) {
                $rows = $db->fetchAllAssociative('SELECT exEntryID FROM ExpressEntityEntries WHERE ' . $where . ' AND exEntryDateCreated < ? ORDER BY exEntryDateCreated, exEntryID LIMIT ' . self::FORM_BATCH_SIZE, $params);
                if (!$rows) {
                    break;
                }
                $deleted = 0;
                foreach ($rows as $row) {
                    if (microtime(true) >= $deadline) {
                        break;
                    }
                    $entry = $em->find(Entry::class, (int) $row['exEntryID']);
                    if ($entry && in_array((string) $entry->getEntity()->getID(), $ids, true) && $entry->getDateCreated() < $cutoff) {
                        $manager->deleteEntry($entry);
                        // Leave the task runner's managed objects attached.
                        if ($em->contains($entry)) {
                            $em->detach($entry);
                        }
                        ++$count['express'];
                        ++$deleted;
                    }
                }
                if ($deleted === 0) {
                    break;
                }
            }
        }

        if ($settings['legacy'] > 0) {
            $db = $this->database();
            $cutoff = $policy->cutoff($settings['legacy'])->format('Y-m-d H:i:s');
            while (microtime(true) < $deadline) {
                $rows = $db->fetchAllAssociative('SELECT asID FROM btFormAnswerSet WHERE created < ? ORDER BY created, asID LIMIT ' . self::FORM_BATCH_SIZE, [$cutoff]);
                if (!$rows) {
                    break;
                }
                $deleted = 0;
                foreach ($rows as $row) {
                    if (microtime(true) >= $deadline) {
                        break;
                    }
                    $id = (int) $row['asID'];
                    $db->beginTransaction();
                    try {
                        // Match the deletion order used by Core's legacy form report.
                        $db->delete('btFormAnswers', ['asID' => $id]);
                        $removed = $db->delete('btFormAnswerSet', ['asID' => $id]);
                        $db->commit();
                        $count['legacy'] += $removed;
                        $deleted += $removed;
                    } catch (\Throwable $error) {
                        $db->rollBack();
                        throw $error;
                    }
                }
                if ($deleted === 0) {
                    break;
                }
            }
        }

        if ($settings['logs'] > 0) {
            $db = $this->database();
            $cutoff = $policy->cutoff($settings['logs'])->getTimestamp();
            do {
                $deleted = $db->executeStatement('DELETE FROM Logs WHERE time < ? LIMIT ' . self::LOG_BATCH_SIZE, [$cutoff]);
                $count['logs'] += $deleted;
            } while ($deleted > 0);
        }

        return $count;
    }
}
