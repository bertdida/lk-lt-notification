<?php

namespace LTN\Controllers;

use LTN\Models\Config;
use LTN\Models\User;

class ConfigController
{
    public static function getAll(User $user)
    {
        $sql = <<<SQL
        SELECT * FROM `live_tracker_filter_notification_configs` AS c
        LEFT JOIN live_tracker_filters AS l
        ON c.live_tracker_filter_id = l.id WHERE c.user_id = ?;
SQL;
        $query = $user->dbConn->prepare($sql);
        $query->bind_param('s', $user->id);
        $query->execute();

        $result = $query->get_result();
        $hasResult = $result->num_rows >= 1;

        if ($hasResult === false) {
            return [];
        }

        $configs = [];
        while ($row = $result->fetch_assoc()) {
            array_push($configs, new Config($row, $user));
        }

        $query->free_result();
        $query->close();

        return $configs;
    }
}
