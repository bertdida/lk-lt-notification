<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;

class Engagements extends Model
{
    protected $table = 'social_activities';

    public static $facebookReactions = [
        'like',
        'love',
        'haha',
        'wow',
        'sad',
        'angry',
        'support',
        'care',
    ];
}
