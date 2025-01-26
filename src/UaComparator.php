<?php

/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator;

use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\LogicException;

final class UaComparator extends Application
{
    /**
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function __construct()
    {
        parent::__construct('Useragent Parser Comparator Project', 'dev-master');

        $logger = new Logger('ua-comparator');
        $logger->pushHandler(new StreamHandler('log/error.log', LogLevel::NOTICE));
        ErrorHandler::register($logger);

        $browscapAdapter = new LocalFilesystemAdapter('data/cache/general/');
        $cache           = new Pool(
            new Flysystem(
                new Filesystem($browscapAdapter),
            ),
        );

        $config = new Config(['data/configs/config.json']);

        $this->add(new Command\CompareCommand($logger, $cache, $config));
        $this->add(new Command\ParseCommand($logger, $config));
    }
}
