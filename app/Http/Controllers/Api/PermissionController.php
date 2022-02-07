<?php

namespace App\Http\Controllers\Api;

use App\Role;
use App\User;
use App\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    private $permission;
    public function __construct() {
        $this->middleware('auth:sanctum');
        $this->permission = new Permission;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $data = Permission::all();
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function show(Permission $permission)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function edit(Permission $permission)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Permission $permission)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Permission  $permission
     * @return \Illuminate\Http\Response
     */
    public function destroy(Permission $permission)
    {
        //
    }
    public function groupPermissions(Request $request)
    {
        try {
            $data = Permission::all()->groupBy('subject');
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }

        return response()->json($data, 200);
    }

    public function permissionsUser(Request $request) {
        $data = $this->permission->permissionsUser($request->id);
        return \response()->json($data);
    }
}
