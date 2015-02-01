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
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Module;

use BrowserDetector\BrowserDetector;
use Monolog\Logger;
use WurflCache\Adapter\AdapterInterface;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class MobileDetect implements ModuleInterface
{
    /**
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     * @var \BrowserDetector\BrowserDetector
     */
    private $input = null;

    /**
     * @var \WurflCache\Adapter\AdapterInterface
     */
    private $cache = null;

    /**
     * @var integer
     */
    private $timer = 0;

    /**
     * creates the module
     *
     * @param \Monolog\Logger                      $logger
     * @param \WurflCache\Adapter\AdapterInterface $cache
     */
    public function __construct(Logger $logger, AdapterInterface $cache)
    {
        $this->logger = $logger;
        $this->cache  = $cache;

        $this->input = new BrowserDetector();
        //$this->input->setInterface(new UserAgent());
        $this->input->setLogger($logger);
        $this->input->setCache($this->cache);
    }

    /**
     * initializes the module
     *
     * @throws \BrowserDetector\Input\Exception
     * @return \UaComparator\Module\MobileDetect
     */
    public function init()
    {
        $this->detect('');

        return $this;
    }

    public function detect($agent)
    {
    }

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\MobileDetect
     */
    public function startTimer()
    {
        $this->timer = microtime(true);

        return $this;
    }

    /**
     * stops the detection timer and returns the duration
     * @return float
     */
    public function endTimer()
    {
        $duration    = microtime(true) - $this->timer;
        $this->timer = 0;

        return $duration;
    }

    /**
     * @return \BrowserDetector\BrowserDetector
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @param \BrowserDetector\BrowserDetector $input
     *
     * @return \UaComparator\Module\MobileDetect
     */
    public function setInput(BrowserDetector $input)
    {
        $this->input = $input;

        return $this;
    }
}