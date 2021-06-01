<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';

use DI\Container;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use LTN\Controllers\NotificationController;
use LTN\Controllers\PingController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Tuupola\Middleware\CorsMiddleware;

$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

$capsule = new Capsule;
$capsule->addConnection([
    'host' => $_ENV['DB_HOST'],
    'database' => $_ENV['DB_NAME'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'driver' => 'mysql',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

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
});

if (php_sapi_name() !== 'cli') {
    $app->run();
}

// Registers cli
require_once ROOT_DIR . '/src/cli.php';
