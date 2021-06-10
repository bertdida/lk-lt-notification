<?php

namespace LTN\Controllers;

use LTN\Models\NotificationConfig;
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

        $configs = $user->notificationConfigs->filter(function (NotificationConfig $config) {
            return $this->isConfigValid($config);
        });

        foreach ($configs as $config) {
            if ($config->test($payload)) {
                $config->send($payload);
            }
        }

        return $response->withJson([
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    private function isConfigValid(NotificationConfig $config): bool
    {
        $frequencies = json_decode($config->frequencies, true);
        $frequencyValues = array_column($frequencies, 'value');
        return in_array('immediately', $frequencyValues);
    }
}
