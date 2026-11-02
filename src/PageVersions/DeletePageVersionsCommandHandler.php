<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;

final class DeletePageVersionsCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;
    public function __invoke(DeletePageVersionsCommand $command): void
    {
        $count = app(Cleanup::class)->page($command->pageID);
        app(RunStore::class)->add($command->runID, $count);
        if ($this->output) {
            $this->output->write(t('Page %d: deleted %d old versions.', $command->pageID, $count['versions']));
        }
    }
}
