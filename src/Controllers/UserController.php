<?php

namespace LTN\Controllers;

use mysqli;

class UserController
{
    const TABLE_NAME = 'users';

    public static function getById(mysqli $dbConn, int $id): ?array
    {
        $sql = 'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `id` = ?';
        $query = $dbConn->prepare($sql);
        $query->bind_param('s', $id);
        $query->execute();

        $result = $query->get_result();
        $hasResult = $result->num_rows === 1;

        $query->free_result();
        $query->close();

        if (!$hasResult) {
            return null;
        }

        return $result->fetch_assoc();
    }
}
