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

namespace UaComparator\Module\Mapper;

use BrowserDetector\Version\Version;
use stdClass;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class UaParser implements MapperInterface
{
    /** @throws void */
    public function __construct(private readonly InputMapper | null $mapper)
    {
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param stdClass $parserResult
     *
     * @return Result the object containing the browsers details
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function map($parserResult, string $agent): Result
    {
        $browser = new Browser(
            $this->mapper->mapBrowserName($parserResult->ua->family),
            null,
            new Version(
                (string) $parserResult->ua->major,
                (string) $parserResult->ua->minor,
                (string) $parserResult->ua->patch,
            ),
        );

        $os = new Os(
            $this->mapper->mapOsName($parserResult->os->family),
            null,
            null,
            new Version(
                (string) $parserResult->os->major,
                (string) $parserResult->os->minor,
                (string) $parserResult->os->patch,
            ),
        );

        $device = new Device(null, null);
        $engine = new Engine(null);

        $requestFactory = new GenericRequestFactory();

        return new Result(
            $requestFactory->createRequestForUserAgent($agent),
            $device,
            $os,
            $browser,
            $engine,
        );
    }

    /** @throws void */
    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
