<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transition extends Model
{
    protected $fillable = [
        'previousState', 'nextState', 'reference', 'action_id',
        'sourcePosition', 'destinationPosition', 'process_id',
        'source_ref', 'destination_ref'
    ];
}
