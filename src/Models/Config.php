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

        $raw = json_decode($payload['raw'], true);
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
            $mail->Subject = 'Here is the subject';
            $mail->Body = 'This is the HTML message body <b>in bold!</b>';
            $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $error) {
            Logger::save($mail->ErrorInfo);
        }
    }
}
