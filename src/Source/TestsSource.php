<?php

namespace UaComparator\Source;

use BrowscapPHP\Helper\IniLoader;
use Monolog\Logger;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
class TestsSource implements SourceInterface
{
    /**
     * @var string
     */
    private $dir = null;

    /**
     * @param \Monolog\Logger $logger
     * @param int             $limit
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, $limit = 0)
    {
        $paths = [
            'woothee' => [
                'path'   => 'vendor/woothee/woothee-testset/testsets',
                'suffix' => 'yml',
                'mapper' => 'mapWoothee',
            ],
            'whichbrowser' => [
                'path'   => 'vendor/whichbrowser/parser/tests/data',
                'suffix' => 'yml',
                'mapper' => 'mapWhichbrowser',
            ],
            'piwik' => [
                'path'   => 'vendor/piwik/device-detector/Tests/fixtures',
                'suffix' => 'yml',
                'mapper' => 'mapPiwik',
            ],
            'browscap' => [
                'path'   => 'vendor/browscap/browscap/tests/fixtures/issues',
                'suffix' => 'php',
                'mapper' => 'mapBrowscap',
            ],
        ];

        $allAgents = [];

        foreach ($paths as $sourcePath) {
            $path   = $sourcePath['path'];
            $suffix = $sourcePath['suffix'];

            foreach ($this->loadFromPath($path, $suffix) as $dataFile) {
                foreach ($dataFile as $dataRow) {
                    $allAgents[] = $this->{$sourcePath['mapper']}($dataRow);
                }
            }
        }
    }

    /**
     * @param string $path
     * @param string $suffix
     *
     * @return \Generator
     */
    private function loadFromPath($path, $suffix)
    {
        $iterator = new \RecursiveDirectoryIterator($path);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== $suffix) {
                continue;
            }

            $path = $file->getPathname();

            switch ($suffix) {
                case 'php':
                    yield include $path;
                    break;
                case 'yml':
                    $data = \Spyc::YAMLLoad($path);

                    if (!is_array($data)) {
                        continue;
                    }

                    yield $data;
                    break;
                default:
                    continue;
            }
        }
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapWoothee(array $data)
    {
        return $data['target'];
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapWhichbrowser(array $data)
    {
        if (!isset($data['headers']['User-Agent'])) {
            $headers = http_parse_headers($data['headers']);

            if (! isset($headers['User-Agent'])) {
                return '';
            }

            return $headers['User-Agent'];
        }

        return $data['headers']['User-Agent'];
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapPiwik(array $data)
    {
        return $data['user_agent'];
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapBrowscap(array $data)
    {
        return $data[0];
    }
}
