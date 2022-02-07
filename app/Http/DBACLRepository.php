<?php

namespace App\Http;

use App\FileManager\Services\ACLService\ACLRepository;

class DBACLRepository implements ACLRepository
{
    /**
     * Get user ID
     *
     * @return mixed
     */
    public function getUserID()
    {
        return \Auth::id();
    }

    /**
     * Get ACL rules list for user
     *
     * @return array
     */
    public function getRules(): array
    {
        if (\Auth::id() === 1) {
            return [
                ['disk' => 'public', 'path' => '*', 'access' => 2],
            ];
        }
        
        // return [
        //     ['disk' => 'disk-name', 'path' => '/', 'access' => 1],                                  // main folder - read
        //     ['disk' => 'disk-name', 'path' => 'users', 'access' => 1],                              // only read
        //     ['disk' => 'disk-name', 'path' => 'users/'. \Auth::user()->name, 'access' => 1],        // only read
        //     ['disk' => 'disk-name', 'path' => 'users/'. \Auth::user()->name .'/*', 'access' => 2],  // read and write
        // ];

        return \DB::table('acl_rules')
            ->where('user_id', $this->getUserID())
            ->get(['disk', 'path', 'access'])
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->all();
    }
}