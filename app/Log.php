<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Mehradsadeghi\FilterQueryString\FilterQueryString;

class Log extends Model
{
    use FilterQueryString;
    protected $fillable = ['event', 'description', 'user_id', 'type'];
    protected $filters = ['user_id', 'created_at', 'like', 'between', 'event', 'description'];
}
