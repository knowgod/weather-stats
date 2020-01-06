<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUnhandledExceptionInspection */

require_once 'vendor/autoload.php';

/**
 * @todo Read API initialization from .env
 */
$processor = new \Knowgod\WeatherStats\Main('YOUR_API_KEY_HERE', '50.56139', '30.652427');
$processor->readWriteFile('data/input-test.csv', 'data/output-test.csv');
