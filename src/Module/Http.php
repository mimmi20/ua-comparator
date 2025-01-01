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
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaComparator\Helper\Request;
use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;
use UaResult\Result\Result;
use Ubench;
use UnexpectedValueException;

use function http_build_query;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class Http implements ModuleInterface
{
    private string $name                     = '';
    private Response | null $detectionResult = null;
    private string $agent                    = '';
    private Ubench | null $bench             = null;
    private array | null $config             = null;
    private CheckInterface | null $check     = null;
    private MapperInterface | null $mapper   = null;
    private GuzzleHttpRequest $request;
    private float $duration     = 0.0;
    private int | float $memory = 0;

    /** @throws void */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $cache,
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

    /** @throws void */
    public function detect(string $agent, array $headers = []): self
    {
        $this->agent = $agent;
        $body        = null;

        $params   = [$this->config['ua-key'] => $agent] + $this->config['params'];
        $headers += $this->config['headers'];

        if ($this->config['method'] === 'GET') {
            $uri = $this->config['uri'] . '?' . http_build_query($params, '', '&');
        } else {
            $uri  = $this->config['uri'];
            $body = http_build_query($params, '', '&');
        }

        $this->request = new GuzzleHttpRequest($this->config['method'], $uri, $headers, $body);
        $requestHelper = new Request();

        $this->detectionResult = null;

        try {
            $this->detectionResult = $requestHelper->getResponse($this->request, new Client());
        } catch (ConnectException $e) {
            $this->logger->error(
                new ConnectException('could not connect to uri "' . $uri . '"', $this->request, $e),
            );
        } catch (RequestException $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @throws void
     */
    public function startTimer(): self
    {
        $this->bench->start();

        return $this;
    }

    /**
     * stops the detection timer
     *
     * @throws Exception
     */
    public function endTimer(): self
    {
        $this->bench->end();

        $this->duration = $this->bench->getTime(true);
        $this->memory   = $this->bench->getMemoryPeak(true);

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
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @throws void */
    public function getConfig(): array | null
    {
        return $this->config;
    }

    /** @throws void */
    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    /** @throws void */
    public function getCheck(): CheckInterface | null
    {
        return $this->check;
    }

    /** @throws void */
    public function setCheck(CheckInterface $check): self
    {
        $this->check = $check;

        return $this;
    }

    /** @throws void */
    public function getMapper(): MapperInterface | null
    {
        return $this->mapper;
    }

    /** @throws void */
    public function setMapper(MapperInterface $mapper): self
    {
        $this->mapper = $mapper;

        return $this;
    }

    /** @throws void */
    public function getDetectionResult(): Result | null
    {
        if ($this->detectionResult === null) {
            return null;
        }

        try {
            $return = $this->getCheck()->getResponse(
                $this->detectionResult,
                $this->request,
                $this->cache,
                $this->logger,
                $this->agent,
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
            if (isset($return->result)) {
                return $this->getMapper()->map($return->result, $this->agent);
            }

            return $this->getMapper()->map($return, $this->agent);
        } catch (UnexpectedValueException $e) {
            $this->logger->error($e);
        }

        return null;
    }
}
