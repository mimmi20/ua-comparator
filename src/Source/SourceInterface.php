<?php
namespace UaComparator\Source;

use Monolog\Logger;

/**
 * Class DirectorySource
 *
 * @package UaComparator\Source
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
interface SourceInterface
{
    /**
     * @param \Monolog\Logger $logger
     *
     * @return \Generator
     * @throws \BrowscapPHP\Helper\Exception
     */
    public function getUserAgents(Logger $logger);
}
