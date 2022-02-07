<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    protected $fillable = [
        'name'
    ];

    public function states() {
        return $this->hasMany(State::class);  
    }
}
