<?php

namespace Xw\DataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Scheduler\Scheduler;
use Concrete\Core\Command\Task\Input\Input;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\Application\Application;
use Concrete\Core\Package\PackageService;
use Doctrine\ORM\EntityManagerInterface;

final class Policy
{
    public const TASK_HANDLE = 'xw_delete_expired_form_data';
    public const SOURCES = ['express', 'legacy', 'logs'];
    public const MONTHS = [0, 1, 2, 3, 6, 12, 24, 36];

    /** @var Application */
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public static function labels(): array
    {
        return [
            'express' => t('Express Form submissions'),
            'legacy' => t('Legacy Form block submissions'),
            'logs' => t('Concrete CMS database logs'),
        ];
    }

    private function config()
    {
        $package = $this->app->make(PackageService::class)->getByHandle('xw_data_retention');
        if (!$package) {
            throw new \RuntimeException('The data retention package is not installed.');
        }
        return $package->getController()->getConfig();
    }

    public function get(): array
    {
        $config = $this->config();
        $result = [];
        foreach (self::SOURCES as $source) {
            $value = (int) $config->get('retention.' . $source . '_months');
            $result[$source] = in_array($value, self::MONTHS, true) ? $value : 0;
        }
        $result['delete_express_uploads'] = (bool) $config->get('retention.delete_express_uploads', false);
        $result['express_upload_folder_id'] = max(0, (int) $config->get('retention.express_upload_folder_id', 0));
        return $result;
    }

    /** Initialize only the new options; existing retention periods are untouched. */
    public function initializeUploads(): void
    {
        $config = $this->config();
        foreach (['delete_express_uploads' => false, 'express_upload_folder_id' => 0] as $key => $default) {
            if ($config->get('retention.' . $key) === null) {
                $config->save('retention.' . $key, $default);
            }
        }
    }

    public function save(array $values): array
    {
        $validated = [];
        $labels = self::labels();
        foreach (self::SOURCES as $source) {
            $raw = $values[$source] ?? null;
            if (!is_scalar($raw) || !preg_match('/^(0|1|2|3|6|12|24|36)$/D', (string) $raw)) {
                throw new \InvalidArgumentException(t('Invalid retention period for %s.', $labels[$source]));
            }
            $validated[$source] = (int) $raw;
        }
        $enabled = $values['delete_express_uploads'] ?? '0';
        $folder = $values['express_upload_folder_id'] ?? '0';
        if (!is_scalar($enabled) || !in_array((string) $enabled, ['0', '1'], true)) {
            throw new \InvalidArgumentException(t('Invalid uploaded file deletion setting.'));
        }
        if (!is_scalar($folder) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $folder)
            || filter_var($folder, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException(t('Invalid upload folder.'));
        }
        $enabled = (bool) (int) $enabled;
        $folder = (int) $folder;
        if ($enabled && $validated['express'] === 0) {
            throw new \InvalidArgumentException(t('Choose an Express retention period before enabling uploaded file deletion.'));
        }
        if (($enabled || $folder > 0) && !app(Uploads::class)->isAllowedFolder($folder)) {
            throw new \InvalidArgumentException(t('Choose an existing File Manager folder other than the root folder.'));
        }
        if ($enabled && ($values['confirm_uploads'] ?? null) !== '1') {
            throw new \InvalidArgumentException(t('Please explicitly confirm permanent deletion of uploaded files.'));
        }
        $config = $this->config();
        foreach ($validated as $source => $months) {
            $config->save('retention.' . $source . '_months', $months);
        }
        $config->save('retention.delete_express_uploads', $enabled);
        $config->save('retention.express_upload_folder_id', $folder);
        $validated['delete_express_uploads'] = $enabled;
        $validated['express_upload_folder_id'] = $folder;
        $this->syncSchedule($validated);
        return $validated;
    }

    public function syncSchedule(?array $values = null): void
    {
        $values = $values ?? $this->get();
        $task = $this->app->make(TaskService::class)->getByHandle(self::TASK_HANDLE);
        if (!$task) {
            throw new \RuntimeException('The retention task is missing.');
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $scheduled = $em->getRepository(ScheduledTask::class)->findBy(['task' => $task]);
        if (max(array_map(static function ($source) use ($values) { return (int) $values[$source]; }, self::SOURCES)) > 0) {
            if (!$scheduled) {
                $this->app->make(Scheduler::class)->createScheduledTask($task, new Input(), '10 3 * * *');
            }
        } else {
            foreach ($scheduled as $item) {
                $em->remove($item);
            }
            if ($scheduled) {
                $em->flush();
            }
        }
    }

    public function cutoff(int $months): \DateTimeImmutable
    {
        if (!in_array($months, self::MONTHS, true) || $months === 0) {
            throw new \InvalidArgumentException('A positive configured retention period is required.');
        }
        // Clamp the day to the target month; PHP's bare "-1 month" can roll 31 March into March.
        $now = new \DateTimeImmutable('now');
        $targetMonth = $now->modify('first day of this month')->modify('-' . $months . ' months');
        $day = min((int) $now->format('j'), (int) $targetMonth->format('t'));
        return $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('n'), $day)
            ->setTime((int) $now->format('G'), (int) $now->format('i'), (int) $now->format('s'));
    }
}
