<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;

class CheckToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // return ;
        if (gettype($request->bearerToken()) !== 'NULL') {
            # code...
            [$id, $accessToken] = explode('|', $request->bearerToken(), 2);
            $token = DB::table('personal_access_tokens')->where('token', hash('sha256', $accessToken))->first();
            $date_diff = date_diff(date_create($token->last_used_at), date_create(now()));
    
            if($date_diff->i > 50) {
                DB::table('personal_access_tokens')->where('token', hash('sha256', $accessToken))->delete();
                return response()->json([
                    "message"=>"Unauthenticated",
                    "success"=>false
                ], 401);
            }
        }

        return $next($request);
    }
}
