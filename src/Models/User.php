<?php

namespace LTN\Models;

use LTN\Controllers\ConfigController;
use LTN\Controllers\UserController;
use PDO;

class User
{
    public $dbConn;

    public function __construct(PDO $dbConn, int $id)
    {
        $this->dbConn = $dbConn;
        $user = UserController::getById($this->dbConn, $id);

        if (is_array($user)) {
            foreach ($user as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function getConfigs(): array
    {
        return ConfigController::getAll($this);
    }
}
