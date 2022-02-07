<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LatestDocument extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['document_id', 'user_id'];

    public function documents() {
        return $this->belongsTo('App\Document', 'document_id');
    }
}
