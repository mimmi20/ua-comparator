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

use BrowserDetector\DetectorFactory;
use Composer\InstalledVersions;
use InvalidArgumentException;
use JsonException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use React\Http\Message\Response;
use RuntimeException;
use UnexpectedValueException;

use function array_change_key_case;
use function array_keys;
use function is_int;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function memory_get_peak_usage;
use function microtime;

use const CASE_LOWER;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class BrowserDetectorHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws RuntimeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws JsonException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $cacheDir = 'data/cache/browser';

        $fileAdapter = new LocalFilesystemAdapter($cacheDir);
        $cache       = new SimpleCache(
            new Flysystem(
                new Filesystem($fileAdapter),
            ),
        );

        $start    = microtime(true);
        $logger   = new NullLogger();
        $factory  = new DetectorFactory($cache, $logger);
        $detector = $factory();
        $detector->getBrowser('Test String');
        $initTime = microtime(true) - $start;

        $hasUa = $request->hasHeader('user-agent');

        $headerNames = array_keys($request->getHeaders());

        $headers = [];

        foreach ($headerNames as $headerName) {
            if (is_int($headerName)) {
                continue;
            }

            $headers[$headerName] = $request->getHeaderLine($headerName);
        }

        $output = [
            'headers' => array_change_key_case($headers, CASE_LOWER),
            'result' => [
                'parsed' => null,
                'err' => null,
            ],
            'parse_time' => 0,
            'init_time' => $initTime,
            'memory_used' => 0,
            'version' => InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
        ];

        if ($hasUa) {
            $start     = microtime(true);
            $r         = $detector->getBrowser($request);
            $parseTime = microtime(true) - $start;

            $output['result']['parsed'] = [
                'device' => $r['device'],
                'client' => [
                    'name' => $r['client']['name'],
                    'modus' => null,
                    'version' => $r['client']['version'],
                    'manufacturer' => $r['client']['manufacturer'],
                    'bits' => null,
                    'type' => $r['client']['type'],
                    'isbot' => $r['client']['isbot'],
                ],
                'platform' => [
                    'name' => $r['os']['name'],
                    'marketingName' => $r['os']['marketingName'],
                    'version' => $r['os']['version'],
                    'manufacturer' => $r['os']['manufacturer'],
                    'bits' => null,
                ],
                'engine' => $r['engine'],
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
