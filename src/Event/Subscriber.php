<?php

namespace Xw\DataRetention\Event;

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Command\Process\Event\ProcessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Xw\DataRetention\PageVersions\RunStore;

final class Subscriber implements EventSubscriberInterface
{
    /**
     * @var RunStore
     */
    private $runStore;

    public function __construct(RunStore $runStore)
    {
        $this->runStore = $runStore;
    }

    public static function getSubscribedEvents(): array
    {
        return ['on_close_process' => 'onCloseProcess'];
    }

    public function onCloseProcess(ProcessEvent $event): void
    {
        $this->runStore->completed($event);
    }
}
