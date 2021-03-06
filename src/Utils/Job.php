<?php

namespace LTN\Utils;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;
use LTN\Models\Contact;
use LTN\Models\Engagements;
use LTN\Models\NotificationConfig;
use LTN\Models\User;

class Job
{
    private $user;

    private $isHourly;

    private $capsule;

    public function __construct(Capsule $capsule, int $userId, bool $isHourly = false)
    {
        $this->capsule = $capsule;
        $this->user = User::find($userId);
        $this->isHourly = $isHourly;
    }

    public function run()
    {
        if (!$this->user instanceof User) {
            return;
        }

        $connectionName = $this->getConnectionName();

        $configs = NotificationConfig::on($connectionName)->where('user_id', $this->user->id)->get();
        $configs = $configs->filter(function (NotificationConfig $config) {
            return $this->isConfigValid($config);
        });

        if (empty($configs)) {
            return;
        }

        $subHours = $this->isHourly ? 1 : 24;
        $engagements = Engagements::on($connectionName)
            ->where('user_id', $this->user->id)
            ->where('timestampint', '>=', Carbon::now()->subHours($subHours)->timestamp)
            ->where('activity_type', '!=', 'manual_entry')
            ->get()
            ->toArray();

        if (empty($engagements)) {
            return;
        }

        $contactIds = array_unique(array_column($engagements, 'activity_from_id'));
        $contacts = Contact::on($connectionName)
            ->where('user_id', $this->user->id)
            ->whereIn('provider_user_id', $contactIds)
            ->get()
            ->all();

        NotificationConfig::setCachedContacts($contacts);

        foreach ($configs as $config) {
            if (!$this->isConfigValid($config)) {
                continue;
            }

            $configEngagements = array_filter($engagements, function ($engagement) use ($config) {
                return $config->test($engagement);
            });

            if (empty($configEngagements)) {
                continue;
            }

            $summary = $this->getSummary($engagements);
            $data = [
                '%recipient%' => $this->user->name,
                '%summaries%' => $this->summaryToString($summary),
                '%summaries_html%' => $this->summaryToString($summary, true),
                '%frequency%' => $this->isHourly ? 'hourly' : 'daily',
            ];

            $emailTemplate = file_get_contents(ROOT_DIR . '/src/templates/summary/message.html');
            $textTemplate = file_get_contents(ROOT_DIR . '/src/templates/summary/message.txt');

            $message['subject'] = "LeadKlozer: {$data['%frequency%']} summaries";
            $message['email_content'] = strtr($emailTemplate, $data);
            $message['text_content'] = strtr($textTemplate, $data);

            $config->sendMessage($message);
        }
    }

    private function getConnectionName(string $default = 'default'): string
    {
        $dbName = $this->user->db_name;

        if (empty($dbName)) {
            return $default;
        }

        $query = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?';
        $db = Capsule::select($query, [$dbName]);

        if (empty($db)) { // database doesn't exists
            return $default;
        }

        $this->capsule->addConnection([
            'host' => $_ENV['DB_HOST'],
            'database' => $dbName,
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'driver' => 'mysql',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ], $dbName);

        return $dbName;
    }

    private function isConfigValid(NotificationConfig $config): bool
    {
        $frequencies = json_decode($config->frequencies, true);
        $frequencyValues = array_column($frequencies, 'value');
        return in_array($this->isHourly ? 'hourly' : 'daily', $frequencyValues);
    }

    private function getSummary(array $engagements): array
    {
        $summary = array_reduce($engagements, function (array $carry, array $engagement) {
            $page = json_decode($engagement['social_page'], true);
            $pageName = $page['name'];

            if (!array_key_exists($pageName, $carry)) {
                $carry[$pageName] = [];
            }

            if (in_array($engagement['activity_type'], Engagements::$facebookReactions)) {
                $engagement['activity_type'] = 'reaction';
            }

            if (!array_key_exists($engagement['activity_type'], $carry[$pageName])) {
                $carry[$pageName][$engagement['activity_type']] = 0;
            }

            $carry[$pageName][$engagement['activity_type']] += 1;
            return $carry;
        }, []);

        $retval = [];
        $typePluralizedMap = [
            'message' => 'messages',
            'comment' => 'comments',
            'reaction' => 'reactions',
        ];

        foreach ($summary as $pageName => $values) {
            foreach ($values as $type => $count) {
                if (array_key_exists($type, $typePluralizedMap)) {
                    $type = ngettext($type, $typePluralizedMap[$type], $count);
                    $retval[$pageName][] = "{$count} {$type}";
                }
            }
        }

        return $retval;
    }

    private function summaryToString(array $summary, bool $isHtml = false): string
    {
        $retval = '';
        foreach ($summary as $pageName => $values) {
            $values = array_map(function ($value) use ($isHtml) {
                return $isHtml ? "<li>{$value}</li>" : "- {$value}";
            }, $values);

            $valuesString = implode("\n", $values);
            if ($isHtml) {
                $valuesString = sprintf("<ul>\n%s\n</ul>", $valuesString);
                $pageName = "<strong>{$pageName}</strong>";
            }

            $retval .= "\n\n" . $pageName . "\n" . $valuesString;

        }

        return trim($retval);
    }
}
