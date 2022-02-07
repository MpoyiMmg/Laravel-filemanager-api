<?php

namespace App\Http\Controllers\Api;

use App\Role;
use App\User;
use Exception;
use App\UsersRole;
use Illuminate\Http\Request;

use App\FileManager\LogsTracker;
use App\Jobs\ProcessUserMailJob;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Image;

class UserController extends Controller
{

    public $logsTracker;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->logsTracker = new LogsTracker();

    }
    /**
     * Construct of UserController
     *
     */

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            if(!$request->user()->can('list-users')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            $data = User::where('id','<>', Auth::user()->id)
                    ->where('is_admin', false)
                    ->orderBy('created_at','desc')
                    ->get();
        } catch (Exceotion $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json($data, 200);
    }

    public function allUsers(Request $request) {
        try {
            $data = User::where('id','<>', Auth::user()->id)
                    ->orderBy('firstname')
                    ->get();
        } catch (Exceotion $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
        return response()->json($data, 200);
    }

    public function countUser(Request $request)
    {
        if ($request->user()->is_admin) {
            $users = User::all();

            $allusersWithCount = [
                'users' => $users,
                'users_count' => $users->count()
            ];
            return $allusersWithCount;
        }
        return null;
    }
    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Role $role)
    {
        
        try {
            if ($request->user()->can('create-users')) {
                if ($request->file('picture')) {

                    $validator = Validator::make($request->all(), [
                        'firstname' => 'required|max:25',
                        'middlename' => 'required|max:25',
                        'lastname' => 'required|max:25',
                        'email' => 'required|email|unique:users|max:50',
                        'address' => 'required|max:50',
                        'phone' => 'required|unique:users|max:15',
                        'picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                        'roles' => 'required'
                    ]);
                } else {
                    $validator = Validator::make($request->all(), [
                        'firstname' => 'required|max:25',
                        'middlename' => 'required|max:25',
                        'lastname' => 'required|max:25',
                        'email' => 'required|email|unique:users|max:50',
                        'address' => 'required|max:50',
                        'phone' => 'required|unique:users|max:15',
                        'roles' => 'required'
                    ]);
                }
    
                if ($validator->fails()) {
                    return response()->json([
                        'errors' => $validator->errors(),
                    ], 422);
                }
    
                if ($request->file('picture')) {
                    $picture = $request->file('picture');
                    $user_picture = rand() . '.' . $picture->getClientOriginalExtension();
                    $picture->move(public_path('images'), $user_picture);
                } else {
                    $user_picture = null;
                }

                $profile = $user_picture ? ('/images/' . $user_picture) : null;
                $user = User::Create([
                    'firstname' => $request->firstname,
                    'middlename' => $request->middlename,
                    'lastname' => $request->lastname,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'phone' => str_replace(' ', '', $request->phone),
                    'picture' => $profile,
                    'actif' => $request->actif == "true" ? true : false,
                    'password' => bcrypt('123456'),
                ]);

                //Send a mail to created user
                //ProcessUserMailJob::dispatch($user);
                ProcessRegistration::dispatch($user);
                $userRolesCreated = [];
                foreach ($request->roles as $roles) {
                    
                    for ($i=0; $i < count($roles); $i++) {
                        $roleExist = $role->find($roles[$i]);
                        
                        if($roleExist === null) {
                            $data = ['error' => 'Aucun role ne correspond à l\'id '.$roles[$i]];
                            return response()->json($data, 404);
                        }

                        $userRole = new UsersRole;

                        $userRole->user_id = $user->id;
                        $userRole->role_id = $roles[$i];
                        $userRole->save();

                        array_push($userRolesCreated, $userRole);
                    }
                }

                $defaultRules = [
                    "user_id" => $user->id,
                    "disk" => "public",
                    "path" => "/",
                    "access" => 1
                ];

                DB::table('acl_rules')->insert($defaultRules);
            }
            else {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
        } catch (Exception $e) {

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'data' => $user,
            'userRoles' => $userRolesCreated
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\User  $user<
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id)
    {
        try {
            $user = User::find($request->id);

            if ($user === null) {
                $data = ['error' => 'Aucun utilisateur ne correspond à cet id'];
                return response()->json($data, 404);
            }

            $userRoles = UsersRole::where('user_id', $user->id)->get();
            $roles = [];

            foreach ($userRoles as $userRole ) {
                $role = Role::where('id', $userRole->role_id)->first();
                array_push($roles, $role);
            }

            $data = [
                        'userRead' => $user,
                        'roles' => $roles
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$id)
    {
        try {
            $user_id = $request->id;
            if ($request->user()->can('update-users')) {
                if ($request->file('picture')) {

                    $validator = Validator::make($request->all(), [
                        'firstname' => 'required|max:255',
                        'lastname' => 'required|max:255',
                        'email' => 'required|email|max:255',
                        'phone' => 'required|max:255',
                        'address' => 'required|max:255',
                        'picture' => 'image|mimes:jpeg,png,jpg|max:2048',
                    ]);
                } else {
                    $validator = Validator::make($request->all(), [
                        'firstname' => 'required|max:255',
                        'lastname' => 'required|max:255',
                        'email' => 'required|email|max:255',
                        'phone' => 'required|max:255',
                        'address' => 'required|max:255',
                    ]);
                }
    
              
                $user = User::findOrFail($user_id);
    
                if ($request->file('picture')) {
                    $picture = $request->file('picture');
                    $user_picture = rand() . '.' . $picture->getClientOriginalExtension();
                    $picture->move(public_path('images'), $user_picture);
                    $user->picture = "";
                    $profile = '/images/' . $user_picture;
                } else {
                    $user_picture = $user->picture;
                    $profile = $user_picture;
                }
    
                $user->firstname = $request->user['firstname'];
                $user->lastname = $request->user['lastname'];
                $user->email = $request->user['email'];
                $user->phone = str_replace(' ', '', $request->user['phone']);
                $user->address = $request->user['address'];
                $user->picture = $profile;
                $user->actif = $request->user['actif'];
    
                $user->save();


            $usersRolesUpdated = [];
            $userRole = new UsersRole;
            $userRoleDeleted = $userRole->where('user_id', $user->id)->delete();
            foreach ($request->roles as $role) {
                if ($role !== null) {                
                    $userRole = new UsersRole;

                    $userRole->user_id = $user->id;
                    $userRole->role_id = $role['id'];
                    $userRole->save();
                    
                    array_push($usersRolesUpdated, $userRole);
                }
            }

            $data = [
                        'userUpdated' => $user,
                        'usersRolesUpdated' => $usersRolesUpdated
                    ];
            } else {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }

        } catch (Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'data' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, User $user,$id)
    {
        try {
            $user = $user->find($request->id);
            $user->actif = $user->actif == 1 ? 0 : 1;
            $user->save();
        
        } catch (Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
        return $user;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        // return response()->json($request->all(), 500);
        $user_id = $request->user()->id;

        try {
            $validator = Validator::make($request->all(), [
                'firstname' => 'required|max:255',
                'lastname' => 'required|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|max:255',
                'address' => 'required|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::findOrFail($user_id);

            $user->firstname = $request->firstname;
            $user->lastname = $request->lastname;
            $user->email = $request->email;
            $user->phone = str_replace(' ', '', $request->phone);
            $user->address = $request->address;

            $user->save();

        } catch (Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage()
            ], 404);
        }

        return response()->json([
            'user' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function updatePicture(Request $request)
    {
        $user_id = $request->user()->id;

        try {

            $validator = Validator::make($request->all(), [
                'picture' => 'image|mimes:jpeg,png,jpg|max:3072',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            try {
                
                $picture = $request->file('picture');
                $user_picture = rand() . '_' . $request->file('picture')->getClientOriginalName();
                
                $imgSmall = Image::make($picture)->resize(36,36);
                $imgSmall->save(public_path('/images/small/'.$user_picture));

                $imgMedium = Image::make($picture)->resize(200,200);
                $imgMedium->save(public_path('/images/medium/'.$user_picture));

                $picture->move(public_path('/images', $user_picture));
                $profile = '/images/' . $user_picture;

                $user = User::findOrFail($user_id);
                $user->picture = $profile;
                $user->picture_small = '/images/small/'.$user_picture;
                $user->picture_medium ='/images/medium/'.$user_picture;

                $user->save();

            } catch (Exception $exception) {

                return response()->json([
                    'error' => [
                        "message" => $exception->getMessage(),
                        "type" => "ModelNotFoundException",
                    ],
                ], 500);
            }

        } catch (Exception $exception) {

            return response()->json([
                'error' => [
                    "message" => $exception->getMessage()
                ],
            ], 500);

        }

        return response()->json([
            'user' => $user,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try{
            if ($request->user()->can('delete-users')) {
                $user_id = $request->id;
                DB::table('acl_rules')->where('user_id', $user_id)->delete();
    
                $deleted = User::where('id', $user_id)->delete();
            } else {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
        } catch(Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
        return \response()->json($deleted, 200);
    }

    public function changePassword(Request $request)
    {
        try {
            $valid = validator($request->only('old_password', 'new_password', 'confirm_password'), [
                'old_password' => 'required|string|min:4',
                'new_password' => 'required|string|min:4',
                'confirm_password' => 'required_with:new_password|same:new_password|string|min:4',
            ]);

            if ($valid->fails()) {
                return response()->json([
                    'errors' => $valid->errors(),
                    'message' => 'Faild to update password.',
                    'type' => 'fail',
                    'status' => false,
                ], 422);
            }

            if (Hash::check($request->get('old_password'), $request->user()->password)) {
                $user = User::find($request->user()->id);
                $user->password = bcrypt($request->get('new_password'));
                if ($user->save()) {
                    return response()->json([
                        'data' => $user,
                        'message' => 'Your password has been updated',
                        'status' => true,
                    ], 200);
                }
            } else {
                return response()->json([
                    'errors' => [],
                    'message' => 'Wrong password entered.',
                    'type' => 'Wrong',
                    'status' => false,
                ], 422);
            }
        } catch (Exception $e) {
            return response()->json([
                'errors' => $e->getMessage(),
                'message' => 'Please try again',
                'status' => false,
            ], 500);
        }
    }

    public function getUserData(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }

    public function logoutUser(User $user)
    {
        $this->logsTracker->disconnect($user->id);

        $user->tokens()->delete();

    }

    public function getAllDirectories(Request $request) {
        $directories = Storage::disk('public')->directories();

        $jsonData = json_encode($directories, JSON_FORCE_OBJECT);

        return response()->json($jsonData, 200);

    }

    public function getAllPermissions(Request $request) {

        $first_dirname = $request->dirname.'/';
        $second_dirname = $request->dirname.'/*';

        $query = "SELECT usr.id as userID, 
                    CONCAT(usr.firstname,' ',usr.lastname) as username,
                    (SELECT acl.access FROM acl_rules as acl 
                        WHERE (acl.path = ? OR acl.path = ?) AND acl.user_id = usr.id) as access
                    FROM users as usr WHERE usr.id != 1";

        $users = DB::select($query, [$first_dirname, $second_dirname]);

        return response()->json([
            'users' => $users,
            'is_admin' => Auth::user()->is_admin
        ], 200);
    }

    public function changePermissions(Request $request) {
        
        if ($request->access === 0 || $request->access === 1) {
            $data_type_1 = [
                'disk' => $request->disk,
                'access' => $request->access,
                'path' => $request->path,
                'user_id' => $request->user_id
            ];
    
            $data_type_2 = [
                'disk' => $request->disk,
                'access' => $request->access,
                'path' => $request->path.'/*',
                'user_id' => $request->user_id
            ];

            $first_checking = DB::table('acl_rules')
                    ->where('user_id', $data_type_1['user_id'])
                    ->where('path', $data_type_1['path'])
                    ->first();
            $second_checking = DB::table('acl_rules')
                    ->where('user_id', $data_type_2['user_id'])
                    ->where('path', $data_type_2['path'])
                    ->first();
            
            if($first_checking && $second_checking){
                DB::table('acl_rules')
                    ->where('user_id', $data_type_2['user_id'])
                    ->where('path', $data_type_2['path'])
                    ->delete();
                DB::table('acl_rules')
                    ->where('user_id', $data_type_1['user_id'])
                    ->where('path', $data_type_1['path'])
                    ->delete();
                
                $data_type_1 = [
                    'disk' => $request->disk,
                    'access' => $request->access,
                    'path' => $request->path,
                    'user_id' => $request->user_id
                ];
        
                $data_type_2 = [
                    'disk' => $request->disk,
                    'access' => $request->access,
                    'path' => $request->path.'/',
                    'user_id' => $request->user_id
                ];
                
                DB::table('acl_rules')->insert($data_type_2);
                DB::table('acl_rules')->insert($data_type_1);
                
                return 'success';
            }

            $data_type_1 = [
                'disk' => $request->disk,
                'access' => $request->access,
                'path' => $request->path,
                'user_id' => $request->user_id
            ];
    
            $data_type_2 = [
                'disk' => $request->disk,
                'access' => $request->access,
                'path' => $request->path.'/',
                'user_id' => $request->user_id
            ];

            $first_checking = DB::table('acl_rules')
                    ->where('user_id', $data_type_1['user_id'])
                    ->where('path', $data_type_1['path'])
                    ->first();
            $second_checking = DB::table('acl_rules')
                    ->where('user_id', $data_type_2['user_id'])
                    ->where('path', $data_type_2['path'])
                    ->first();
            
            if($first_checking && $second_checking){
                DB::table('acl_rules')
                    ->where('user_id', $data_type_2['user_id'])
                    ->where('path', $data_type_2['path'])
                    ->update(["access" => $data_type_2['access']]);
                DB::table('acl_rules')
                    ->where('user_id', $data_type_1['user_id'])
                    ->where('path', $data_type_1['path'])
                    ->update(["access" => $data_type_1['access']]);
            } else {
                DB::table('acl_rules')->insert($data_type_2);
                DB::table('acl_rules')->insert($data_type_1);
            }
        }

        if ($request->access === 2) {
            $data_type_1 = [
                'disk' => $request->disk,
                'access' => $request->access,
                'path' => $request->path,
                'user_id' => $request->user_id
            ];
    
            $data_type_2 = [
                'disk' => $request->disk,
                'access' => $request->access,
                'path' => $request->path.'/',
                'user_id' => $request->user_id
            ];

            $first_checking = DB::table('acl_rules')
                    ->where('user_id', $data_type_1['user_id'])
                    ->where('path', $data_type_1['path'])
                    ->first();
            $second_checking = DB::table('acl_rules')
                    ->where('user_id', $data_type_2['user_id'])
                    ->where('path', $data_type_2['path'])
                    ->first();
            
            if($first_checking && $second_checking){
                DB::table('acl_rules')
                    ->where('user_id', $data_type_2['user_id'])
                    ->where('path', $data_type_2['path'])
                    ->delete();
                DB::table('acl_rules')
                    ->where('user_id', $data_type_1['user_id'])
                    ->where('path', $data_type_1['path'])
                    ->delete();
                
                $data_type_1 = [
                    'disk' => $request->disk,
                    'access' => $request->access,
                    'path' => $request->path,
                    'user_id' => $request->user_id
                ];
        
                $data_type_2 = [
                    'disk' => $request->disk,
                    'access' => $request->access,
                    'path' => $request->path.'/*',
                    'user_id' => $request->user_id
                ];
                
                DB::table('acl_rules')->insert($data_type_2);
                DB::table('acl_rules')->insert($data_type_1);
                
                return 'success';
            } else {

                $data_type_1 = [
                    'disk' => $request->disk,
                    'access' => $request->access,
                    'path' => $request->path,
                    'user_id' => $request->user_id
                ];
        
                $data_type_2 = [
                    'disk' => $request->disk,
                    'access' => $request->access,
                    'path' => $request->path.'/*',
                    'user_id' => $request->user_id
                ];
    
                DB::table('acl_rules')->insert($data_type_2);
                DB::table('acl_rules')->insert($data_type_1);
                return 'success';
            }

        }
                
    }

}
