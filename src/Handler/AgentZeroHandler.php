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
use hexydec\agentzero\agentzero;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

use function get_object_vars;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function memory_get_peak_usage;
use function microtime;
use function trim;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class AgentZeroHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $start = microtime(true);
        agentzero::parse('Test String');
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
            'version' => InstalledVersions::getPrettyVersion('hexydec/agentzero'),
        ];

        if ($hasUa) {
            $start     = microtime(true);
            $r         = agentzero::parse($agentString);
            $parseTime = microtime(true) - $start;

            if ($r !== false) {
                $output['result']['parsed'] = [
                    'device' => [
                        'architecture' => $r->architecture,
                        'deviceName' => null,
                        'marketingName' => $r->device === null ? null : trim(
                            $r->device . ' ' . $r->model,
                        ),
                        'manufacturer' => null,
                        'brand' => $r->vendor,
                        'dualOrientation' => null,
                        'simCount' => null,
                        'display' => [
                            'width' => $r->width,
                            'height' => $r->height,
                            'touch' => null,
                            'type' => null,
                            'size' => null,
                        ],
                        'type' => $r->type === 'robot' ? null : $r->category,
                        'ismobile' => $r->ismobiledevice ?? null,
                        'istv' => null,
                        'bits' => $r->bits,
                    ],
                    'client' => [
                        'name' => $r->app ?? $r->browser,
                        'modus' => null,
                        'version' => $r->appversion ?? $r->browserversion,
                        'manufacturer' => null,
                        'bits' => null,
                        'isbot' => $r->type === 'robot',
                        'type' => $r->type !== 'robot' ? null : $r->category,
                    ],
                    'platform' => [
                        'name' => $r->platform,
                        'marketingName' => null,
                        'version' => $r->platformversion,
                        'manufacturer' => null,
                        'bits' => null,
                    ],
                    'engine' => [
                        'name' => $r->engine,
                        'version' => $r->engineversion,
                        'manufacturer' => null,
                    ],
                    'raw' => get_object_vars($r),
                ];
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
