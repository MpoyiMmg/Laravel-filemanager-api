<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'content', "status"
    ];

    public function user()
    { 
        return $this->belongsTo('App\User', 'sender_id');
    }

    public function document() {
        return $this->belongsTo('App\Document', 'doc_id');
    }
}
