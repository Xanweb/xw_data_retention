<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Scheduler\Scheduler;
use Concrete\Core\Command\Task\Input\Input;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Entity\Command\ScheduledTask;
use Concrete\Core\Package\PackageService;
use Doctrine\ORM\EntityManagerInterface;

final class Policy
{
    public const TASK_HANDLE = 'xw_delete_old_page_versions';
    public const MIN_KEEP = 10;
    public const MAX_KEEP = 1000;
    public const MONTHS = [0, 1, 2, 3, 6, 12, 24, 36];

    private function config()
    {
        return app(PackageService::class)->getByHandle('xw_data_retention')->getController()->getConfig();
    }

    public function get(): array
    {
        $config = $this->config();
        $keep = (int) $config->get('page_versions.keep', self::MIN_KEEP);
        $months = (int) $config->get('page_versions.months', 0);
        return [
            'enabled' => (bool) $config->get('page_versions.enabled', false),
            'keep' => max(self::MIN_KEEP, min(self::MAX_KEEP, $keep)),
            'months' => in_array($months, self::MONTHS, true) ? $months : 0,
            'include_stacks' => (bool) $config->get('page_versions.include_stacks', false),
        ];
    }

    public function validate(array $input): array
    {
        foreach (['enabled', 'include_stacks'] as $key) {
            if (!isset($input[$key]) || !is_scalar($input[$key]) || !in_array((string) $input[$key], ['0', '1'], true)) {
                throw new \InvalidArgumentException(t('Invalid page version retention setting.'));
            }
        }
        $keep = $input['keep'] ?? null;
        if (!is_scalar($keep) || !preg_match('/^[1-9][0-9]*$/D', (string) $keep)
            || filter_var($keep, FILTER_VALIDATE_INT) === false || (int) $keep < self::MIN_KEEP || (int) $keep > self::MAX_KEEP) {
            throw new \InvalidArgumentException(t('Keep between %d and %d page versions.', self::MIN_KEEP, self::MAX_KEEP));
        }
        $months = $input['months'] ?? null;
        if (!is_scalar($months) || !in_array((string) $months, array_map('strval', self::MONTHS), true)) {
            throw new \InvalidArgumentException(t('Invalid page version age limit.'));
        }
        if ((string) $input['enabled'] === '1' && ($input['confirm_activation'] ?? null) !== '1') {
            throw new \InvalidArgumentException(t('Please explicitly confirm automatic deletion.'));
        }
        return ['enabled' => (bool) (int) $input['enabled'], 'keep' => (int) $keep, 'months' => (int) $months, 'include_stacks' => (bool) (int) $input['include_stacks']];
    }

    public function save(array $input): void
    {
        $settings = $this->validate($input);
        foreach ($settings as $key => $value) {
            $this->config()->save('page_versions.' . $key, $value);
        }
        $this->syncSchedule();
    }

    public function syncSchedule(): void
    {
        $task = app(TaskService::class)->getByHandle(self::TASK_HANDLE);
        $em = app(EntityManagerInterface::class);
        $scheduled = $em->getRepository(ScheduledTask::class)->findBy(['task' => $task]);
        if ($this->get()['enabled']) {
            if (!$scheduled) { app(Scheduler::class)->createScheduledTask($task, new Input(), '40 3 * * *'); }
        } else {
            foreach ($scheduled as $item) { $em->remove($item); }
            if ($scheduled) { $em->flush(); }
        }
    }

    public function coreTaskScheduled(): bool
    {
        $task = app(TaskService::class)->getByHandle('remove_old_page_versions');
        return $task && (bool) app(EntityManagerInterface::class)->getRepository(ScheduledTask::class)->findOneBy(['task' => $task]);
    }
}
