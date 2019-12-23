<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUnhandledExceptionInspection */

require_once 'vendor/autoload.php';

$processor = new \Knowgod\WeatherStats\Main();
$processor->readWriteFile('data/input-test.csv', 'data/output-test.csv');
