<?php

namespace App;

use App\Computer;
use App\Document;
use App\Permissions\HasPermissionsTrait;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasPermissionsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname', 'middlename', 'lastname', 'email', 'password',
        'phone', 'picture', 'address', "actif",'picture_big','picture_medium','picture_small'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function name() {
        return $this->firstname.' '.$this->lastname;
    }


    public function computers()
    {
        return $this->HasMany('App\Computer', 'user_id');
    }
    
    public function notifications()
    {
        return $this->HasMany('App\Notification', 'user_id');
    }

    public function documents()
    {
        return $this->HasMany('App\Document');
    }

    public function roles() {
        return $this->belongsToMany('App\Role', 'users_roles');
    }

    public function documentVersions() {
        return $this->belongsToMany('App\DocumentVersion', 'users_document_versions');
    }

}
