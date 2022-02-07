<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public function roles()
    {
        return $this->belongsToMany('App\Role', 'roles_permissions');
    }

    public function users()
    {
        return $this->belongsToMany('App\User', 'users_permissions');
    }

    public function permissionsUser($userId) {
        try {
            $user = User::find($userId);
            $permissions = [];
            if ($user->is_admin) {
                $permissions = Permission::all();
            } else {
                $userRole = UsersRole::where('user_id', $userId)->first();
                if ($userRole !== null) {
                    $role = Role::where('id', $userRole->role_id)->first();
                    if ( !empty($role)) {
                        $rolePermissions = RolesPermission::where('role_id', $role->id)->get();
                        $id = [];
                        foreach ($rolePermissions as $rolePermission) {
                            $permission = Permission::where('id', $rolePermission->permission_id)->first();
                            array_push($permissions, $permission);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return $permissions;
    }
}
