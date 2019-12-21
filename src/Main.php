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
     * API documentation at:
     * {@link https://api.meteostat.net/}
     *
     * @param string $oldDate
     * @param string $newDate
     *
     * @return stdClass
     */
    private function readStats(string $oldDate, string $newDate): stdClass
    {
        $apiKey    = 'YOUR API KEY HERE';
        $url       = 'https://api.meteostat.net';
        $path      = '/v1/history/daily';
        $stationId = '33345';
        $query     = [
            'station' => $stationId,
            'start'   => $oldDate,
            'end'     => $newDate,
            'key'     => $apiKey,
        ];

        $client   = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url . '/' . $path, ['query' => $query]);

        $body = $response->getBody();

        return json_decode($body->getContents(), false);
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
}
