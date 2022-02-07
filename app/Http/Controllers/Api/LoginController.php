<?php

namespace App\Http\Controllers\Api;

use App\Log;
use App\Role;
use App\User;
use Exception;
use Validator;
use App\UsersRole;
use App\Permission;
use App\RolesPermission;
use Illuminate\Http\Request;
use App\FileManager\LogsTracker;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Handle an authentication attempt.
     *
     * @return Response
     */

    public $logsTracker;

    public function __construct() {

        $this->logsTracker = new LogsTracker();

    }

    public function authenticate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                $error = Json_decode($validator->messages()->toJson());
                return response()->json([
                    'error' => [
                        "message" => $error,
                        "type" => ["ValidationException"],
                    ],
                ], 302);
            } else {

                $user = User::where('email', $request->email)->first();

                if (!$user || !Hash::check($request->password, $user->password)) {

                    return response()->json([], 400);
                }
                if (!$user || !Hash::check($request->password, $user->password)) {

                    return response()->json([], 401);
                }
                //verifier si le user est actif 401
                if (!$user->actif){ 
                    return response()->json([], 403);
                }

                if ($user->is_admin) {
                    if (!$user->is_logged) {
                        $role = Role::all();

                        if (count($role) > 0) {
                            DB::statement("SET foreign_key_checks=1");
                            DB::table('roles')->delete();
                            DB::statement("SET foreign_key_checks=0");  
                        }

                        $newRole = new Role;
                        $newRole->name = 'Administrateur';
                        $newRole->slug = 'admin';
                        $newRole->save();

                        $userRole = new UsersRole;
                        $userRole->user_id = $user->id;
                        $userRole->role_id = $newRole->id;
                        $userRole->save();

                        $permissions = Permission::all();

                        if(count($permissions) > 0) {
                            foreach ($permissions as $permission) {
                                $rolePermission = new RolesPermission;
    
                                $rolePermission->role_id = $newRole->id;
                                $rolePermission->permission_id = $permission->id;
                                $rolePermission->save();
                            }
                            $user->is_logged = true;
                            $user->save();
                        } else {
                            return response()->json(["Aucune permission n'est existante"], 403);
                        }
                        
                    }
                }

                $token = $user->createToken($request->email)->plainTextToken;

                $this->logsTracker->connect($user->id);

                return response()->json([
                    'id' => $user->id,
                    'name' => $user->firstname.' '.$user->lastname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'picture_small' => $user->picture_small,
                    'token' => $token
                ], 200);

            }
        } catch (Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request) {
        $userId = Auth::user()->id;
        // dd($user);
        $this->logsTracker->disconnect($userId);
        return Auth::user()->currentAccessToken()->delete();
    }
}
