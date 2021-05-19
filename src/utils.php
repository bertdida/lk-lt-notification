<?php

use Illuminate\Database\Capsule\Manager as Capsule;

function getDbConnection(array $config): Capsule
{
    $capsule = new Capsule;
    $capsule->addConnection(array_merge([
        'driver' => 'mysql',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ], $config));

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
}
