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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function assert;

final readonly class PlatinePhpHandlerFactory
{
    /** @throws ContainerExceptionInterface */
    public function __invoke(ContainerInterface $container): PlatinePhpHandler
    {
        $logger = $container->get(LoggerInterface::class);

        assert($logger instanceof LoggerInterface);

        return new PlatinePhpHandler($logger);
    }
}
