<?php
/**
 * This file is part of the mimmi20/mezzio-sample-project package.
 *
 * Copyright (c) 2021, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
//$cacheConfig = ['config_cache_path' => 'data/cache/config-cache.php'];

$aggregator = new ConfigAggregator(
    [
        \Mimmi20\Monolog\Formatter\ConfigProvider::class,
        \Mimmi20\MonologFactory\ConfigProvider::class,
        \Laminas\Hydrator\ConfigProvider::class,
        \Laminas\Diactoros\ConfigProvider::class,
        // Include cache configuration
        //new ArrayProvider($cacheConfig),
        // Load application config in a pre-defined order in such a way that local settings
        // overwrite global settings. (Loaded as first to last):
        //   - `global.php`
        //   - `*.global.php`
        //   - `local.php`
        //   - `*.local.php`
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
        // Load development config if it exists
        new PhpFileProvider(realpath(__DIR__) . '/development.config.php'),
    ],
    //$cacheConfig['config_cache_path']
);

return $aggregator->getMergedConfig();
