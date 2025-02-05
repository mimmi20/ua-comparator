<?php
/**
 * This file is part of the mimmi20/mezzio-sample-project package.
 *
 * Copyright (c) 2021, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    // Provides application-wide services.
    // We recommend using fully-qualified class names whenever possible as
    // service names.
    'dependencies' => [
        // Use 'factories' for services provided by callbacks/factory classes.
        'factories' => [
            \UaComparator\Handler\AgentZeroHandler::class => InvokableFactory::class,
            \UaComparator\Handler\BrowscapPhpHandler::class => InvokableFactory::class,
            \UaComparator\Handler\BrowserDetectorHandler::class => InvokableFactory::class,
            \UaComparator\Handler\CbschuldHandler::class => InvokableFactory::class,
            \UaComparator\Handler\CrawlerDetectHandler::class => InvokableFactory::class,
            \UaComparator\Handler\DonatjHandler::class => InvokableFactory::class,
            \UaComparator\Handler\EndorphinHandler::class => InvokableFactory::class,
            \UaComparator\Handler\ForocoDetectionHandler::class => InvokableFactory::class,
            \UaComparator\Handler\FyreUseragentHandler::class => InvokableFactory::class,
            \UaComparator\Handler\MatomoHandler::class => InvokableFactory::class,
            \UaComparator\Handler\MobileDetectHandler::class => InvokableFactory::class,
            \UaComparator\Handler\PlatinePhpHandler::class => \UaComparator\Handler\PlatinePhpHandlerFactory::class,
            \UaComparator\Handler\UaParserHandler::class => InvokableFactory::class,
            \UaComparator\Handler\WhichBrowserHandler::class => InvokableFactory::class,
            \UaComparator\Handler\WolfcastHandler::class => InvokableFactory::class,
            \UaComparator\Handler\WootheeHandler::class => InvokableFactory::class,
        ],
    ],
];
