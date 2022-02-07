<?php

namespace App;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Computer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'model', 'generation', 'price', 'user_id'
    ];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
}
