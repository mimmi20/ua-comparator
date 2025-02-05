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

namespace UaComparator\Module;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaComparator\Helper\Request;
use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;
use UaResult\Result\Result;
use Ubench;
use UnexpectedValueException;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class Http implements ModuleInterface
{
    private Response | null $detectionResult = null;
    private string $agent                    = '';
    private readonly Ubench $bench;
    private float $duration = 0.0;
    private int $memory     = 0;

    /**
     * @param array{uri: string, headers: array<string, string>, method: 'GET'|'POST'} $config
     *
     * @throws void
     */
    public function __construct(
        private readonly string $name,
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $cache,
        private readonly CheckInterface $check,
        private readonly MapperInterface $mapper,
        private readonly array $config,
    ) {
        $this->bench = new Ubench();
    }

    /**
     * initializes the module
     *
     * @throws void
     */
    public function init(): self
    {
        return $this;
    }

    /** @throws GuzzleException */
    public function detect(string $agent, array $headers): self
    {
        $this->agent = $agent;

        $headers += $this->config['headers'];

        $uri = $this->config['uri'];

        $request       = new GuzzleHttpRequest($this->config['method'], $uri, $headers);
        $requestHelper = new Request();

        $this->detectionResult = null;

        try {
            $this->detectionResult = $requestHelper->getResponse($request, new Client());
        } catch (ConnectException $e) {
            $this->logger->error(
                new ConnectException('could not connect to uri "' . $uri . '"', $request, $e),
            );
        } catch (\GuzzleHttp\Exception\ServerException | \GuzzleHttp\Exception\RequestException $e) {
            $this->logger->error(
                $e,
            );
            $this->logger->error(
                get_debug_type($e->getRequest())
            );
        } catch (\Throwable $e) {
            $this->logger->error(get_debug_type($e));
            $this->logger->error($e);
        }

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @throws void
     */
    public function startBenchmark(): self
    {
        $this->bench->start();

        return $this;
    }

    /**
     * stops the detection timer
     *
     * @throws Exception
     */
    public function endBenchmark(): self
    {
        $this->bench->end();

        $this->duration = (float) $this->bench->getTime(true);
        $this->memory   = (int) $this->bench->getMemoryPeak(true);

        return $this;
    }

    /**
     * returns the needed time
     *
     * @throws void
     */
    public function getTime(): float
    {
        return $this->duration;
    }

    /**
     * returns the maximum needed memory
     *
     * @throws void
     */
    public function getMaxMemory(): int
    {
        return $this->memory;
    }

    /** @throws void */
    public function getName(): string
    {
        return $this->name;
    }

    /** @throws void */
    public function getDetectionResult(): Result | null
    {
        if ($this->detectionResult === null) {
            return null;
        }

        try {
            $return = $this->check->getResponse(
                response: $this->detectionResult,
                uri: new Uri($this->config['uri']),
                cache: $this->cache,
                logger: $this->logger,
                agent: $this->agent,
            );
        } catch (RequestException $e) {
            $this->logger->error($e);

            return null;
        }

        if (isset($return->duration)) {
            $this->duration = $return->duration;

            $return->duration = null;
        }

        if (isset($return->memory)) {
            $this->memory = $return->memory;

            $return->memory = null;
        }

        try {
            return $this->mapper->map($return, $this->agent);
        } catch (UnexpectedValueException $e) {
            $this->logger->error($e);
        }

        return null;
    }
}
