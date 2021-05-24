<?php

namespace LTN\Models;

use Illuminate\Database\Eloquent\Model;
use LTN\Models\LiveTrackerFilter;

class ContactType extends Model
{
    protected $table = 'stage_types';

    public function liveTrackerFilters()
    {
        return $this->belongsToMany(
            LiveTrackerFilter::class,
            'live_tracker_filter_stage_type', // relationship's intermediate table
            'stage_type_id', // foreign key of this model
            'live_tracker_filter_id', // foreign key of the model we are joining
        );
    }
}
