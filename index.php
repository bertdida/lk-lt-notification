<?php
define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

$customErrorHandler = function (Request $request, Throwable $exception) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    return $response
        ->withJson(['error' => $exception->getMessage()])
        ->withStatus($exception->getCode());
};

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
});

$app->run();
