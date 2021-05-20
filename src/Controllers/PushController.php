<?php

namespace LTN\Controllers;

use LTN\Models\PushSubscriber;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class PushController
{
    public function post(Request $request, Response $response)
    {
        $payload = $request->getParsedBody();

        switch ($payload['action']) {
            case 'subscribe':
                $this->subscribe($payload);
                break;

            case 'unsubscribe':
                $this->unsubscribe($request, $payload);
                break;

            default:
                return $response->withJson([
                    'data' => [
                        'error' => 'Unhandled action',
                    ],
                ], 422);
                break;
        }

        return $response->withJson([
            'data' => [
                'status' => '👌',
            ],
        ]);
    }

    private function subscribe(array $payload): PushSubscriber
    {
        return PushSubscriber::updateOrCreate(
            [
                'endpoint' => $payload['endpoint'],
            ],
            [
                'auth' => $payload['auth'],
                'p256dh' => $payload['p256dh'],
                'lk_user_id' => $payload['user_id'],
            ]
        );
    }

    private function unsubscribe(Request $request, array $payload): void
    {
        $pushSubscriber = PushSubscriber::where(['endpoint' => $payload['endpoint']])->first();

        if (!$pushSubscriber) {
            throw new HttpNotFoundException($request, 'Resource not found.');
        }

        $pushSubscriber->delete();
    }
}
