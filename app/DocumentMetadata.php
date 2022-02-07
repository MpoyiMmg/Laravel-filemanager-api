<?php

namespace App;

use App\Document;
use Illuminate\Database\Eloquent\Model;

class DocumentMetadata extends Model
{
    protected $fillable = [
        'document_id',
        'metadata_id',
        'value'
    ];
    function documents()
    {
        return $this->belongsToMany('App\Document', 'document_id');
    }

    public function metadata()
    {
        return $this->belongsTo('App\Metadata');
    }
   
}
