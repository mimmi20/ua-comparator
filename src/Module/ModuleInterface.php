<?php


namespace UaComparator\Module;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
interface ModuleInterface
{
    /**
     * creates the module
     *
     * @param \Psr\Log\LoggerInterface          $logger
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     */
    public function __construct(LoggerInterface $logger, CacheItemPoolInterface $cache);

    /**
     * initializes the module
     *
     * @return \UaComparator\Module\ModuleInterface
     */
    public function init();

    /**
     * @param string $agent
     *
     * @return \UaComparator\Module\ModuleInterface
     */
    public function detect($agent);

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\ModuleInterface
     */
    public function startTimer();

    /**
     * stops the detection timer
     *
     * @return \UaComparator\Module\ModuleInterface
     */
    public function endTimer();

    /**
     * returns the needed time
     *
     * @return float
     */
    public function getTime();

    /**
     * returns the maximum needed memory
     *
     * @return int
     */
    public function getMaxMemory();

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     *
     * @return \UaComparator\Module\ModuleInterface
     */
    public function setName($name);

    /**
     * @return array|null
     */
    public function getConfig();

    /**
     * @param array $config
     *
     * @return \UaComparator\Module\Http
     */
    public function setConfig(array $config);

    /**
     * @return null|\UaComparator\Module\Check\CheckInterface
     */
    public function getCheck();

    /**
     * @param \UaComparator\Module\Check\CheckInterface $check
     *
     * @return \UaComparator\Module\Http
     */
    public function setCheck(CheckInterface $check);

    /**
     * @return null|\UaComparator\Module\Mapper\MapperInterface
     */
    public function getMapper();

    /**
     * @param \UaComparator\Module\Mapper\MapperInterface $mapper
     *
     * @return \UaComparator\Module\Http
     */
    public function setMapper(MapperInterface $mapper);

    /**
     * @return \UaResult\Result\Result|null
     */
    public function getDetectionResult();
}
