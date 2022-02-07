<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MetadataOptions extends Model
{
    protected $fillable = [
        'metadata_id', 'option_id'
    ];
}
