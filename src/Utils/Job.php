<?php

namespace LTN\Utils;

use Carbon\Carbon;
use LTN\Models\Contact;
use LTN\Models\Engagements;
use LTN\Models\NotificationConfig;
use LTN\Models\User;
use LTN\Utils\Logger;

class Job
{
    private $user;

    private $isHourly;

    public function __construct(int $userId, bool $isHourly = false)
    {
        $this->user = User::find($userId);
        $this->isHourly = $isHourly;
    }

    public function run()
    {
        if (is_null($this->user)) {
            return;
        }

        $configs = $this->user->notificationConfigs;
        if (empty($configs)) {
            return;
        }

        $subHours = $this->isHourly ? 1 : 24;
        $engagements = Engagements::where('user_id', $this->user->id)
            ->where('timestampint', '>=', Carbon::now()->subHours($subHours)->timestamp)
            ->where('activity_type', '!=', 'manual_entry')
            ->get()
            ->toArray();

        $contactIds = array_unique(array_column($engagements, 'activity_from_id'));
        $contacts = Contact::where('user_id', $this->user->id)
            ->whereIn('provider_user_id', $contactIds)
            ->get()
            ->all();

        NotificationConfig::setCachedContacts($contacts);

        foreach ($configs as $config) {
            $configEngagements = array_filter($engagements, function ($engagement) use ($config) {
                return $config->test($engagement);
            });

            if (empty($configEngagements)) {
                continue;
            }

            Logger::save(json_encode($this->getSummary($engagements)));
        }
    }

    private function getSummary(array $engagements): array
    {
        return array_reduce($engagements, function (array $carry, array $engagement) {
            $page = json_decode($engagement['social_page'], true);
            $pageName = $page['name'];

            if (!array_key_exists($pageName, $carry)) {
                $carry[$pageName] = [];
            }

            if (!array_key_exists($engagement['activity_type'], $carry[$pageName])) {
                $carry[$pageName][$engagement['activity_type']] = 0;
            }

            $carry[$pageName][$engagement['activity_type']] += 1;
            return $carry;
        }, []);
    }
}
