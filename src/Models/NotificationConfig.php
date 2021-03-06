<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\Engagements;
use LTN\Models\LiveTrackerFilter;
use LTN\Models\PushSubscriber;
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

    private static $cachedContacts = [];

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
        // checks engagement type
        if (!in_array($payload['activity_type'], $this->getEngagementTypes())) {
            return false;
        }

        // checks comment keywords
        if ($payload['activity_type'] === 'comment' && !empty($this->getCommentKeywords())) {
            $isFound = false;
            ['values' => $values] = $this->getMessageData($payload);
            ['%full_comment%' => $comment] = $values;

            foreach ($this->getCommentKeywords() as $keyword) {
                if ($this->stringContains($comment, $keyword)) {
                    $isFound = true;
                    break;
                }
            }

            if (!$isFound) {
                return false;
            }
        }

        $contact = $this->getContact($payload);

        // checks contact status
        if (!$contact || !in_array($contact->status, $this->getContactStatuses())) {
            return false;
        }

        // checks direct communication
        if ($this->liveTrackerFilter->is_direct_communication) {
            if (!$contact->last_message && !$contact->last_comment) {
                $contactInfo = $contact->info()
                    ->whereIn('label', ['email', 'phone'])
                    ->whereNotNull('value')
                    ->first();

                if (!$contactInfo) {
                    return false;
                }
            }
        }

        // checks for contact type
        $contactTypes = $this->liveTrackerFilter->contactTypes;
        if (!is_null($contact->type) && !in_array($contact->type->id, $contactTypes->pluck('id')->toArray())) {
            return false;
        }

        // if contact's type is null, the live tracker filter's contact
        // types must have the default contact type for all contacts - which
        // the id is defined as $defaultContactTypeId
        $defaultContactTypeId = 1;
        if (!$contactTypes->where('default_stage_type_id', $defaultContactTypeId)->first()) {
            return false;
        }

        // TODO: check for posts or ads
        // TODO: check for posts ads messages

        return true;
    }

    public function send(array $payload): void
    {
        $data = $this->getMessageData($payload);
        $message['subject'] = "LeadKlozer: {$data['page']['name']}'s page has new {$data['activity_type']}";
        $message['email_content'] = $data['email_content'];
        $message['text_content'] = $data['text_content'];

        $this->sendMessage($message);
    }

    public function sendMessage(array $message): void
    {
        $methods = json_decode($this->methods, true);

        foreach ($methods as ['value' => $value]) {
            switch ($value) {
                case 'email':
                    $this->sendEmail($message);
                    break;
                case 'text':
                    $this->sendSMS($message);
                    break;
                case 'push':
                    $this->sendPush($message);
                    break;
            }
        }
    }

    public static function setCachedContacts(array $contacts): void
    {
        foreach ($contacts as $contact) {
            if (!$contact instanceof Contact) {
                throw new \ValueError;
            }
        }

        self::$cachedContacts = array_reduce($contacts, function (array $carry, Contact $contact): array{
            $carry[$contact->provider_user_id] = $contact;
            return $carry;
        }, []);
    }

    private function sendEmail(array $message): void
    {
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
            $mail->Subject = $message['subject'];
            $mail->Body = $message['email_content'];
            $mail->AltBody = $message['text_content'];
            $mail->send();
        } catch (PHPMailerException $error) {
            Logger::save($mail->ErrorInfo);
        }
    }

    private function sendSMS(array $message): void
    {
        $client = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);

        if (!isset($this->user->phone)) {
            return;
        }

        try {
            $client->messages->create(
                $this->user->phone,
                [
                    'from' => $_ENV['TWILIO_PHONE'],
                    'body' => $message['text_content'],
                ]
            );
        } catch (TwilioException $error) {
            Logger::save($error->getMessage());
        }
    }

    private function sendPush(array $message): void
    {
        $subscribers = PushSubscriber::where('user_id', $this->user->id)
            ->get()
            ->toArray();

        if (empty($subscribers)) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:support@leadklozer.com',
                'publicKey' => $_ENV['VAPID_PUBLIC'],
                'privateKey' => $_ENV['VAPID_PRIVATE'],
            ],
        ]);

        $notificationPayload = [
            'title' => $message['subject'],
            'message' => $message['text_content'],
            'url' => null,
        ];

        foreach ($subscribers as $subscriber) {
            $subscription = Subscription::create([
                'endpoint' => $subscriber['endpoint'],
                'publicKey' => $subscriber['p256dh'],
                'authToken' => $subscriber['auth'],
            ]);

            $webPush->sendOneNotification($subscription, json_encode($notificationPayload));
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

        if ($payload['activity_type'] === 'comment') {
            $activityType = 'comment';

            if (array_key_exists('changes', $raw) && !empty($raw['changes'])) {
                $data['%comment%'] = null;

                if (array_key_exists('message', $raw['changes'][0]['value'])) {
                    $comment = $raw['changes'][0]['value']['message'];
                    $data['%full_comment%'] = $comment;
                    $data['%comment%'] = $this->truncate($comment);
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

    private function getContact(array $payload): ?Contact
    {
        $providerId = $payload['activity_from_id'];

        if (!array_key_exists($providerId, self::$cachedContacts)) {
            $contact = Contact::where('user_id', $payload['user_id'])
                ->where('provider_user_id', $providerId)
                ->first();

            self::$cachedContacts[$providerId] = $contact;
        }

        return self::$cachedContacts[$providerId];
    }

    private function getContactStatuses(): array
    {
        $contactStatuses = json_decode($this->liveTrackerFilter->contact_status, true);
        return array_reduce($contactStatuses['items'], function ($carry, $status) {
            $value = strtolower($status['text']);

            if (in_array($value, $carry) || !$status['isSelected']) {
                return $carry;
            }

            array_push($carry, $value);
            return $carry;
        }, []);
    }

    private function getEngagementTypes(): array
    {
        $engagements = json_decode($this->liveTrackerFilter->engagements, true);
        return array_reduce($engagements['items'], function ($carry, $engagement) {
            ['isSelected' => $isSelected, 'text' => $text] = $engagement;

            if (!$isSelected) {
                return $carry;
            }

            if ($this->stringContains($text, 'reactions')) {
                $carry = array_merge($carry, Engagements::$facebookReactions);
            }

            if ($this->stringContains($text, 'inbox')) {
                array_push($carry, 'message');
            }

            if ($this->stringContains($text, 'lead')) {
                array_push($carry, 'lead');
            }

            if ($this->stringContains($text, 'comments')) {
                array_push($carry, 'comment');
            }

            return $carry;
        }, []);
    }

    private function stringContains($haystack, $needle)
    {
        return (strpos(strtolower($haystack), strtolower($needle)) !== false);
    }

    private function getCommentKeywords(): array
    {
        $keywords = json_decode($this->liveTrackerFilter->keywords, true);
        return array_reduce($keywords['items'], function ($carry, $keyword) {
            if (!$keyword['isSelected']) {
                return $carry;
            }

            array_push($carry, $keyword['text']);
            return $carry;
        }, []);
    }
}
