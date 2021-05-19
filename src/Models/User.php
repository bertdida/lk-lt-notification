<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\NotificationConfig;

class User extends Model
{
    protected $table = 'users';

    public function notificationConfigs()
    {
        return $this->hasMany(NotificationConfig::class);
    }
}
