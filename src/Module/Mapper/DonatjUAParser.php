<?php
/**
 * Copyright (c) 2015, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Module\Mapper;

use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class DonatjUAParser implements MapperInterface
{
    /**
     * @var null|\UaDataMapper\InputMapper
     */
    private $mapper = null;

    /**
     * Gets the information about the browser by User Agent
     *
     * @param \stdClass $parserResult
     * @param string    $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details.
     */
    public function map($parserResult, $agent)
    {
        $browserName    = $this->mapper->mapBrowserName($parserResult->browser);
        $browserVersion = $this->mapper->mapBrowserVersion($parserResult->version, $browserName);

        $browser = new Browser(
            $agent,
            [
                'name'         => $browserName,
                'modus'        => null,
                'version'      => $browserVersion,
                'manufacturer' => null,
                'bits'         => null,
                'type'         => null,
            ]
        );

        $device = new Device(
            $agent,
            []
        );

        $os = new Os(
            $agent,
            [
                'name'         => $this->mapper->mapOsName($parserResult->platform),
                'version'      => null,
                'manufacturer' => null,
                'bits'         => null,
            ]
        );

        $engine = new Engine(
            $agent,
            []
        );

        return new Result($agent, $device, $os, $browser, $engine);
    }

    /**
     * @return null|\UaDataMapper\InputMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param \UaDataMapper\InputMapper $mapper
     *
     * @return \UaComparator\Module\Mapper\MapperInterface
     */
    public function setMapper(InputMapper $mapper)
    {
        $this->mapper = $mapper;

        return $this;
    }
}
