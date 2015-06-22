<?php
namespace UaComparator\Source;

use BrowscapPHP\Helper\IniLoader;
use Monolog\Logger;

/**
 * Class DirectorySource
 *
 * @package UaComparator\Source
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
class PdoSource implements SourceInterface
{
    /**
     * @var \PDO
     */
    private $pdo = null;

    /**
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param \Monolog\Logger $logger
     *
     * @return \Generator
     * @throws \BrowscapPHP\Helper\Exception
     */
    public function getUserAgents(Logger $logger)
    {
        //
    }
}
