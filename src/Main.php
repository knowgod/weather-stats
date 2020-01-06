<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */
/**
 * @author    Arkadij Kuzhel <arkadij@madepeople.se>
 * @created   21.12.19
 */
declare(strict_types=1);

namespace Knowgod\WeatherStats;

use stdClass;

/**
 * Class Main
 *
 */
class Main
{
    const STAT_TEMPERATURE = 'temperature';

    /**
     * @var resource
     */
    private $fpInput;

    /**
     * @var resource
     */
    private $fpOutput;

    /**
     * @var array
     */
    private $outputHeaders;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $lat;

    /**
     * @var string
     */
    private $long;

    /**
     * @var \Knowgod\WeatherStats\StatsProvider\Meteostat
     */
    private $statsProvider;

    /**
     * @param string $apiKey
     * @param string $lat
     * @param string $long
     */
    public function __construct(string $apiKey, string $lat, string $long)
    {
        $this->apiKey = $apiKey;
        $this->lat    = $lat;
        $this->long   = $long;
    }

    /**
     * @param $inputFile
     * @param $outputFile
     *
     * @throws \Exception
     */
    public function readWriteFile($inputFile, $outputFile)
    {
        $this->prepareFiles($inputFile, $outputFile);
        $oldDate = false;
        while ($newDate = $this->readDate()) {
            if (!$oldDate) {
                $oldDate = $newDate;
                continue;
            }
            $statsForPeriod = $this->readStats($oldDate, $newDate);
            $statsAverage   = $this->getAverages($statsForPeriod);
            $this->writeOutput($oldDate, $newDate, $statsAverage);
            $oldDate = $newDate;
        }
        $this->finalizeFiles();
    }

    /**
     * @param $inputFile
     * @param $outputFile
     *
     * @throws \Exception
     */
    private function prepareFiles($inputFile, $outputFile)
    {
        $this->fpInput  = fopen($inputFile, 'rb');
        $this->fpOutput = fopen($outputFile, 'wb+');
        if (!($this->fpInput && $this->fpOutput)) {
            /** @noinspection ThrowRawExceptionInspection */
            throw new \Exception('Input file should be readable, output file should be writable');
        }
    }

    /**
     * @return string|false
     */
    private function readDate()
    {
        $row = fgetcsv($this->fpInput);
        if (!$row) {
            return false;
        }
        $date = \DateTime::createFromFormat('d/m/y', $row[0]);

        return $date->format('Y-m-d');
    }

    /**
     * @param string $oldDate
     * @param string $newDate
     *
     * @return stdClass
     */
    private function readStats(string $oldDate, string $newDate): stdClass
    {
        $statsProvider = $this->getStatsProvider();

        return $statsProvider->getPeriodStatsDaily($oldDate, $newDate);
    }

    /**
     * @param stdClass $body
     *
     * @return array
     */
    private function getAverages(stdClass $body): array
    {
        $stats = [];
        foreach ($body->data as $datum) {
            $stats[ self::STAT_TEMPERATURE ][] = $datum->temperature;
        }

        $averages = [];
        foreach ($stats as $key => $aResults) {
            $averages[ $key ] = array_sum($aResults) / count($aResults);
        }

        return $averages;
    }

    /**
     * @param string $oldDate
     * @param string $newDate
     * @param array  $statsAverage
     *
     * @throws \Exception
     */
    private function writeOutput(string $oldDate, string $newDate, array $statsAverage)
    {
        $oldDT = new \DateTime($oldDate);
        $newDT = new \DateTime($newDate);

        $row = array_merge([
            'from' => $oldDate,
            'to'   => $newDate,
            'days' => $oldDT->diff($newDT)->format('%R%a days'),
        ],
            $statsAverage
        );

        $isHeadersWritten = (bool) $this->outputHeaders;
        foreach ($row as $key => $value) {
            if (!$isHeadersWritten) {
                $this->outputHeaders[ $key ] = $key;
            }
            $row[ $key ] = $value;
        }

        if (!$isHeadersWritten) {
            fputcsv($this->fpOutput, $this->outputHeaders);
        }
        fputcsv($this->fpOutput, $row);
    }

    private function finalizeFiles()
    {
        fclose($this->fpInput);
        fclose($this->fpOutput);
    }

    /**
     * @return StatsProvider\Meteostat
     */
    private function getStatsProvider(): StatsProvider\Meteostat
    {
        if (!$this->statsProvider) {
            $this->statsProvider = new \Knowgod\WeatherStats\StatsProvider\Meteostat(
                $this->apiKey,
                $this->lat,
                $this->long
            );
        }

        return $this->statsProvider;
    }
}
