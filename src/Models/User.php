<?php

namespace LTN\Models;

use LTN\Controllers\UserController;
use mysqli;

class User
{
    public function __construct(mysqli $dbConn, int $id)
    {
        $user = UserController::getById($dbConn, $id);

        if (!is_array($user)) {
            return null;
        }

        foreach ($user as $key => $value) {
            $this->$key = $value;
        }
    }
}
