<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';

use LTN\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Tuupola\Middleware\CorsMiddleware;

$dbConn = new \mysqli($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
$app = AppFactory::create();

$app->add(new CorsMiddleware([
    'origin' => ['*'],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'headers.allow' => ['Accept', 'Content-Type'],
    'headers.expose' => [],
    'credentials' => false,
    'cache' => 0,
    'logger' => $container['logger'],
]));

$customErrorHandler = function (Request $request, Throwable $exception) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    return $response
        ->withJson(['error' => $exception->getMessage()])
        ->withStatus($exception->getCode() === 0 ? 500 : $exception->getCode());
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
});

$app->post('/service', function (Request $request, Response $response) use ($dbConn) {
    $payload = $request->getParsedBody();
    $user = new User($dbConn, $payload['user_id']);

    if (!property_exists($user, 'id')) {
        return $response->withJson(['error' => 'User not found', 'success' => false], 404);
    }

    $configs = $user->getConfigs();
    foreach ($configs as $config) {
        if ($config->test($payload)) {
            // $config->send($payload);
        }
    }

    return $response->withJson(['success' => true]);
});

$app->run();
