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

file_put_contents(
    __DIR__ . '/../data/version.txt',
    mb_substr(
        hash(
            'sha512',
            file_get_contents(
                'https://raw.githubusercontent.com/ua-parser/uap-core/master/regexes.yaml',
            ),
        ),
        0,
        7,
    ),
);
