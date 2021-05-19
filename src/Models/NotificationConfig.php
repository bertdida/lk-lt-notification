<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\LiveTrackerFilter;
use LTN\Models\User;
use LTN\Utils\Logger;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class NotificationConfig extends Model
{
    protected $table = 'live_tracker_filter_notification_configs';

    public function liveTrackerFilter()
    {
        return $this->hasOne(LiveTrackerFilter::class, 'id', 'live_tracker_filter_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function test(array $payload): bool
    {
        return true;
    }

    public function send(array $payload): void
    {
        $methods = json_decode($this->methods, true);

        if (empty($methods)) {
            return;
        }

        foreach ($methods as ['value' => $value]) {

            switch ($value) {
                case 'email':
                    $this->sendEmail($payload);
                    break;
                case 'text':
                    $this->sendSMS($payload);
                    break;
                case 'push':
                    $this->sendPush($payload);
                    break;
            }
        }
    }

    private function sendEmail(array $payload): void
    {
        $data = $this->getMessageData($payload);
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['MAIL_PORT'];

            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
            $mail->addAddress($this->user->email, $this->user->name);

            $mail->isHTML(true);
            $mail->Subject = "LeadKlozer: {$data['page']['name']}'s page has new {$data['activity_type']}";
            $mail->Body = $data['email_content'];
            $mail->AltBody = $data['text_content'];
            $mail->send();
        } catch (PHPMailerException $error) {
            Logger::save($mail->ErrorInfo);
        }
    }

    private function sendSMS(array $payload): void
    {
        $client = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
        ['text_content' => $textContent] = $this->getMessageData($payload);

        if (!isset($this->user->phone)) {
            return;
        }

        try {
            $client->messages->create(
                $this->user->phone,
                [
                    'from' => $_ENV['TWILIO_PHONE'],
                    'body' => $textContent,
                ]
            );
        } catch (TwilioException $error) {
            Logger::save($error->getMessage());
        }
    }

    private function sendPush(array $payload): void
    {
        $subscriber = null; // get this from database

        if (is_null($subscriber)) {
            return;
        }

        $subscription = Subscription::create([
            'endpoint' => $subscriber['endpoint'],
            'publicKey' => $subscriber['p256dh'],
            'authToken' => $subscriber['auth'],
        ]);

        $data = $this->getMessageData($payload);
        $payload = [
            'badge' => null,
            'icon' => null,
            'title' => "LeadKlozer: {$data['page']['name']}'s page has new {$data['activity_type']}",
            'message' => $data['text_content'],
            'url' => null,
        ];

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:support@leadklozer.com',
                'publicKey' => $_ENV['VAPID_PUBLIC'],
                'privateKey' => $_ENV['VAPID_PRIVATE'],
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->sendOneNotification($subscription, json_encode($payload));
    }

    private function getMessageData(array $payload): array
    {
        $raw = json_decode($payload['raw'], true);
        $post = json_decode($payload['social_post'], true);
        $page = json_decode($payload['social_page']['raw'], true);

        $engagementFrom = $payload['activity_from'];
        $engagementFromLink = 'https://facebook.com/' . $payload['activity_from_id'];

        $data = [
            '%recipient%' => $this->user->name,
            '%post%' => $this->truncate($post['message'] ?? ''),
            '%post_link%' => $post['permalink_url'] ?? null,
            '%page_name%' => $page['name'],

            '%engagement_from%' => $engagementFrom,
            '%engagement_from_link%' => $engagementFromLink,
            '%activity_type%' => $payload['activity_type'],
        ];

        $activityType = 'reaction';

        if ($payload['activity_type'] === 'message') {
            $activityType = 'message';

            if (array_key_exists('messaging', $raw) && !empty($raw['messaging'])) {
                $data['%message%'] = $this->truncate($raw['messaging'][0]['message']['text']);
            }
        }

        if ($payload['activity_type'] === 'comment') {
            $activityType = 'comment';

            if (array_key_exists('changes', $raw) && !empty($raw['changes'])) {
                $data['%comment%'] = null;

                if (array_key_exists('message', $raw['changes'][0]['value'])) {
                    $data['%comment%'] = $this->truncate($raw['changes'][0]['value']['message']);
                }
            }
        }

        $emailTemplate = file_get_contents(ROOT_DIR . "/src/templates/{$activityType}/message.html");
        $textTemplate = file_get_contents(ROOT_DIR . "/src/templates/{$activityType}/message.txt");

        $emailContent = strtr($emailTemplate, $data);
        $textContent = strtr($textTemplate, $data);

        return [
            'raw' => $raw,
            'post' => $post,
            'page' => $page,
            'values' => $data,
            'email_content' => $emailContent,
            'text_content' => $textContent,
            'activity_type' => $activityType,
        ];
    }

    private function truncate(string $string, int $length = 150, string $append = '...'): string
    {
        $string = trim($string);

        if (strlen($string) > $length) {
            $string = wordwrap($string, $length);
            $string = explode("\n", $string, 2);
            $string = $string[0] . $append;
        }

        return $string;
    }
}
