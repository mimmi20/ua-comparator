<?php


namespace UaComparator\Module\Mapper;

use BrowserDetector\Loader\NotFoundException;
use Psr\Cache\CacheItemPoolInterface;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Company\CompanyLoader;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * Browscap.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class Wurfl implements MapperInterface
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
     * @param \stdClass $parserResult
     * @param string    $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details.
     */
    public function map($parserResult, $agent)
    {
        $apiMob = ('true' === $parserResult->controlcap_is_mobile);
        $apiBro = $parserResult->controlcap_advertised_browser;
        $apiVer = $parserResult->controlcap_advertised_browser_version;

        if ($apiMob) {
            $apiOs    = ('iPhone OS' === $parserResult->controlcap_advertised_device_os ? 'iOS' : $parserResult->controlcap_advertised_device_os);
            $apiDev   = $parserResult->model_name;
            $apiMan   = $parserResult->manufacturer_name;
            $apiPhone = ('true' === $parserResult->controlcap_is_phone);

            $brandName = $parserResult->brand_name;

            if ('Opera' === $brandName) {
                $brandName = null;
            }
        } else {
            $apiOs    = null;
            $apiDev   = null;
            $apiMan   = null;
            $apiPhone = false;

            $brandName = null;
        }

        $apiBot       = ('true' === $parserResult->controlcap_is_robot);
        $browserMaker = '';

        $apiOs = trim($apiOs);
        if (!$apiOs) {
            $apiOs = null;
        } else {
            $apiOs = trim($apiOs);
        }

        $marketingName = $parserResult->marketing_name;

        $apiDev        = $this->mapper->mapDeviceName($apiDev);
        $apiMan        = $this->mapper->mapDeviceMaker($apiMan, $apiDev);
        $marketingName = $this->mapper->mapDeviceMarketingName($marketingName, $apiDev);
        $brandName     = $this->mapper->mapDeviceBrandName($brandName, $apiDev);

        if ('Generic' === $apiMan || 'Opera' === $apiMan) {
            $apiMan        = null;
            $apiDev        = null;
            $marketingName = null;
        }

        $apiDev = trim($apiDev);
        if (!$apiDev) {
            $apiDev = null;
        }

        switch (strtolower($apiBro)) {
            case 'microsoft':
                $browserMaker = 'Microsoft';

                switch (strtolower($apiVer)) {
                    case 'internet explorer':
                        $apiBro = 'Internet Explorer';
                        $apiVer = $parserResult->controlcap_advertised_browser_version;
                        break;
                    case 'internet explorer 10':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '10.0';
                        break;
                    case 'internet explorer 9':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '9.0';
                        break;
                    case 'internet explorer 8':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '8.0';
                        break;
                    case 'internet explorer 7':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '7.0';
                        break;
                    case 'internet explorer 6':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '6.0';
                        break;
                    case 'internet explorer 5.5':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '5.5';
                        break;
                    case 'internet explorer 5':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '5.0';
                        break;
                    case 'internet explorer 4.0':
                    case 'internet explorer 4':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '4.0';
                        break;
                    case 'mobile explorer':
                        $apiBro = 'IEMobile';
                        $apiVer = '';
                        break;
                    case 'mobile explorer 4.0':
                        $apiBro = 'IEMobile';
                        $apiVer = '4.0';
                        break;
                    case 'mobile explorer 6':
                        $apiBro = 'IEMobile';
                        $apiVer = '6.0';
                        break;
                    case 'mobile explorer 7.6':
                        $apiBro = 'IEMobile';
                        $apiVer = '7.6';
                        break;
                    case 'mobile explorer 7.11':
                        $apiBro = 'IEMobile';
                        $apiVer = '7.11';
                        break;
                    case 'mobile explorer 6.12':
                        $apiBro = 'IEMobile';
                        $apiVer = '6.12';
                        break;
                    case 'xbox 360':
                        $apiBro = 'Internet Explorer';
                        $apiVer = '9.0';
                        $apiDev = 'Xbox 360';
                        $apiMan = 'Microsoft';
                        break;
                    case 'outlook express':
                        $apiBro = 'Windows Live Mail';
                        $apiVer = '';
                        break;
                    case 'office 2007':
                        $apiBro = 'Office';
                        $apiVer = '2007';
                        break;
                    case 'microsoft office 2007':
                        $apiBro = 'Office';
                        $apiVer = '2007';
                        break;
                    case 'microsoft office':
                        $apiBro = 'Office';
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'microsoft internet explorer':
            case 'msie':
                $apiBro       = 'Internet Explorer';
                $browserMaker = 'Microsoft';
                break;
            case 'microsoft mobile explorer':
                $apiBro       = 'IEMobile';
                $browserMaker = 'Microsoft';
                break;
            case 'microsoft office 2007':
                $browserMaker = 'Microsoft';
                $apiBro       = 'Office';
                $apiVer       = '2007';
                break;
            case 'microsoft office':
                $browserMaker = 'Microsoft';
                $apiBro       = 'Office';
                break;
            case 'microsoft outlook':
                $browserMaker = 'Microsoft';
                $apiBro       = 'Outlook';
                break;
            case 'opera mobi':
                $browserMaker = 'Opera Software ASA';
                $apiBro       = 'Opera Mobile';
                $apiVer       = null;
                break;
            case 'opera tablet':
                $browserMaker = 'Opera Software ASA';
                $apiBro       = 'Opera Tablet';
                $apiVer       = null;
                break;
            case 'google chrome':
            case 'chrome mobile':
            case 'chrome':
                $apiBro       = 'Chrome';
                $apiVer       = null;
                $browserMaker = 'Google';
                break;
            case 'google':
                $browserMaker = 'Google';

                switch (strtolower($apiVer)) {
                    case 'chrome':
                        $apiBro = 'Chrome';
                        $apiVer = $parserResult->controlcap_advertised_browser_version;
                        break;
                    case 'bot':
                        $apiBro = 'Google Bot';
                        $apiVer = null;
                        $apiBot = true;
                        break;
                    case 'wireless transcoder':
                        $apiBro = 'Google Wireless Transcoder';
                        $apiVer = null;
                        $apiBot = true;
                        break;
                    case 'adsense bot':
                        $apiBro = 'AdSense Bot';
                        $apiVer = null;
                        $apiBot = true;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'mozilla firefox':
            case 'firefox':
                $apiBro       = 'Firefox';
                $browserMaker = 'Mozilla';
                if ('3.0' === $apiVer) {
                    $apiVer = null;
                }
                break;
            case 'mozilla':
                $browserMaker = 'Mozilla';

                switch (strtolower($apiVer)) {
                    case 'firefox':
                        $apiBro = 'Firefox';
                        $apiVer = $parserResult->controlcap_advertised_browser_version;
                        break;
                    case 'thunderbird':
                        $apiBro = 'Thunderbird';
                        $apiVer = $parserResult->controlcap_advertised_browser_version;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'fennec':
                $apiBro       = 'Fennec';
                $browserMaker = 'Mozilla';
                $apiVer       = null;
                break;
            case 'apple safari':
            case 'safari':
                $apiBro       = 'Safari';
                $browserMaker = 'Apple';
                $apiVer       = null;
                break;
            case 'apple':
                $browserMaker = 'Apple';

                switch (strtolower($apiVer)) {
                    case 'safari':
                        $apiBro = 'Safari';
                        $apiVer = $parserResult->controlcap_advertised_browser_version;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'opera software opera':
            case 'opera':
                $apiBro       = 'Opera';
                $browserMaker = 'Opera Software ASA';
                $apiVer       = null;
                break;
            case 'opera software':
                $browserMaker = 'Opera Software ASA';

                switch (strtolower($apiVer)) {
                    case 'opera':
                        $apiBro = 'Opera';
                        $apiVer = $parserResult->controlcap_advertised_browser_version;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'konqueror':
                $apiBro = 'Konqueror';
                break;
            case 'access netfront':
                $apiBro       = 'NetFront';
                $browserMaker = 'Access';
                break;
            case 'nokia':
            case 'nokia browserng':
                $apiBro = 'Nokia Browser';
                break;
            case 'facebook':
                switch (strtolower($apiVer)) {
                    case 'bot':
                        $apiBro = 'FaceBook Bot';
                        $apiVer = null;
                        $apiBot = true;
                        break;
                    default:
                        // nothing to do here
                        break;
                }
                break;
            case 'bing bot':
                $apiBro       = 'BingBot';
                $browserMaker = 'Microsoft';
                $apiBot       = true;
                break;
            case 'bing':
                $browserMaker = 'Microsoft';

                switch (strtolower($apiVer)) {
                    case 'bot':
                        $apiBro = 'BingBot';
                        $apiVer = null;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'google bot':
            case 'facebook bot':
                $apiBot = true;
                break;
            case 'generic web browser':
                $apiBro = null;
                $apiOs  = null;
                $apiMob = null;
                $apiTab = null;
                $apiDev = null;
                $apiMan = null;
                $apiBot = null;
                break;
            case 'robot bot or crawler':
            case 'robot':
                $apiBot = true;
                $apiDev = 'general Bot';
                $apiBro = 'unknown';
                break;
            case 'generic smarttv':
                $apiBot = false;
                $apiDev = 'general TV Device';
                $apiBro = 'unknown';
                break;
            case 'unknown':
                $browserMaker = 'unknown';
                $apiBro       = 'unknown';

                switch (strtolower($apiVer)) {
                    case 'bot or crawler':
                        $apiBot = true;
                        $apiDev = 'general Bot';
                        $apiBro = 'unknown';
                        $apiVer = null;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'wii':
                $apiBot = false;
                $apiDev = 'Wii';
                $apiBro = 'Wii Browser';
                $apiMan = 'Nintendo';
                break;
            case 'android webkit':
            case 'android':
                $apiBro = 'Android Webkit';
                if ('4.01' === $apiVer) {
                    $apiVer = '4.0.1';
                }
                $browserMaker = 'Google';
                break;
            case 'ucweb':
                $apiBro = 'UC Browser';
                break;
            case 'seomoz':
                $browserMaker = 'SEOmoz';

                switch (strtolower($apiVer)) {
                    case 'rogerbot':
                        $apiBro = 'Rogerbot';
                        $apiVer = null;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            case 'java':
                $browserMaker = 'unknown';

                switch (strtolower($apiVer)) {
                    case 'updater':
                        $apiBro       = 'Java Standard Library';
                        $apiVer       = null;
                        $browserMaker = 'Oracle';
                        $apiBot       = true;
                        $apiPhone     = false;
                        $device       = null;
                        break;
                    default:
                        // nothing to do
                        break;
                }
                break;
            default:
                // nothing to do here
                break;
        }

        $apiBro = trim($apiBro);
        if (!$apiBro) {
            $apiBro = null;
            $apiOs  = null;
            $apiMob = null;
            $apiTab = null;
            $apiDev = null;
            $apiMan = null;
            $apiBot = null;

            $apiPhone      = null;
            $marketingName = null;
            $apiTranscoder = null;
        }

        $browserName = $this->mapper->mapBrowserName($apiBro);

        $manufacturer    = null;
        $browserMakerKey = $this->mapper->mapBrowserMaker($browserMaker, $browserName);
        try {
            $manufacturer = (new CompanyLoader($this->cache))->load($browserMakerKey);
        } catch (NotFoundException $e) {
            //$this->logger->info($e);
        }

        $browser = new Browser(
            $browserName,
            $manufacturer,
            $this->mapper->mapBrowserVersion($apiVer, $browserName)
        );

        $deviceName = $this->mapper->mapDeviceName($apiDev);
        $pointing   = null;

        if ($apiBot) {
            $apiPhone = false;
        } else {
            $pointing = $parserResult->pointing_method;
        }

        if (!$apiBro) {
            $apiMob     = null;
            $apiPhone   = null;
            $deviceType = null;
        } else {
            $deviceType = $parserResult->controlcap_form_factor;
        }

        if (!$apiPhone && $deviceType === 'Feature Phone') {
            $deviceType = 'Mobile Device';
        }

        if ($apiPhone && $deviceType === 'Tablet') {
            $deviceType = 'FonePad';
        }

        $deviceManufacturer = null;
        $deviceMakerKey     = $this->mapper->mapDeviceMaker($apiMan, $deviceName);
        try {
            $deviceManufacturer = (new CompanyLoader($this->cache))->load($deviceMakerKey);
        } catch (NotFoundException $e) {
            //$this->logger->info($e);
        }

        $deviceBrand    = null;
        $deviceBrandKey = $this->mapper->mapDeviceBrandName($brandName, $deviceName);
        try {
            $deviceBrand = (new CompanyLoader($this->cache))->load($deviceBrandKey);
        } catch (NotFoundException $e) {
            //$this->logger->info($e);
        }

        $device = new Device(
            $deviceName,
            $this->mapper->mapDeviceMarketingName($marketingName, $deviceName),
            $deviceManufacturer,
            $deviceBrand,
            null,
            null,
            $this->mapper->mapDeviceType($this->cache, $deviceType),
            $pointing
        );

        $os = new Os($this->mapper->mapOsName($apiOs), null);

        $engine = new Engine(null);

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine, (array) $parserResult);
    }

    /**
     * @return null|\UaDataMapper\InputMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}
