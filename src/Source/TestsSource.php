<?php

namespace UaComparator\Source;

use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
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
                    case 'woothee':
                        $agentsFromFile = $this->mapWoothee($dataFile);
                        break;
                    default:
                        continue;
                }

                $output->writeln(' [added ' . str_pad(number_format(count($allAgents)), 12, ' ', STR_PAD_LEFT) . ' agent' . (count($allAgents) !== 1 ? 's' : '') . ' so far]');

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
}
