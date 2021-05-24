<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\ContactType;

class LiveTrackerFilter extends Model
{
    protected $table = 'live_tracker_filters';

    public function contactTypes()
    {
        return $this->belongsToMany(
            ContactType::class,
            'live_tracker_filter_stage_type', // relationship's intermediate table
            'live_tracker_filter_id', // foreign key of this model
            'stage_type_id', // foreign key of the model we are joining
        );
    }
}
