<?php

use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // TODO: inspect all 
        // the missing permissions
        DB::table('permissions')->insert([
            // roles
            [
                'name' => 'Create Roles',
                'slug' => 'create-roles',
                'action' => 'create',
                'subject' => 'roles'
            ],
            [
                'name' => 'List Roles',
                'action' => 'list',
                'slug' => 'list-roles',
                'subject' => 'roles'
            ],
            [
                'name' => 'Read Roles',
                'slug' => 'read-roles',
                'action' => 'read',
                'subject' => 'roles'
            ],
            [
                'name' => 'Update Roles',
                'slug' => 'update-roles',
                'action' => 'update',
                'subject' => 'roles'
            ],
            [
                'name' => 'delete Roles',
                'slug' => 'delete-roles',
                'action' => 'delete',
                'subject' => 'roles'
            ],
            // users
            [
                'name' => 'Create users',
                'slug' => 'create-users',
                'action' => 'create',
                'subject' => 'users'
            ],
            [
                'name' => 'List users',
                'slug' => 'list-users',
                'action' => 'list',
                'subject' => 'users'
            ],
            [
                'name' => 'Read Users',
                'slug' => 'read-users',
                'action' => 'read',
                'subject' => 'users'
            ],
            [
                'name' => 'Update users',
                'slug' => 'update-users',
                'action' => 'update',
                'subject' => 'users'
            ],
            [
                'name' => 'delete users',
                'slug' => 'delete-users',
                'action' => 'delete',
                'subject' => 'users'
            ],
            // Document Types
            [
                'name' => 'Create DocumentTypes',
                'slug' => 'create-document-types',
                'action' => 'create',
                'subject' => 'document-types'
            ],
            [
                'name' => 'List DocumentTypes',
                'slug' => 'list-document-types',
                'action' => 'list',
                'subject' => 'document-types'
            ],
            [
                'name' => 'Read DocumentTypes',
                'slug' => 'read-document-types',
                'action' => 'read',
                'subject' => 'document-types'
            ],
            [
                'name' => 'Update DocumentTypes',
                'slug' => 'update-document-types',
                'action' => 'update',
                'subject' => 'document-types'
            ],
            [
                'name' => 'delete DocumentTypes',
                'slug' => 'delete-document-types',
                'action' => 'delete',
                'subject' => 'document-types'
            ],
            // Metadata
            [
                'name' => 'Create Metadata',
                'slug' => 'create-metadata',
                'action' => 'create',
                'subject' => 'metadata'
            ],
            [
                'name' => 'List Metadata',
                'slug' => 'list-metadata',
                'action' => 'list',
                'subject' => 'metadata'
            ],
            [
                'name' => 'Read Metadata',
                'slug' => 'read-metadata',
                'action' => 'read',
                'subject' => 'metadata'
            ],
            [
                'name' => 'Update Metadata',
                'slug' => 'update-metadata',
                'action' => 'update',
                'subject' => 'metadata'
            ],
            [
                'name' => 'delete Metadata',
                'slug' => 'delete-metadata',
                'action' => 'delete',
                'subject' => 'metadata'
            ],
            // logs
            [
                'name' => 'List Logs',
                'slug' => 'list-logs',
                'action' => 'list',
                'subject' => 'logs'
            ],

            // workFlow
            [
                'name' => 'Create Process',
                'slug' => 'create-process',
                'action' => 'create',
                'subject' => 'processes'
            ],
            [
                'name' => 'List Process',
                'slug' => 'list-process',
                'action' => 'list',
                'subject' => 'processes'
            ],
            [
                'name' => 'Read Processes',
                'slug' => 'read-processes',
                'action' => 'read',
                'subject' => 'processes'
            ],
            [
                'name' => 'Update Process',
                'slug' => 'update-process',
                'action' => 'update',
                'subject' => 'processes'
            ],
            [
                'name' => 'Delete Process',
                'slug' => 'delete-process',
                'action' => 'delete',
                'subject' => 'processes'
            ],
            // actions
            [
                'name' => 'Create Action',
                'slug' => 'create-action',
                'action' => 'create',
                'subject' => 'actions'
            ],
            [
                'name' => 'List Action',
                'slug' => 'list-action',
                'action' => 'list',
                'subject' => 'actions'
            ],
            [
                'name' => 'Read Action',
                'slug' => 'read-actions',
                'action' => 'read',
                'subject' => 'actions'
            ],
            [
                'name' => 'Update Action',
                'slug' => 'update-action',
                'action' => 'update',
                'subject' => 'actions'
            ],
            [
                'name' => 'Delete Action',
                'slug' => 'delete-action',
                'action' => 'delete',
                'subject' => 'actions'
            ],
        ]);
    }
}
