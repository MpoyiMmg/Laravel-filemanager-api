<?php

namespace App;

use App\User;
use App\DocumentType;
use App\DocumentMetadata;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = ['name','level','user_id','document_type_id', 'folder_id', 'comment', 'last_modified_at'];

    public function user() {
        return $this->belongsTo('App\User');
    }

    public function type() {
        return $this->belongsTo('App\DocumentType', 'document_type_id');
    }

    public function documentMetadata()
    {
        return $this->belongsTo('App\DocumentMetadata', 'document_id');
    }

    public function folders() {
        return $this->hasOne('App\Folder');
    }

    public function notifications() {
        return $this->hasOne('App\Notification');
    }

    public function latestDocuments() {
        return $this->belongTo('App\LatestDocument', 'document_id');
    }
    
}
