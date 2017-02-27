<?php


namespace UaComparator;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\ErrorHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Symfony\Component\Console\Application;

/**
 * Class UaComparator
 *
 * @category   UaComparator
 *
 * @author     Thomas Müller <mimmi20@live.de>
 */
class UaComparator extends Application
{
    public function __construct()
    {
        parent::__construct('Useragent Parser Comparator Project', 'dev-master');

        $logger = new Logger('ua-comparator');
        $logger->pushHandler(new StreamHandler('log/error.log', Logger::ERROR));
        $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR));

        ErrorHandler::register($logger);

        $adapter  = new Local('data/cache/general/');
        $cache    = new FilesystemCachePool(new Filesystem($adapter));
        $cache->setLogger($logger);

        $config = new Config(['data/configs/config.json']);

        $commands = [
            new Command\CompareCommand($logger, $cache, $config),
            new Command\ParseCommand($logger, $cache, $config),
        ];

        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}
