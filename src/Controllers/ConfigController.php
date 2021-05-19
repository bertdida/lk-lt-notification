<?php

namespace LTN\Controllers;

use LTN\Models\Config;
use LTN\Models\User;

class ConfigController
{
    public static function getAll(User $user): array
    {
        $sql = <<<SQL
        SELECT * FROM `live_tracker_filter_notification_configs` AS c
        LEFT JOIN live_tracker_filters AS l
        ON c.live_tracker_filter_id = l.id WHERE c.user_id = :userId;
SQL;
        $query = $user->dbConn->prepare($sql);
        $query->bindParam(':userId', $user->id, \PDO::PARAM_INT);
        $query->execute();

        return array_map(function ($result) use ($user) {
            return new Config($result, $user);
        }, $query->fetchAll());
    }
}
