<?php

use Illuminate\Database\Capsule\Manager as Capsule;

function getDbConnection(array $config, $name = null): Capsule
{
    $capsule = new Capsule;
    $dbConfig = array_merge([
        'driver' => 'mysql',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ], $config);

    $capsule->addConnection($dbConfig, $name);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
}
