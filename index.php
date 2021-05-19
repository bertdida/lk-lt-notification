<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/utils.php';

use DI\Container;
use Dotenv\Dotenv;
use LTN\Controllers\NotificationController;
use LTN\Controllers\PingController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Tuupola\Middleware\CorsMiddleware;

$dotenv = Dotenv::createImmutable(ROOT_DIR);
$dotenv->load();

$container = new Container();

$container->set('lkdb', function () {
    return getDbConnection([
        'host' => $_ENV['LK_DB_HOST'],
        'database' => $_ENV['LK_DB_NAME'],
        'username' => $_ENV['LK_DB_USERNAME'],
        'password' => $_ENV['LK_DB_PASSWORD'],
    ], 'lk');
});

$container->set('localdb', function () {
    return getDbConnection([
        'host' => 'db',
        'database' => $_ENV['DB_NAME'],
        'username' => $_ENV['DB_USERNAME'],
        'password' => $_ENV['DB_PASSWORD'],
    ], 'local');
});

$container->set(NotificationController::class, function ($container) {
    return new NotificationController($container->get('lkdb'));
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(new CorsMiddleware([
    'origin' => ['*'],
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
$app->post('/notification', NotificationController::class . ':post');

$app->run();
