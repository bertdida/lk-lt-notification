<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';

use Dotenv\Dotenv;
use LTN\Utils\Logger;

$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USERNAME'];
$dbPass = $_ENV['DB_PASSWORD'];

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (\PDOException $error) {
    Logger::save($error->getMessage());
    exit($error->getMessage());
}

$stmt = $pdo->query('SELECT id FROM users WHERE status LIMIT 2');
while ($row = $stmt->fetch()) {
    execInBackground("php index.php --userid={$row['id']} --ishourly");
}

/**
 * Executes $command in the background (no cmd window) without
 * PHP waiting for it to finish, on both Windows and Unix.
 *
 * https://www.php.net/manual/en/function.exec.php#86329
 */
function execInBackground(string $command): void
{
    if (substr(php_uname(), 0, 7) === 'Windows') {
        pclose(popen('start /B ' . $command, 'r'));
    } else {
        exec($command . ' > /dev/null &');
    }
}
