<?php

namespace UaComparator\Source;

use Monolog\Logger;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
interface SourceInterface
{
    /**
     * @param \Monolog\Logger $logger
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger);
}
