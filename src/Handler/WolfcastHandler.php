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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Wolfcast\BrowserDetection;

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

final readonly class WolfcastHandler
{
    /** @throws InvalidArgumentException */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $start = microtime(true);
        new BrowserDetection('Test String');
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
            'version' => InstalledVersions::getPrettyVersion('wolfcast/browser-detection'),
        ];

        if ($hasUa) {
            $start     = microtime(true);
            $result    = new BrowserDetection($agentString);
            $parseTime = microtime(true) - $start;

            $output['result']['parsed'] = [
                'device' => [
                    'architecture' => null,
                    'deviceName' => null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => null,
                    'dualOrientation' => null,
                    'simCount' => null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'type' => null,
                    'ismobile' => $result->isMobile(),
                    'istv' => null,
                    'bits' => null,
                ],
                'client' => [
                    'name' => $result->getName() !== 'unknown' ? $result->getName() : null,
                    'modus' => null,
                    'version' => $result->getVersion() !== 'unknown' ? $result->getVersion() : null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => $result->getPlatform() !== 'unknown' ? $result->getPlatform() : null,
                    'marketingName' => null,
                    'version' => $result->getPlatformVersion(
                        true,
                    ) !== 'unknown' ? $result->getPlatformVersion(true) : null,
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ],
                'raw' => null,
            ];

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
