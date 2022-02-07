<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function metadata()
    {
        return $this->belongsToMany('App\Metadata');
    }

    public function documents()
    {
        return $this->HasMany('App\Document', 'document_type_id');
    }
    
}
