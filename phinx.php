<?php
define('ROOT_DIR', __DIR__);
require_once './vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

return
    [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => getenv('docker') !== false ? 'db' : 'localhost',
            'name' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USERNAME'],
            'pass' => $_ENV['DB_PASSWORD'],
            'port' => '3306',
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
