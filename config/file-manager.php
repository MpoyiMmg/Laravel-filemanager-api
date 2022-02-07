<?php

use App\Http\DBACLRepository;
use App\FileManager\Services\ACLService\ConfigACLRepository;
use App\FileManager\Services\ConfigService\DefaultConfigRepository;

return [

    /**
     * Set Config repository
     *
     * Default - DefaultConfigRepository get config from this file
     */
    'configRepository' => DefaultConfigRepository::class,

    /**
     * ACL rules repository
     *
     * Default - ConfigACLRepository (see rules in - aclRules)
     */
    'aclRepository' => DBACLRepository::class,

    //********* Default configuration for DefaultConfigRepository **************

    /**
     * LFM Route prefix
     * !!! WARNING - if you change it, you should compile frontend with new prefix(baseUrl) !!!
     */
    'routePrefix' => 'file-manager',

    /**
     * List of disk names that you want to use
     * (from config/filesystems)
     */
    'diskList' => ['public'],

    /**
     * Default disk for left manager
     *
     * null - auto select the first disk in the disk list
     */
    'leftDisk' => null,

    /**
     * Default disk for right manager
     *
     * null - auto select the first disk in the disk list
     */
    'rightDisk' => null,

    /**
     * Default path for left manager
     *
     * null - root directory
     */
    'leftPath' => null,

    /**
     * Default path for right manager
     *
     * null - root directory
     */
    'rightPath' => null,

    /**
     * Image cache ( Intervention Image Cache )
     *
     * set null, 0 - if you don't need cache (default)
     * if you want use cache - set the number of minutes for which the value should be cached
     */
    'cache' => null,

    /**
     * File manager modules configuration
     *
     * 1 - only one file manager window
     * 2 - one file manager window with directories tree module
     * 3 - two file manager windows
     */
    'windowsConfig' => 2,

    /**
     * File upload - Max file size in KB
     *
     * null - no restrictions
     */
    'maxUploadFileSize' => null,

    /**
     * File upload - Allow these file types
     *
     * [] - no restrictions
     */
    'allowFileTypes' => ['jpeg','jpg','png','pdf','doc','docx','xls','xlsx','mp4','mp3','wav','form','pptx','dwg'],

    /**
     * Show / Hide system files and folders
     */
    'hiddenFiles' => true,

    /***************************************************************************
     * Middleware
     *
     * Add your middleware name to array -> ['web', 'auth', 'admin']
     * !!!! RESTRICT ACCESS FOR NON ADMIN USERS !!!!
     */
    'middleware' => ['auth:sanctum'], 

    /***************************************************************************
     * ACL mechanism ON/OFF
     *
     * default - false(OFF)
     */
    'acl' => true,

    /**
     * Hide files and folders from file-manager if user doesn't have access
     *
     * ACL access level = 0
     */
    'aclHideFromFM' => true,

    /**
     * ACL strategy
     *
     * blacklist - Allow everything(access - 2 - r/w) that is not forbidden by the ACL rules list
     *
     * whitelist - Deny anything(access - 0 - deny), that not allowed by the ACL rules list
     */
    'aclStrategy' => 'whitelist',

    /**
     * ACL Rules cache
     *
     * null or value in minutes
     */
    'aclRulesCache' => null,

    //********* Default configuration for DefaultConfigRepository END **********


    /***************************************************************************
     * ACL rules list - used for default ACL repository (ConfigACLRepository)
     *
     * 1 it's user ID
     * null - for not authenticated user
     *
     * 'disk' => 'disk-name'
     *
     * 'path' => 'folder-name'
     * 'path' => 'folder1*' - select folder1, folder12, folder1/sub-folder, ...
     * 'path' => 'folder2/*' - select folder2/sub-folder,... but not select folder2 !!!
     * 'path' => 'folder-name/file-name.jpg'
     * 'path' => 'folder-name/*.jpg'
     *
     * * - wildcard
     *
     * access: 0 - deny, 1 - read, 2 - read/write
     */
    'aclRules' => [        
        1 => [                        
            ['disk' => 'public', 'path' => '*', 'access' => 2],            
        ],
        19 => [
            ['disk' => 'public', 'path' => '/', 'access' => 1],            
            ['disk' => 'public', 'path' => 'SECURITE*', 'access' => 1],            
            ['disk' => 'public', 'path' => 'FINANCES*', 'access' => 1],            
        ],
        25 => [
            ['disk' => 'public', 'path' => '/', 'access' => 1],
            ['disk' => 'public', 'path' => 'MANAGEMENT', 'access' => 1],            
            ['disk' => 'public', 'path' => 'MANAGEMENT/*', 'access' => 2],            

        ],
    ],
];
