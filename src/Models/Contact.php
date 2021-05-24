<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\ContactInfo;
use LTN\Models\ContactType;

class Contact extends Model
{
    protected $table = 'contacts';

    public function info()
    {
        return $this->hasMany(ContactInfo::class);
    }

    public function type()
    {
        return $this->belongsTo(ContactType::class, 'stage_type_id');
    }
}
