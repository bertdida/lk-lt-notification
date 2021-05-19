<?php

namespace LTN\Controllers;

use LTN\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class NotificationController
{
    public function post(Request $request, Response $response)
    {
        $payload = $request->getParsedBody();

        if (!array_key_exists('user_id', $payload)) {
            return $response->withJson([
                'data' => [
                    'error' => 'Invalid post data.',
                ],
            ], 422);
        }

        $user = User::where('id', $payload['user_id'])->first();

        if (is_null($user)) {
            return $response->withJson([
                'data' => [
                    'error' => 'User not found',
                ],
            ], 404);
        }

        foreach ($user->notificationConfigs as $config) {
            if ($config->test($payload)) {
                $config->send($payload);
            }
        }

        return $response->withJson([
            'data' => [
                'user' => $user->notificationConfigs,
            ],
        ]);
    }
}
