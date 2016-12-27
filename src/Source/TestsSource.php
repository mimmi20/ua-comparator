<?php

namespace UaComparator\Source;

use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
class TestsSource implements SourceInterface
{
    /**
     * @param \Monolog\Logger                                   $logger
     * @param int                                               $limit
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, $limit, OutputInterface $output)
    {
        $paths = [
            //'woothee' => [
            //    'path'   => 'vendor/woothee/woothee-testset/testsets',
            //    'suffix' => 'yaml',
            //],
            //'whichbrowser' => [
            //    'path'   => 'vendor/whichbrowser/parser/tests/data',
            //    'suffix' => 'yaml',
            //],
            'piwik' => [
                'path'   => 'vendor/piwik/device-detector/Tests/fixtures',
                'suffix' => 'yml',
            ],
            'browscap' => [
                'path'   => 'vendor/browscap/browscap/tests/fixtures/issues',
                'suffix' => 'php',
            ],
            'uap-core' => [
                'path'   => 'vendor/thadafinser/uap-core/tests',
                'suffix' => 'yaml',
            ],
            'browser-detector' => [
                'path'   => 'vendor/mimmi20/browser-detector/tests/issues',
                'suffix' => 'json',
            ],
        ];

        $allAgents = [];

        foreach ($paths as $library => $sourcePath) {
            if ($limit && count($allAgents) >= $limit) {
                continue;
            }

            $path   = $sourcePath['path'];
            $suffix = $sourcePath['suffix'];

            foreach ($this->loadFromPath($path, $suffix, $output) as $dataFile) {
                if ($limit && count($allAgents) >= $limit) {
                    break;
                }

                $agentsFromFile = [];

                switch ($library) {
                    case 'uap-core':
                        $agentsFromFile = $this->mapUapCore($dataFile);
                        break;
                    case 'browscap':
                        $agentsFromFile = $this->mapBrowscap($dataFile);
                        break;
                    case 'piwik':
                        $agentsFromFile = $this->mapPiwik($dataFile);
                        break;
                    case 'whichbrowser':
                        $agentsFromFile = $this->mapWhichbrowser($dataFile);
                        break;
                    case 'woothee':
                        $agentsFromFile = $this->mapWoothee($dataFile);
                        break;
                    case 'browser-detector':
                        $agentsFromFile = $this->mapBrowserDetector($dataFile);
                        break;
                    default:
                        continue;
                }

                $output->writeln(' [added ' . str_pad(number_format(count($allAgents)), 12, ' ', STR_PAD_LEFT) . ' agent' . (count($allAgents) <> 1 ? 's' : '') . ' so far]');

                $newAgents = array_diff($agentsFromFile, $allAgents);
                $allAgents = array_merge($allAgents, $newAgents);
                //$allAgents = array_unique($allAgents);
            }
        }

        $i = 0;
        foreach ($allAgents as $agent) {
            if ($limit && $i >= $limit) {
                return null;
            }

            ++$i;
            yield $agent;
        }
    }

    /**
     * @param string                                            $path
     * @param string                                            $suffix
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    private function loadFromPath($path, $suffix, OutputInterface $output = null)
    {
        if (!file_exists($path)) {
            return null;
        }

        $output->writeln('    reading path ' . $path);

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

            $output->write('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT), false);
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
                case 'json':
                    yield (array) json_decode(file_get_contents($filepath));
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
            if (empty($row['ua'])) {
                continue;
            }

            $allData[] = $row['ua'];
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

    /**
     * @param array $data
     *
     * @return string
     */
    private function mapBrowserDetector(array $data)
    {
        $allData = [];

        foreach ($data as $row) {
            if (empty($row->ua)) {
                continue;
            }

            $allData[] = $row->ua;
        }

        return $allData;
    }
}
