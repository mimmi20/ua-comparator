<?php
/**
 * This file is part of the ua-comparator package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace UaComparator;

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
        $logger->pushHandler(new StreamHandler('log/error.log', Logger::NOTICE));
        ErrorHandler::register($logger);

        $cache  = new FilesystemAdapter('', 0, 'data/cache/general/');
        $config = new Config(['data/configs/config.json']);

        $this->add(new Command\CompareCommand($logger, $cache, $config));
        $this->add(new Command\ParseCommand($logger, $cache, $config));
    }
}
