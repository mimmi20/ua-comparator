<?php
/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2023, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator\Module;

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
    private string $name = '';

    private Response | null $detectionResult = null;

    private string $agent = '';

    private Ubench | null $bench = null;

    private array | null $config = null;

    private CheckInterface | null $check = null;

    private MapperInterface | null $mapper = null;

    private \GuzzleHttp\Psr7\Request $request;

    private float $duration = 0.0;

    private int $memory = 0;

    /**
     * creates the module
     */
    public function __construct(private LoggerInterface $logger, private CacheItemPoolInterface $cache)
    {
        $this->bench = new Ubench();
    }

    /**
     * initializes the module
     */
    public function init(): self
    {
        return $this;
    }

    public function detect(string $agent, array $headers = []): self
    {
        $this->agent = $agent;
        $body        = null;

        $params   = [$this->config['ua-key'] => $agent] + $this->config['params'];
        $headers += $this->config['headers'];

        if ('GET' === $this->config['method']) {
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
            $this->logger->error(new ConnectException('could not connect to uri "' . $uri . '"', $this->request, $e));
        } catch (RequestException $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    /**
     * starts the detection timer
     */
    public function startTimer(): self
    {
        $this->bench->start();

        return $this;
    }

    /**
     * stops the detection timer
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
     */
    public function getTime(): float
    {
        return $this->duration;
    }

    /**
     * returns the maximum needed memory
     */
    public function getMaxMemory(): int
    {
        return $this->memory;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getConfig(): array | null
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getCheck(): CheckInterface | null
    {
        return $this->check;
    }

    public function setCheck(CheckInterface $check): self
    {
        $this->check = $check;

        return $this;
    }

    public function getMapper(): MapperInterface | null
    {
        return $this->mapper;
    }

    public function setMapper(MapperInterface $mapper): self
    {
        $this->mapper = $mapper;

        return $this;
    }

    public function getDetectionResult(): Result | null
    {
        if (null === $this->detectionResult) {
            return;
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

            return;
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
    }
}
