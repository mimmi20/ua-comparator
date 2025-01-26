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

use BrowscapPHP\Browscap;
use BrowscapPHP\Exception;
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

use function array_keys;
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

final readonly class BrowscapPhpHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $cacheDir = 'data/cache/browscap';

        $browscapAdapter = new LocalFilesystemAdapter($cacheDir);
        $cache           = new SimpleCache(
            new Flysystem(
                new Filesystem($browscapAdapter),
            ),
        );
        $logger          = new NullLogger();
        $bc              = new Browscap($cache, $logger);
        $start           = microtime(true);
        $bc->getBrowser('Test String');
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
            'version' => InstalledVersions::getPrettyVersion('browscap/browscap-php'),
        ];

        if ($hasUa) {
            $start = microtime(true);
            $r     = $bc->getBrowser($agentString);
            $end   = microtime(true) - $start;

            $output['result']['parsed'] = [
                'device' => [
                    'deviceName' => $r->device_name ?? null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => $r->device_maker ?? null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen'),
                        'type' => null,
                        'size' => null,
                    ],
                    'dualOrientation' => null,
                    'type' => $r->device_type ?? null,
                    'simCount' => null,
                    'ismobile' => $r->ismobiledevice ?? null,
                ],
                'client' => [
                    'name' => $r->browser ?? null,
                    'modus' => $r->browser_modus ?? null,
                    'version' => $r->version ?? null,
                    'manufacturer' => $r->browser_maker ?? null,
                    'bits' => $r->browser_bits ?? null,
                    'isbot' => $r->crawler ?? null,
                    'type' => $r->browser_type ?? null,
                ],
                'platform' => [
                    'name' => $r->platform ?? null,
                    'marketingName' => null,
                    'version' => $r->platform_version ?? null,
                    'manufacturer' => $r->platform_maker ?? null,
                    'bits' => $r->platform_bits ?? null,
                ],
                'engine' => [
                    'name' => $r->renderingengine_name ?? null,
                    'version' => $r->renderingengine_version ?? null,
                    'manufacturer' => $r->renderingengine_maker ?? null,
                ],
                'raw' => $r,
            ];

            $output['parse_time'] = $end;
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
