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

use Mimmi20\MonologFactory\MonologHandlerPluginManager;
use Mimmi20\Monolog\Formatter\StreamFormatter;
use Mimmi20\Monolog\Handler\CallbackFilterHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\LogRecord;
use Monolog\Processor\HostnameProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\TagProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;

return [
    'monolog_handlers' => [
        'factories' => [
            'HTTP-STREAM' => static function (ContainerInterface $container): StreamHandler {
                return $container->get(MonologHandlerPluginManager::class)->get(
                    StreamHandler::class,
                    [
                        'stream' => 'log/app_http_error.log',
                        'level' => LogLevel::DEBUG,

                        'formatter' => [
                            'type' => StreamFormatter::class,

                            'options' => [
                                'format' => '%message%',
                                'tableStyle' => 'box',
                                'dateFormat' => 'd.m.Y H:i:s',
                                'allowInlineLineBreaks' => true,
                                'includeStacktraces' => true,
                            ],
                        ],
                    ],
                );
            },
            'CLI-STREAM' => static function (ContainerInterface $container): StreamHandler {
                return $container->get(MonologHandlerPluginManager::class)->get(
                    StreamHandler::class,
                    [
                        'stream' => 'log/app_cli_error.log',
                        'level' => LogLevel::DEBUG,

                        'formatter' => [
                            'type' => StreamFormatter::class,

                            'options' => [
                                'format' => '%message%',
                                'tableStyle' => 'box',
                                'dateFormat' => 'd.m.Y H:i:s',
                                'allowInlineLineBreaks' => true,
                                'includeStacktraces' => true,
                            ],
                        ],
                    ],
                );
            },
            'ERROR-STREAM' => static function (ContainerInterface $container): StreamHandler {
                return $container->get(MonologHandlerPluginManager::class)->get(
                    StreamHandler::class,
                    [
                        'stream' => 'log/app_log_error.log',
                        'level' => LogLevel::NOTICE,

                        'formatter' => [
                            'type' => StreamFormatter::class,

                            'options' => [
                                'format' => '%message%',
                                'tableStyle' => 'box',
                                'dateFormat' => 'd.m.Y H:i:s',
                                'allowInlineLineBreaks' => true,
                                'includeStacktraces' => true,
                            ],
                        ],
                    ],
                );
            },
        ],
    ],

    'log' => [
        \Psr\Log\LoggerInterface::class => [
            'name' => 'alttarifbewertung.geld.de',
            'timezone' => 'Europe/Berlin',

            // Handlers for Monolog
            'handlers' => [
                [
                    'type' => FilterHandler::class,
                    'enabled' => true,

                    'options' => [
                        'minLevelOrList' => LogLevel::DEBUG,
                        'bubble' => false,

                        'handler' => [
                            'type' => WhatFailureGroupHandler::class,

                            'options' => [
                                'bubble' => true,

                                'handlers' => [
                                    // schreibt Fehler in eine Datei - nur für http-Requests
                                    'http-stream' => [
                                        'type' => CallbackFilterHandler::class,
                                        'enabled' => true,

                                        'options' => [
                                            'handler' => [
                                                'type' => 'HTTP-STREAM',
                                                'enabled' => true,
                                            ],
                                            'filters' => [
                                                /**
                                                 * @param LogRecord  $record
                                                 * @param int|string $handlerLevel
                                                 *
                                                 * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
                                                 */
                                                static fn (LogRecord $record, $handlerLevel): bool => !str_contains(PHP_SAPI, 'cli'),
                                            ],
                                            'level' => LogLevel::INFO,
                                            'bubble' => true,
                                        ],
                                    ],

                                    // schreibt Fehler in die eine Datei - nur für cli-Requests
                                    'cli-stream' => [
                                        'type' => CallbackFilterHandler::class,
                                        'enabled' => true,

                                        'options' => [
                                            'handler' => [
                                                'type' => 'CLI-STREAM',
                                                'enabled' => true,
                                            ],
                                            'filters' => [
                                                /**
                                                 * @param LogRecord  $record
                                                 * @param int|string $handlerLevel
                                                 *
                                                 * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
                                                 */
                                                static fn (LogRecord $record, $handlerLevel): bool => str_contains(PHP_SAPI, 'cli'),
                                            ],
                                            'level' => LogLevel::INFO,
                                            'bubble' => true,
                                        ],
                                    ],

                                    // schreibt Fehler in die Browser-Console - nur für http-Requests
                                    'browserConsole' => [
                                        'type' => CallbackFilterHandler::class,
                                        'enabled' => true,

                                        'options' => [
                                            'handler' => [
                                                'type' => BrowserConsoleHandler::class,
                                                'enabled' => true,

                                                'options' => [
                                                    'level' => LogLevel::DEBUG,
                                                    'bubble' => true,

                                                    'formatter' => [
                                                        'type' => LineFormatter::class,

                                                        'options' => [
                                                            'format' => '[[%channel%]]{macro: autolabel} [[%level_name%]]{font-weight: bold} %message%',
                                                            'dateFormat' => 'c',
                                                            'allowInlineLineBreaks' => true,
                                                            'ignoreEmptyContextAndExtra' => true,
                                                            'includeStacktraces' => true,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'filters' => [
                                                /**
                                                 * @param LogRecord  $record
                                                 * @param int|string $handlerLevel
                                                 *
                                                 * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
                                                 */
                                                static fn (LogRecord $record, $handlerLevel): bool => !str_contains(PHP_SAPI, 'cli'),
                                            ],
                                            'level' => LogLevel::NOTICE,
                                            'bubble' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // Processors for Monolog
            'processors' => [
                'psr' => [
                    'type' => PsrLogMessageProcessor::class,
                ],
                'memory' => [
                    'type' => MemoryUsageProcessor::class,
                ],
                'web' => [
                    'type' => WebProcessor::class,
                ],
                'hostname' => [
                    'type' => HostnameProcessor::class,
                ],
                'tags' => [
                    'type' => TagProcessor::class,
                    'options' => [
                        'tags' => [
                            'app' => 'alttarifbewertung.geld.de',
                            'vhost' => 'atb-59380',
                            'git' => '32d1dff374ff096fd79e701103ea462882be7105',
                        ],
                    ],
                ],
            ],

            'errorLevelMap' => [], // use default map
            'exceptionLevelMap' => [], // use default map
            'fatalLevel' => LogLevel::EMERGENCY,
        ],
    ],
];
