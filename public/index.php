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

use FrameworkX\App;

// Delegate static file requests back to the PHP built-in webserver
if (PHP_SAPI === 'cli-server' && __FILE__ !== $_SERVER['SCRIPT_FILENAME']) {
    return false;
}

chdir(dirname(__DIR__));

require 'vendor/autoload.php';

ini_set('memory_limit', '-1');

/**
 * Self-called anonymous function that creates its own scope and keeps the global namespace clean.
 */
(static function (): void {
    try {
        $app = new App();
        (require 'config/routes.php')($app);

        $app->run();
    } catch (Throwable $e) {
        var_dump($e);
    }
})();
