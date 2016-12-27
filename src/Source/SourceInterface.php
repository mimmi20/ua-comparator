<?php

namespace UaComparator\Source;

use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
interface SourceInterface
{
    /**
     * @param \Monolog\Logger                                   $logger
     * @param int                                               $limit
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, $limit, OutputInterface $output);
}
