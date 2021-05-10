<?php

namespace LTN\Models;

use LTN\Models\User;
use LTN\Utils\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class Config
{
    private $user = null;

    public function __construct(array $row, User $user)
    {
        $this->user = $user;

        foreach ($row as $key => $value) {
            $this->$key = $value;
        }
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
        } catch (PHPMailerException $e) {
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
        } catch (TwilioException $e) {
            Logger::save($e->getMessage());
        }
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

        $emailTemplate = file_get_contents(ROOT_DIR . "\src\\templates\\{$activityType}\message.html");
        $textTemplate = file_get_contents(ROOT_DIR . "\src\\templates\\{$activityType}\message.txt");

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

    private function truncate($string, $length = 150, $append = "&hellip;"): string
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
