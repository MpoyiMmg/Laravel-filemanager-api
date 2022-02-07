<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentTypeMetadata extends Model
{
    protected $fillable = [
        'document_type_id',
        'metadata_id'
    ];

}
