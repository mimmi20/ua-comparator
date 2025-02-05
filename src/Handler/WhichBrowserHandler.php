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
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use WhichBrowser\Parser;

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

final readonly class WhichBrowserHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $cacheDir = 'data/cache/whichbrowser';

        $browscapAdapter = new LocalFilesystemAdapter($cacheDir);
        $cache           = new Pool(
            new Flysystem(
                new Filesystem($browscapAdapter),
            ),
        );
        $parser          = new Parser();
        $start           = microtime(true);
        $parser->analyse(['User-Agent' => 'Test String'], ['cache' => $cache]);
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
            'version' => InstalledVersions::getPrettyVersion('whichbrowser/parser'),
        ];

        if ($hasUa) {
            $start = microtime(true);
            $parser->analyse($headers, ['cache' => $cache]);
            $isMobile  = $parser->isMobile();
            $parseTime = microtime(true) - $start;

            $output['result']['parsed'] = [
                'device' => [
                    'architecture' => null,
                    'deviceName' => $parser->device->model,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => $parser->device->manufacturer,
                    'dualOrientation' => null,
                    'simCount' => null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'type' => $parser->device->type,
                    'ismobile' => $isMobile,
                    'istv' => null,
                    'bits' => null,
                ],
                'client' => [
                    'name' => $parser->browser->name,
                    'modus' => null,
                    'version' => $parser->browser->version->value ?? null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => $parser->os->name,
                    'marketingName' => null,
                    'version' => $parser->os->version->value ?? null,
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => $parser->engine->name,
                    'version' => null,
                    'manufacturer' => null,
                ],
                'raw' => $parser->toArray(),
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
