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
            'uap-core' => [
                'path'   => 'vendor/thadafinser/uap-core/tests',
                'suffix' => 'yaml',
                'mapper' => 'mapUapCore',
            ],
        ];

        $allAgents = [];

        foreach ($paths as $sourcePath) {
            if ($limit && count($allAgents) >= $limit) {
                continue;
            }

            $path   = $sourcePath['path'];
            $suffix = $sourcePath['suffix'];

            foreach ($this->loadFromPath($path, $suffix) as $dataFile) {
                if ($limit && count($allAgents) >= $limit) {
                    break;
                }

                $allAgents = array_merge($allAgents, $this->{$sourcePath['mapper']}($dataFile));
                $allAgents = array_unique($allAgents);
            }
        }

        $i = 0;
        foreach ($allAgents as $agent) {
            if ($limit && $i >= $limit) {
                return;
            }

            ++$i;
            yield $agent;
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
        if (!file_exists($path)) {
            return;
        }
var_dump('reading ' . $path . ' ...');
        $iterator = new \RecursiveDirectoryIterator($path);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== $suffix) {
                continue;
            }

            $filepath = $file->getPathname();

            var_dump('reading ' . $filepath . ' ...');
            switch ($suffix) {
                case 'php':
                    yield include $filepath;
                    break;
                case 'yml':
                case 'yaml':
                    $data = \Spyc::YAMLLoad($filepath);

                    if (!is_array($data)) {
                        continue;
                    }

                    yield $data;
                    break;
                default:
                    // do nothing here
                    break;
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
        $allData = [];

        foreach ($data as $row) {
            if (empty($row['target'])) {
                continue;
            }

            $allData[] = $row['target'];
        }

        return $allData;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapWhichbrowser(array $data)
    {
        $allData = [];

        foreach ($data as $row) {
            if (!isset($row['headers']['User-Agent'])) {
                $headers = http_parse_headers($row['headers']);

                if (! isset($headers['User-Agent'])) {
                    continue;
                }

                $allData[] = $headers['User-Agent'];
                continue;
            }

            $allData[] = $row['headers']['User-Agent'];
        }

        return $allData;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapPiwik(array $data)
    {
        $allData = [];

        foreach ($data as $row) {
            if (empty($row['user_agent'])) {
                continue;
            }

            $allData[] = $row['user_agent'];
        }

        return $allData;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapBrowscap(array $data)
    {
        $allData = [];

        foreach ($data as $row) {
            if (empty($row[0])) {
                continue;
            }

            $allData[] = $row[0];
        }

        return $allData;
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapUapCore(array $data)
    {
        if (empty($data['test_cases'])) {
            return [];
        }

        $allData = [];

        foreach ($data['test_cases'] as $row) {
            if (empty($row['user_agent_string'])) {
                continue;
            }

            $allData[] = $row['user_agent_string'];
        }

        return $allData;
    }
}
