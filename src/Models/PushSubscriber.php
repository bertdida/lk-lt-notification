<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscriber extends Model
{
    protected $connection = 'local';

    protected $table = 'push_subscribers';

    protected $fillable = [
        'lk_user_id',
        'endpoint',
        'auth',
        'p256dh',
    ];
}
