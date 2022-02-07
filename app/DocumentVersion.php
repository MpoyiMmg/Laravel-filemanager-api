<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'name', 'path', 'disk', 'comment'];

    public function users()
    {
        return $this->belongsToMany('App\User', 'users_document_versions');
    }
}
