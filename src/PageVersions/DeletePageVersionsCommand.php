<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

final class DeletePageVersionsCommand extends \Concrete\Core\Foundation\Command\Command
{
    public $pageID;
    public $runID;
    public function __construct(int $pageID, string $runID) { $this->pageID = $pageID; $this->runID = $runID; }
}
