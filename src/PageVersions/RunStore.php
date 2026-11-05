<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Process\Event\ProcessEvent;
use Concrete\Core\Command\Process\Logger\LoggerFactoryInterface;

/** Atomic counters shared by the batch's potentially concurrent workers. */
final class RunStore
{
    private function database() { return app('database')->connection(); }

    public function create(): string
    {
        $id = bin2hex(random_bytes(16));
        $this->database()->insert('XwDataRetentionPageVersionRuns', [
            'runID' => $id, 'versionsDeleted' => 0, 'pagesProcessed' => 0,
            'createdAt' => date('Y-m-d H:i:s'), 'completed' => 0,
        ]);
        return $id;
    }

    public function attach(string $runID, string $processID): void
    {
        $this->database()->update('XwDataRetentionPageVersionRuns', ['processID' => $processID], ['runID' => $runID]);
    }

    public function add(string $runID, array $count): void
    {
        $this->database()->executeStatement('UPDATE XwDataRetentionPageVersionRuns
            SET versionsDeleted = versionsDeleted + ?, pagesProcessed = pagesProcessed + ? WHERE runID = ?', [$count['versions'], $count['pages'], $runID]);
    }

    public function totals(string $runID): array
    {
        return $this->database()->fetchAssociative('SELECT * FROM XwDataRetentionPageVersionRuns WHERE runID = ?', [$runID]) ?: [];
    }

    public function completed(ProcessEvent $event): void
    {
        $process = $event->getProcess();
        if (!$process instanceof \Concrete\Core\Entity\Command\TaskProcess
            || $process->getTask()->getHandle() !== Policy::TASK_HANDLE) { return; }
        $row = $this->database()->fetchAssociative('SELECT * FROM XwDataRetentionPageVersionRuns WHERE processID = ?', [$process->getID()]);
        if (!$row) { return; }
        $logger = app(LoggerFactoryInterface::class)->createFromProcess($process);
        if ($logger) {
            $logger->write(t('Page version retention: deleted %d versions and processed %d pages.', (int) $row['versionsDeleted'], (int) $row['pagesProcessed']));
        }
        $this->database()->update('XwDataRetentionPageVersionRuns', ['completed' => 1], ['runID' => $row['runID']]);
        $this->database()->executeStatement('DELETE FROM XwDataRetentionPageVersionRuns WHERE completed = 1 AND createdAt < ?', [date('Y-m-d H:i:s', strtotime('-30 days'))]);
    }
}
