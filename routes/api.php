<?php

use App\User;
use App\Computer;
use App\Metadata;
use App\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use App\FileManager\Services\ConfigService\DefaultConfigRepository;

$config = new DefaultConfigRepository();

// App middleware list
$middleware = $config->getMiddleware();

/**
 * If ACL ON add "fm-acl" middleware to array
 */
if ($config->getAcl()) {
  $middleware[] = 'fm-acl';
}


Route::get('file-manager/office-edit/{path}', function($path) {
  return App::make('\App\Http\Controllers\Api\FileManagerController')->callAction('officeEdit', [$path]);
})->where('path', '([()a-zA-Z0-9 ?,/.-]+)');

Route::post('file-manager/office-edit/{path}', function(Request $request, $path) {
  return App::make('\App\Http\Controllers\Api\FileManagerController')->callAction('officeEditSave', [$request, $path]);
})->where('path', '([()a-zA-Z0-9 ?,/.-]+)');

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/test', 'Api\TestController@index');

Route::post('/login', 'Api\LoginController@authenticate');
Route::post('/logout', 'Api\LoginController@logout')->middleware('auth:sanctum');
// Users routes

// Route::post('/users', 'Api\UserController@logoutUser');
Route::get('/auth-user', 'Api\UserController@getUserData');
Route::get('/directories', 'Api\UserController@getAllDirectories');
Route::put('/permissions', 'Api\UserController@changePermissions');
Route::post('/permissions', 'Api\UserController@getAllPermissions');
Route::get('/group-permissions', 'Api\PermissionController@groupPermissions');
Route::get('/permissions/user/{id}', 'Api\PermissionController@permissionsUser');


Route::post('/notifications', 'Api\NotificationsController@store');
Route::get('/notifications', 'Api\NotificationsController@index');
Route::get('/notifications/show/{id}', 'Api\NotificationsController@show');
Route::put('/notifications/{id}', 'Api\NotificationsController@update');

/**
 *  FileManager routes
 */

 

Route::get('file-manager/preview', 'Api\FileManagerController@previewFile')->name('fm.preview');
//  Route::get('file-manager/office-edit', 'FileManagerController@officeEdit')->name('fm.officeEdit');}
Route::post('file-manager/office-edit/{safebox}/{binder}/{folder}/{filename}', 'Api\FileManagerController@officeEditSave')->name('fm.officeEditSave');
Route::group([
  'middleware' => $middleware,
  'prefix'     => $config->getRoutePrefix(),
], function () {
  
  Route::get('download', 'Api\FileManagerController@download')->name('fm.download');
  Route::get('download-old-file', 'Api\FileManagerController@downloadOldVersion')->name('fm.downloadOldVersion');
  Route::get('initialize', 'Api\FileManagerController@initialize')->name('fm.initialize');
  Route::get('content', 'Api\FileManagerController@content')->name('fm.content');
  Route::post('search', 'Api\FileManagerController@search')->name('fm.search');
  Route::get('count-documents','Api\FileManagerController@countDocuments')->name('fm.count-documents');
  Route::get('documents-size','Api\FileManagerController@getDocumentsSize')->name('fm.documents-size');
  Route::get('document-path/{id}','Api\FileManagerController@documentPath')->name('fm.document-path');
  Route::get('get-document/{id}','Api\FileManagerController@getDocumentById')->name('fm.get-document');
  Route::get('list-documents','Api\FileManagerController@Listdocuments')->name('fm.list-documents');
  Route::get('tree', 'Api\FileManagerController@tree')->name('fm.tree');
  Route::get('select-disk', 'Api\FileManagerController@selectDisk')->name('fm.select-disk');
  Route::get('old-versions/{id}', 'Api\FileManagerController@oldVersions')->name('fm.old-versions');
  Route::delete('documents/versions/{id}', 'Api\FileManagerController@deleteVersion');
  Route::post('modify', 'Api\FileManagerController@modify')->name('fm.modify');
  Route::post('documents/document-type/edit', 'Api\FileManagerController@modifyDocumentType')->name('fm.modify-document-type');
  Route::get('documents/{id}/path', 'Api\FileManagerController@documentPath')->name('fm.document-path');
  Route::post('upload', 'Api\FileManagerController@upload')->name('fm.upload');
  Route::post('delete', 'Api\FileManagerController@delete')->name('fm.delete');
  Route::post('paste', 'Api\FileManagerController@paste')->name('fm.paste');
  Route::post('rename', 'Api\FileManagerController@rename')->name('fm.rename');
  Route::get('thumbnails', 'Api\FileManagerController@thumbnails')->name('fm.thumbnails');
  Route::get('url', 'Api\FileManagerController@url')->name('fm.url');
  Route::post('create-directory', 'Api\FileManagerController@createDirectory')->name('fm.create-directory');
  Route::post('create-file', 'Api\FileManagerController@createFile')->name('fm.create-file');
  Route::post('update-file', 'Api\FileManagerController@updateFile')->name('fm.update-file');
  Route::get('stream-file', 'Api\FileManagerController@streamFile')->name('fm.stream-file');
  Route::post('zip', 'Api\FileManagerController@zip')->name('fm.zip');
  Route::post('unzip', 'Api\FileManagerController@unzip')->name('fm.unzip');
  Route::get('properties', 'Api\FileManagerController@properties');
});

