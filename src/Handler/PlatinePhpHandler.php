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

namespace UaComparator\Handler;

use Composer\InstalledVersions;
use InvalidArgumentException;
use JsonException;
use Platine\UserAgent\UserAgent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\Http\Message\Response;
use Throwable;

use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function memory_get_peak_usage;
use function microtime;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class PlatinePhpHandler
{
    /** @throws void */
    public function __construct(private LoggerInterface $logger)
    {
        // nothing to do here
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $bc = new UserAgent();

        $start = microtime(true);
        $r     = $bc->parse('Test String');
        $r->device();
        $r->browser();
        $r->os();
        $r->engine();
        $r->cpu();
        $initTime = microtime(true) - $start;

        $hasUa       = $request->hasHeader('user-agent');
        $agentString = $request->getHeaderLine('user-agent');

        $output = [
            'headers' => ['user-agent' => $agentString],
            'result' => [
                'parsed' => null,
                'err' => null,
            ],
            'parse_time' => 0,
            'init_time' => $initTime,
            'memory_used' => 0,
            'version' => InstalledVersions::getPrettyVersion('platine-php/user-agent'),
        ];

        if ($hasUa) {
            $start = microtime(true);

            try {
                $r      = $bc->parse($agentString);
                $device = $r->device();
                $client = $r->browser();
                $os     = $r->os();
                $engine = $r->engine();
                $cpu    = $r->cpu();

                $parseTime = microtime(true) - $start;

                $output['result']['parsed'] = [
                    'device' => [
                        'architecture' => null,
                        'deviceName' => $device->getModel(),
                        'marketingName' => null,
                        'manufacturer' => null,
                        'brand' => $device->getVendor(),
                        'dualOrientation' => null,
                        'simCount' => null,
                        'display' => [
                            'width' => null,
                            'height' => null,
                            'touch' => null,
                            'type' => null,
                            'size' => null,
                        ],
                        'type' => $device->getType(),
                        'ismobile' => null,
                        'istv' => null,
                        'bits' => null,
                    ],
                    'client' => [
                        'name' => $client->getName(),
                        'modus' => null,
                        'version' => $client->getVersion(),
                        'manufacturer' => null,
                        'bits' => null,
                        'isbot' => null,
                        'type' => null,
                    ],
                    'platform' => [
                        'name' => $os->getName(),
                        'marketingName' => null,
                        'version' => $os->getVersion(),
                        'manufacturer' => null,
                        'bits' => null,
                    ],
                    'engine' => [
                        'name' => $engine->getName(),
                        'version' => $engine->getVersion(),
                        'manufacturer' => null,
                    ],
                    'raw' => [
                        'device' => (string) $device,
                        'client' => (string) $client,
                        'os' => (string) $os,
                        'engine' => (string) $engine,
                        'cpu' => (string) $cpu,
                    ],
                ];
            } catch (Throwable $e) {
                $this->logger->error($e);

                return new Response(
                    status: Response::STATUS_BAD_REQUEST,
                    headers: ['Content-Type' => 'application/json'],
                    body: json_encode(
                        new InvalidArgumentException(
                            'Error while parsing',
                            0,
                            $e,
                        ),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
                    ) . "\n",
                );
            }

            $output['parse_time'] = $parseTime;
        }

        $output['memory_used'] = memory_get_peak_usage();

        try {
            return new Response(
                status: Response::STATUS_OK,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(
                    $output,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
                ) . "\n",
            );
        } catch (JsonException $e) {
            return new Response(
                status: Response::STATUS_BAD_REQUEST,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(
                    new InvalidArgumentException(
                        'Unable to encode given data as JSON: ' . json_last_error_msg(),
                        json_last_error(),
                        $e,
                    ),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
                ) . "\n",
            );
        }
    }
}
