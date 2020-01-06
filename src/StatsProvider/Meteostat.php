<?php


namespace Knowgod\WeatherStats\StatsProvider;


use GuzzleHttp\Client;
use stdClass;

class Meteostat
{
    const API_URL = 'https://api.meteostat.net';

    const API_ENDPOINT_HISTORY_DAILY   = '/v1/history/daily';
    const API_ENDPOINT_STATIONS_NEARBY = '/v1/stations/nearby';

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

    public function __construct(string $apiKey, string $lat, string $long)
    {
        $this->apiKey = $apiKey;
        $this->lat    = $lat;
        $this->long   = $long;
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
    public function getPeriodStatsDaily(string $oldDate, string $newDate): stdClass
    {
        $client   = new Client();
        $response = $client->request('GET',
            self::API_URL . self::API_ENDPOINT_HISTORY_DAILY,
            [
                'query' => [
                    'station' => $this->getStationId(),
                    'start'   => $oldDate,
                    'end'     => $newDate,
                    'key'     => $this->apiKey,
                ],
            ]
        );

        $body = $response->getBody();

        return json_decode($body->getContents(), false);
    }

    /**
     * @return string
     */
    private function getStationId(): string
    {
        $client   = new Client();
        $response = $client->request('GET',
            self::API_URL . self::API_ENDPOINT_STATIONS_NEARBY,
            [
                'query' => [
                    'lat'   => $this->lat,
                    'lon'   => $this->long,
                    'limit' => 5,
                    'key'   => $this->apiKey,
                ],
            ]
        );

        $body = $response->getBody();

        return json_decode($body->getContents(), false);
    }
}
