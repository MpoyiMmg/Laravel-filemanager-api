<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Metadata extends Model
{
    protected $fillable = [
        'label', 'type', 'required'
    ];
    public function user()
    {
        return $this->belongsTo('App\DocumentType');
    }

    public function metadata_value()
    {
        return $this->belongsTo('App\DocumentMetadata');
    }

    public function options()
    {
        return $this->belongsToMany('App\Option');
    }
}
