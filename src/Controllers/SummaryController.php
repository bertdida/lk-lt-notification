<?php

namespace LTN\Controllers;

use LTN\Utils\Cron;
use LTN\Utils\Db;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SummaryController
{
    public function get(Request $request, Response $response)
    {
        $params = $request->getParams();

        if (!array_key_exists('user_id', $params)) {
            return $response->withJson([
                'data' => [
                    'error' => 'Invalid request data.',
                ],
            ], 422);
        }

        $dbConfig = [
            'host' => $_ENV['DB_HOST'],
            'name' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USERNAME'],
            'pass' => $_ENV['DB_PASSWORD'],
        ];

        try {
            $db = new Db($dbConfig);
        } catch (\PDOException $error) {
            return $response->withJson([
                'data' => [
                    'error' => $error->getMessage(),
                ],
            ], 500);
        }

        $isHourly = false;
        if (array_key_exists('is_hourly', $params)) {
            $isHourly = filter_var($params['is_hourly'], FILTER_VALIDATE_BOOLEAN);
        }

        $cron = new Cron($db, $isHourly);
        $cron->execute($params['user_id']);

        return $response->withJson([
            'data' => '👌',
        ]);
    }
}
