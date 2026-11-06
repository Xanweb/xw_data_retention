<?php
namespace Xw\DataRetention\PageVersions;

defined('C5_EXECUTE') or die('Access Denied.');

final class DeletePageVersionsCommand extends \Concrete\Core\Foundation\Command\Command
{
    public $pageID;
    public $runID;
    /** @var int[] Versions handled by this job; empty means every eligible version of the page (pre-chunking messages). */
    public $versionIDs;
    /** @var bool Only the first chunk of a page adds to the processed-pages counter. */
    public $countPage;

    public function __construct(int $pageID, string $runID, array $versionIDs = [], bool $countPage = true)
    {
        $this->pageID = $pageID;
        $this->runID = $runID;
        $this->versionIDs = array_map('intval', $versionIDs);
        $this->countPage = $countPage;
    }
}
