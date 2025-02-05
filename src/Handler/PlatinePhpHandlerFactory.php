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
use Platine\UserAgent\UserAgent;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\Http\Message\Response;

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

final readonly class PlatinePhpHandlerFactory
{
    /** @throws ContainerExceptionInterface */
    public function __invoke(ContainerInterface $container): PlatinePhpHandler
    {
        $logger   = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new PlatinePhpHandler($logger);
    }
}
