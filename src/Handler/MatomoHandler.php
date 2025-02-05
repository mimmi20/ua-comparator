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

use BrowserDetector\Version\VersionBuilder;
use BrowserDetector\Version\VersionInterface;
use Composer\InstalledVersions;
use DeviceDetector\Cache\PSR16Bridge;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use InvalidArgumentException;
use JsonException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
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

final readonly class MatomoHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws JsonException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $cacheDir = 'data/cache/matomo';

        $fileAdapter = new LocalFilesystemAdapter($cacheDir);
        $cache       = new SimpleCache(
            new Flysystem(
                new Filesystem($fileAdapter),
            ),
        );

        $start = microtime(true);
        $dd    = new DeviceDetector('Test String');
        $dd->parse();
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
            'headers' => array_change_key_case($headers, CASE_LOWER),
            'result' => [
                'parsed' => null,
                'err' => null,
            ],
            'parse_time' => 0,
            'init_time' => $initTime,
            'memory_used' => 0,
            'version' => InstalledVersions::getPrettyVersion('matomo/device-detector'),
        ];

        $dd->skipBotDetection();

        if ($hasUa) {
            $dd->setUserAgent($agentString);

            $clientHints = ClientHints::factory($headers);
            $dd->setClientHints($clientHints);
            $dd->setCache(new PSR16Bridge($cache));

            $start1 = microtime(true);
            $dd->parse();

            $clientInfo = $dd->getClient();
            $osInfo     = $dd->getOs();
            $model      = $dd->getModel();
            $brand      = $dd->getBrandName();
            $device     = $dd->getDeviceName();
            $isMobile   = $dd->isMobile();

            $parseTime1 = microtime(true) - $start1;

            $dd->skipBotDetection(false);
            $dd->setUserAgent($agentString . ' - ');

            $start2 = microtime(true);

            $dd->parse();

            $isBot   = $dd->isBot();
            $botInfo = $dd->getBot();

            $parseTime2 = microtime(true) - $start2;

            $output['result']['parsed'] = [
                'device' => [
                    'architecture' => null,
                    'deviceName' => null,
                    'marketingName' => $model,
                    'manufacturer' => null,
                    'brand' => $brand,
                    'dualOrientation' => null,
                    'simCount' => null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'type' => $device,
                    'ismobile' => $isMobile,
                    'istv' => null,
                    'bits' => null,
                ],
                'client' => [
                    'name' => $isBot ? ($botInfo['name'] ?? null) : ($clientInfo['name'] ?? null),
                    'version' => $isBot ? null : (new VersionBuilder())->set(
                        $clientInfo['version'] ?? '',
                    )->getVersion(VersionInterface::IGNORE_MICRO),
                    'manufacturer' => null,
                    'type' => $isBot ? ($botInfo['category'] ?? null) : ($clientInfo['type'] ?? null),
                    'isbot' => $isBot,
                ],
                'platform' => [
                    'name' => $osInfo['name'] ?? null,
                    'marketingName' => $osInfo['name'] ?? null,
                    'version' => (new VersionBuilder())->set(
                        $osInfo['version'] ?? '',
                    )->getVersion(VersionInterface::IGNORE_MICRO),
                    'manufacturer' => null,
                ],
                'engine' => [
                    'name' => $isBot ? null : ($clientInfo['engine'] ?? null),
                    'version' => $isBot ? null : (new VersionBuilder())->set(
                        $clientInfo['engine_version'] ?? '',
                    )->getVersion(VersionInterface::IGNORE_MICRO),
                    'manufacturer' => null,
                ],
                'raw' => null,
            ];

            $output['parse_time'] = $isBot ? $parseTime2 : $parseTime1;
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
