<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $fillable = [
        'label'
    ];
    public function metadata()
    {
        return $this->belongsToMany('App\Metadata');
    }
}
