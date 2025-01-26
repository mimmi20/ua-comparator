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
use EndorphinStudio\Detector\Detector;
use EndorphinStudio\Detector\Exception\StorageException;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use ReflectionException;

use function array_keys;
use function is_int;
use function json_decode;
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

final readonly class EndorphinHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws StorageException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $start    = microtime(true);
        $detector = new Detector();

        $detector->analyse('Test String');
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
            'version' => InstalledVersions::getPrettyVersion('endorphin-studio/browser-detector'),
        ];

        if ($hasUa) {
            $start = microtime(true);
            $r     = $detector->analyse($agentString);
            $end   = microtime(true) - $start;

            $r = json_decode((string) json_encode($r));

            $output['result']['parsed'] = [
                'device' => [
                    'deviceName' => $r->device->model ?? null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => $r->device->name ?? null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => $r->isTouch ?? null,
                        'type' => null,
                        'size' => null,
                    ],
                    'dualOrientation' => null,
                    'type' => $r->device->type ?? null,
                    'simCount' => null,
                    'ismobile' => $r->isMobile ?? null,
                ],
                'client' => [
                    'name' => $r->isRobot ? ($r->robot->name ?? null) : ($r->browser->name ?? null),
                    'modus' => null,
                    'version' => $r->browser->version ?? null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => $r->isRobot ?? null,
                ],
                'platform' => [
                    'name' => $r->os->name ?? null,
                    'marketingName' => null,
                    'version' => $r->os->version ?? null,
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
