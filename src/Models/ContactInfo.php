<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\Contact;

class ContactInfo extends Model
{
    protected $table = 'contact_info';

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
