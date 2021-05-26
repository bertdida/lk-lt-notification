<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';

use DI\Container;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use LTN\Controllers\NotificationController;
use LTN\Controllers\PingController;
use LTN\Controllers\PushController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Tuupola\Middleware\CorsMiddleware;

$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

$dbConfig = [
    'default' => [
        'host' => $_ENV['LK_DB_HOST'],
        'database' => $_ENV['LK_DB_NAME'],
        'username' => $_ENV['LK_DB_USERNAME'],
        'password' => $_ENV['LK_DB_PASSWORD'],
    ],
    'local' => [
        'host' => getenv('docker') !== false ? 'db' : 'localhost',
        'database' => $_ENV['DB_NAME'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
    ],
];

$capsule = new Capsule;
foreach ($dbConfig as $name => $config) {
    $capsule->addConnection(array_merge([
        'driver' => 'mysql',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => '',
    ], $config), $name);
}

$capsule->setAsGlobal();
$capsule->bootEloquent();

$container = new Container;
$container->set('db', function ($capsule) {
    return $capsule;
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(new CorsMiddleware([
    'origin' => [
        'http://localhost:3000',
        'https://live.leadklozer.com',
    ],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'headers.allow' => ['Accept', 'Content-Type'],
    'headers.expose' => [],
    'credentials' => false,
    'cache' => 0,
]));

$customErrorHandler = function (Request $request, Throwable $error) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    return $response->withJson(['error' => $error->getMessage()]);
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->get('/ping', PingController::class . ':get');
$app->group('/api', function (RouteCollectorProxy $group) {
    $group->post('/notification', NotificationController::class . ':post');
    $group->post('/push', PushController::class . ':post');
});

$app->run();
