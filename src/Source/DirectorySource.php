<?php

namespace UaComparator\Source;

use Monolog\Logger;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
class DirectorySource implements SourceInterface
{
    /**
     * @var string
     */
    private $dir = null;

    /**
     * @param string $dir
     */
    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @param \Monolog\Logger $logger
     * @param int             $limit
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, $limit = 0)
    {
        $allLines = [];
        $files    = scandir($this->dir, SCANDIR_SORT_ASCENDING);

        foreach ($files as $filename) {
            $file = new \SplFileInfo($this->dir . DIRECTORY_SEPARATOR . $filename);

            if (!$file->isFile()) {
                continue;
            }

            $lines = file($file->getPathname());

            if (empty($lines)) {
                $logger->info('Skipping empty file "' . $file->getPathname() . '"');
                continue;
            }

            foreach ($lines as $line) {
                if (isset($allLines[$line])) {
                    continue;
                }

                $allLines[$line] = 1;

                yield $line;

                if ($limit && count($allLines) >= $limit) {
                    return;
                }
            }
        }
    }
}
