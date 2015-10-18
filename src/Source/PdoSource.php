<?php
namespace UaComparator\Source;

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
     * @param integer         $limit
     *
     * @return \Generator
     * @throws \BrowscapPHP\Helper\Exception
     */
    public function getUserAgents(Logger $logger, $limit = 0)
    {
        $sql = 'SELECT DISTINCT SQL_BIG_RESULT HIGH_PRIORITY `agent` FROM `agents` ORDER BY `count` DESC, `idAgents` DESC';

        if ($limit) {
            $sql .= ' LIMIT ' . $this->pdo->quote($limit, \PDO::PARAM_INT);
        }

        $driverOptions = array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY);

        /** @var \PDOStatement $stmt */
        $stmt = $this->pdo->prepare($sql, $driverOptions);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            yield trim($row->agent);
        }
    }
}
