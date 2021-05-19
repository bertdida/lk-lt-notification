<?php

namespace LTN\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PingController
{
    public function get(Request $request, Response $response)
    {
        return $response->withJson([
            'status' => '👌',
        ]);
    }
}
