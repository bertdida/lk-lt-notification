<?php

namespace LTN\Controllers;

class UserController
{
    const TABLE_NAME = 'users';

    public static function getById(\PDO $dbConn, int $id): ?array
    {
        $sql = 'SELECT * FROM `' . self::TABLE_NAME . '` WHERE `id` = :id LIMIT 1';
        $query = $dbConn->prepare($sql);
        $query->bindParam(':id', $id, \PDO::PARAM_INT);
        $query->execute();

        if ($query->rowCount() === 0) {
            return null;
        }

        return $query->fetch();
    }
}
