<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Binder extends Model
{
    protected $fillable = ['safebox_id', 'name', 'path'];
}
