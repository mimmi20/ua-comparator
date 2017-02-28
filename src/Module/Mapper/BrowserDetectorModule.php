<?php
/**
 * This file is part of the ua-comparator package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace UaComparator\Module\Mapper;

use Psr\Cache\CacheItemPoolInterface;
use UaDataMapper\InputMapper;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class BrowserDetectorModule implements MapperInterface
{
    /**
     * @var \UaDataMapper\InputMapper|null
     */
    private $mapper = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \UaDataMapper\InputMapper         $mapper
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     */
    public function __construct(InputMapper $mapper, CacheItemPoolInterface $cache)
    {
        $this->mapper = $mapper;
        $this->cache  = $cache;
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param \UaResult\Result\Result $parserResult
     * @param string                  $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details
     */
    public function map($parserResult, $agent)
    {
        return $parserResult;
    }

    /**
     * @return null|\UaDataMapper\InputMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}
