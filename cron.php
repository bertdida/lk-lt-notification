<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GetOpt\GetOpt;
use GetOpt\Option;
use LTN\Utils\Cron;
use LTN\Utils\Db;
use LTN\Utils\Logger;

$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

$getOpt = new GetOpt();
$getOpt->addOptions([
    Option::create(null, 'ishourly', GetOpt::NO_ARGUMENT)
        ->setDescription('If set hourly engagements will be summarized, otherwise daily'),
]);

$dbConfig = [
    'host' => $_ENV['DB_HOST'],
    'name' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USERNAME'],
    'pass' => $_ENV['DB_PASSWORD'],
];

try {
    $db = new Db($dbConfig);
} catch (\PDOException $error) {
    Logger::save($error->getMessage());
    exit($error->getMessage());
}

$getOpt->process();
$isHourly = $getOpt->getOption('ishourly') !== null;
$cron = new Cron($db, $isHourly);
$cron->execute();
