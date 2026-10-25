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
        return $result;
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
        $config = $this->config();
        foreach ($validated as $source => $months) {
            $config->save('retention.' . $source . '_months', $months);
        }
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
        if (max($values) > 0) {
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
