<?php

namespace LTN\Models;

use LTN\Models\User;
use LTN\Utils\Logger;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

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
            }
        }
    }

    private function sendEmail(array $payload): void
    {
        $post = json_decode($payload['social_post'], true);
        $page = json_decode($payload['social_page']['raw'], true);

        $data = [
            '%recipient%' => $this->user->name,
            '%reactor%' => $payload['activity_from'],
            '%reactor_link%' => 'https://facebook.com/' . $payload['activity_from_id'],
            '%reaction%' => $payload['activity_type'],
            '%post%' => $this->truncate($post['message'], 150),
            '%post_link%' => $post['permalink_url'],
            '%page_name%' => $page['name'],
        ];

        $emailTemplate = file_get_contents(ROOT_DIR . '\src\templates\reactions\message.html');
        $textTemplate = file_get_contents(ROOT_DIR . '\src\templates\reactions\message.txt');

        $emailContent = strtr($emailTemplate, $data);
        $textContent = strtr($textTemplate, $data);

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
            $mail->Subject = "LeadKlozer: {$page['name']}'s page has new reaction";
            $mail->Body = $emailContent;
            $mail->AltBody = $textContent;
            $mail->send();
        } catch (Exception $e) {
            Logger::save($mail->ErrorInfo);
        }
    }

    private function truncate($string, $length = 100, $append = "&hellip;"): string
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
