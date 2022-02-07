<?php

namespace App\FileManager\Services\ACLService;

/**
 * Class ConfigACLRepository
 *
 * Get rules from file-manager config file - aclRules
 *
 * @package App\FileManager\Services\ACLService
 */
class ConfigACLRepository implements ACLRepository
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
       

        return \DB::table('acl_rules')
            ->where('user_id', $this->getUserID())
            ->get(['disk', 'path', 'access'])
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->all();
    }
}
