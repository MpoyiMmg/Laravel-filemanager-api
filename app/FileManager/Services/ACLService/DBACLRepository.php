<?php

namespace App\FileManager\Services\ACLService;

/**
 * Class DBACLRepository
 *
 * @package App\FileManager\Services\ACLService
 */
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
        return \DB::table('acl_rules')
            ->where('user_id', $this->getUserID())
            ->get(['disk', 'path', 'access'])
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->all();
    }
}
