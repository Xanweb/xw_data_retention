<?php

namespace Xw\DataRetention;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;

class DeleteExpiredCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    public function __invoke(DeleteExpiredCommand $command): void
    {
        $count = app(Cleanup::class)->run();
        if ($this->output) {
            $this->output->write(t('Data retention: deleted %d Express entries, %d legacy form submissions, and %d database log entries.', $count['express'], $count['legacy'], $count['logs']));
        }
    }
}