Route::post('profiles', 'Api\ProfileController@store');
Route::get('profiles', 'ProfileController@index');
Route::get('profiles/{id}', 'ProfileController@show');
Route::put('profiles/{id}', 'ProfileController@update');
Route::delete('profiles/{id}', 'ProfileController@delete');

Route::get('computers', 'Api\ComputerController@index');

Route:: get('/count-logs','Api\LogController@CountLogs');

// Document Metadata routes
Route::post('/document-metadata', 'Api\DocumentMetadataController@store');
Route::get('/document-metadata/{id}', 'Api\DocumentMetadataController@show');
Route::put('/document-metadata', 'Api\DocumentMetadataController@update');

// Permissions routes
Route::get('/permissions', 'Api\PermissionController@index');

Route::get('/actionTypes', 'Api\ActionTypeController@index');

// get statistics
Route::get('/user', 'Api\UserController@countUser');
Route::get('/count', 'Api\DocumentTypeController@countStat');
Route::get('/document-types/count', 'Api\DocumentTypeController@countDocumentTypes');
Route::get('process/tasks', 'Api\ProcessController@getUserTasks');
Route::get('process/show/{id}', 'Api\ProcessController@showProcess');
Route::get('process/check/{id}', 'Api\ProcessController@checkDocument');
Route::get('process/document/{id}', 'Api\ProcessController@getDocumentStates');
Route::get('process/state-actions/{id}', 'Api\ProcessController@getDocumentStateActions');
Route::post('process/change-state', 'Api\ProcessController@changeDocumentState');

// Comments
Route::get('/comments/{id}', 'Api\CommentController@index');
Route::post('/comments', 'Api\CommentController@store');

Route::get('/users', 'Api\UserController@index');

// get all users
Route::get('/all-users', 'Api\UserController@allUsers');

Route::group(['middleware' => 'role'], function() {
  // users
  Route::post('/users', 'Api\UserController@store');
  Route::get('/users/{id}', 'Api\UserController@show');
  Route::put('/users/{id}', 'Api\UserController@update');
  Route::post('/users-profile', 'Api\UserController@updateProfile');
  Route::delete('/users/{id}','Api\UserController@destroy');
  Route::post('/profile','Api\UserController@updatePicture');
  Route::post('/users/reset-password', 'Api\UserController@changePassword');
  Route::put('users/status/{id}','Api\UserController@updateStatus');
  Route::get('/users/{id}', 'Api\UserController@show');

  //Metadata routes
  Route::get('/metadata', 'Api\MetadataController@index');
  Route::post('/metadata', 'Api\MetadataController@store');
  Route::get('/metadata/{id}', 'Api\UserController@show');
  Route::get('/metadata/{id}/options', 'Api\MetadataController@metadataOptions');
  Route::delete('/metadata/{id}','Api\MetadataController@destroy');
  Route::put('/metadata/{id}', 'Api\MetadataController@update');
  Route::get('/options', 'Api\OptionController@index');
  Route::post('/metadata/options', 'Api\OptionController@store');

  // Logs routes
  Route::get('/logs/{perPage}', 'Api\LogController@index');
  Route::get('/logs', 'Api\LogController@allLogs');
  Route::get('/latest-documents', 'Api\LogController@latestDocument');

  // Roles routes
  Route::get('/roles', 'Api\RoleController@index');
  Route::post('/roles', 'Api\RoleController@store');
  Route::post('/users-roles', 'Api\RoleController@setUserRole');
  Route::get('/roles/{id}', 'Api\RoleController@show');
  Route::put('/roles/{id}', 'Api\RoleController@update');
  Route::delete('/roles/{id}', 'Api\RoleController@destroy');

  /**
   *  DocumentType routes
   */
  Route::post('/document-types', 'Api\DocumentTypeController@store');
  Route::get('/document-types', 'Api\DocumentTypeController@index');
  Route::get('/document-types/{id}', 'Api\DocumentTypeController@show');
  Route::post('/document-types/{id}', 'Api\DocumentTypeController@update');
  Route::delete('/document-types/{id}', 'Api\DocumentTypeController@destroy');

  // Document Workflow & Process routes
  Route::post('processes', 'Api\ProcessController@store');
  Route::post('process/store', 'Api\ProcessController@storeProcess');
  Route::post('process/initiate', 'Api\ProcessController@initiateProcess');
  Route::get('processes', 'Api\ProcessController@index');
  Route::get('process/{id}', 'Api\ProcessController@show');
  Route::put('processes/{id}', 'Api\ProcessController@update');
  Route::delete('processes/{id}', 'Api\ProcessController@destroy');

  Route::post('states', 'Api\StateController@store');
  Route::put('states/target-profile/{id}', 'Api\StateController@set_target_profile');
  Route::get('states/{id} ', 'Api\StateController@show');
  Route::post('transitions', 'Api\TransitionController@store');
  Route::post('actions', 'Api\ActionController@store');
  Route::put('actions/{id}', 'Api\ActionController@update');
  Route::get('actions', 'Api\ActionController@index');
  Route::delete('actions/{id}', 'Api\ActionController@destroy');
});