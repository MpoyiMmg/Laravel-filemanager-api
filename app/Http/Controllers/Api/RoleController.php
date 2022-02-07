<?php

namespace App\Http\Controllers\Api;

use App\Role;

use App\User;
use App\UsersRole;
use App\Permission;
use App\RolesPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function __construct() {
        $this->middleware('auth:sanctum');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            if (!$request->user()->can('list-roles')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }

            $data = Role::all();
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }

        return response()->json($data, 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Role $role, Permission $permission)
    {
        try {
            if (!$request->user()->can('create-roles')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            $validator = Validator::make($request->all(), [
                'name' => 'required|max:20',
                'slug' => 'required',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }
    
            $role->name = $request->name;
            $role->slug = $request->slug;
            $role->save();

            $permissionsRolesCreated = [];
            foreach ($request->permissions_group as $permissions) {
                
                for ($i=0; $i < count($permissions); $i++) {
                    $permissionExist = $permission->find($permissions[$i]);
                    
                    if($permissionExist === null) {
                        $data = ['error' => 'Aucun role ne correspond à l\'id '.$roles[$i]];
                        return response()->json($data, 404);
                    }

                    $permissionRole = new RolesPermission;

                    $permissionRole->role_id = $role->id;
                    $permissionRole->permission_id = $permissions[$i];
                    $permissionRole->save();

                    array_push($permissionsRolesCreated, $permissionRole);
                }
            }

            $data = [
                        'roleCreated' => $role,
                        'permissionsRoleCreated' => $permissionsRolesCreated
                    ];

        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }
        return response()->json($data, 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id )
    {

        try {
            $role = Role::find($request->id);

            if ($role === null) {
                $data = ['error' => 'Aucun role ne correspond à cet id'];
                return response()->json($data, 404);
            }

            $rolePermissions = RolesPermission::where('role_id', $role->id)->get();
            $permissions = [];

            foreach ($rolePermissions as $rolePermission ) {
                $permission = Permission::where('id', $rolePermission->permission_id)->first();
                array_push($permissions, $permission);
            }

            $data = [
                        'roleRead' => $role,
                        'permissions' => $permissions
                    ];
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }
        return response()->json($data, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit(Role $role)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$id)
    {
        try {

            if (!$request->user()->can('update-roles')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }

            $role = Role::find($request->id);
            
            if ($role === null) {
                $data = ['error' => 'Aucun role ne correspond à cet id'];
                return response()->json($data, 200);
            }
            $validator = Validator::make($request->all(), [
                'role' => 'required|max:15',
                'permissions' => 'required',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }
            $role->name = $request->role['name'];
            $role->slug = $request->role['slug'];
            $role->save();

            $permissionsRolesUpdated = [];
            $permissionRole = new RolesPermission;
            $permissionRoleDeleted = $permissionRole->where('role_id', $role->id)->delete();
            foreach (array_keys($request->permissions) as $key) {           
                    foreach ( $request->permissions[$key] as $value) {
                        if ($value != null) {
                            $permissionRole = new RolesPermission;

                            $permissionRole->role_id = $role->id;
                            $permissionRole->permission_id = $value['id'];
                            $permissionRole->save();

                            array_push($permissionsRolesUpdated, $permissionRole);
                        }
                    }
            }

            $data = [
                        'roleUpdated' => $role,
                        'permissionsRoleUpdated' => $permissionsRolesUpdated,

                    ];
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }
        return response()->json($data, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Role $role)
    {
        try {
            if (!$request->user()->can('delete-roles')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            
            $role = Role::where('id', $request->id)->first();
            if($role) {
                $user_role = DB::table('users_roles')->where('role_id', $role->id)->delete();
                $role_permissions = DB::table('roles_permissions')->where('role_id', $role->id)->delete();
                $role->delete();
                $data = ['roleDeleted' => $role];
            }

        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }
        return response()->json($data, 200);
    }
}
