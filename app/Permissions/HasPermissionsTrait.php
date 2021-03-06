<?php
 
namespace App\Permissions;

use App\Permission;
use App\Role;

trait HasPermissionsTrait {

    public function givePermissionsTo(... $permissions) {
        $permissions = $this->getAllPermissions($permissions);

        if ($permissions === null) {
            return $this;
        }

        $this->permissions()->saveMany($permissions);
        return $this;
    }

    public function withdrawPermissionTo(... $permissions) {
        $permissions = $this->getAllPermissions($permissions);
        $this->permissions()->detach($permissions);
        return $this;
    }

    public function refreshPermissions(... $permissions) {
        $this->permissions()->detach();
        return $this->givePermissionsTo($permissions);
    }

    public function hasPermissionTo($permission) {
        if (\Auth::id() === 1) {
            return true;
        }
        return $this->hasPermissionThroughRole($permission) || $this->hasPermission($permission);
    }

    public function hasPermissionThroughRole($permission) {
        foreach ($permission->roles as $role) {
           return true;
        }
        return false;
    }

    public function hasRole(... $roles) {
        foreach ($roles as $role) {
            if($this->roles->contains("id", $role)) {
                return true;
            }
            return false;
        }
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'users_permissions');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'users_roles');
    }

    public function hasPermission($permission) {
        return (bool) $this->permissions->where('slug', $permission->slug)->count();
    }

    protected function getAllPermissions(array $permissions) {
        return Permission::whereIn("slug", $permissions)->get();
    }
}