<?php

namespace App\Http\Middleware;

use Closure;
use App\User;
use App\Role;


class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $role = null, $permission = null)
    {
        $roles = Role::all();
        $user = $request->user();
        if ($user->is_admin) {
            return $next($request);
        } else {
            foreach ($roles as $role) {
                if ($user->hasRole($role->id)) {
                    return $next($request);
                }
            }
        }
        $data = ['error' => "Forbidden"];
        return response()->json($data, 403);
    }
}
