<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentState extends Model
{
  protected $fillable = [
    'document_id','state_id','comment','date_in',
    'date_out','user_id', 'state_type_id'
  ];
}
