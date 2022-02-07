<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    public function process()
    {
        return $this->belongsTo('App\Process');
    }

    protected $fillable = [
        'name','process_id','user_id','xCoordinate',
        'yCoordinate', 'reference','type', 'metadata_id',
        'operator', 'metadataValue', 'transition_if_true',
        'transition_if_false'
    ];
}
