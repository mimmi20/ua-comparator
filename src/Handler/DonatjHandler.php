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

use function array_keys;
use function donatj\UserAgent\parse_user_agent;
use function is_int;
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

final readonly class DonatjHandler
{
    /** @throws InvalidArgumentException */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $start = microtime(true);
        parse_user_agent('Test String');
        $initTime = microtime(true) - $start;

        $hasUa       = $request->hasHeader('user-agent');
        $agentString = $request->getHeaderLine('user-agent');

        $headerNames = array_keys($request->getHeaders());

        $headers = [];

        foreach ($headerNames as $headerName) {
            if (is_int($headerName)) {
                continue;
            }

            $headers[$headerName] = $request->getHeaderLine($headerName);
        }

        $output = [
            'hasUa' => $hasUa,
            'headers' => $headers,
            'result' => [
                'parsed' => null,
                'err' => null,
            ],
            'parse_time' => 0,
            'init_time' => $initTime,
            'memory_used' => 0,
            'version' => InstalledVersions::getPrettyVersion('donatj/phpuseragentparser'),
        ];

        if ($hasUa) {
            $start     = microtime(true);
            $r         = parse_user_agent($agentString);
            $parseTime = microtime(true) - $start;

            $output['result']['parsed'] = [
                'device' => [
                    'deviceName' => null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'dualOrientation' => null,
                    'type' => null,
                    'simCount' => null,
                    'ismobile' => null,
                ],
                'client' => [
                    'name' => $r['browser'] ?? null,
                    'modus' => null,
                    'version' => $r['version'] ?? null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => $r['platform'] ?? null,
                    'marketingName' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ],
                'raw' => $r,
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
            throw new InvalidArgumentException(
                'Unable to encode given data as JSON: ' . json_last_error_msg(),
                json_last_error(),
                $e,
            );
        }
    }
}
