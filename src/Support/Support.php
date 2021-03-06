<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 14/12/18
 * Time: 14:52
 */

namespace Idsign\Permission\Support;

use Illuminate\Support\Facades\Route;

class Support
{
    protected $app = null;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function routes($guard = null)
    {
        Route::namespace('Idsign\Permission\Http\Controllers')
            ->middleware('auth:'.($guard ? $guard : config('auth.defaults.guard')))
            ->prefix('/acl')
            ->group(function (){
                Route::prefix('/labels')->group(function (){
                    Route::get('/all', 'LabelsController@all');
                });

                Route::prefix('/sections')->group(function (){
                    Route::get('/get-tree', 'SectionController@getTree');
                    Route::delete('/delete/{modelId}', 'SectionController@delete');
                    Route::post('/move', 'SectionController@move');
                    Route::post('/add', 'SectionController@add');
                    Route::put('/change', 'SectionController@change');
                });

                Route::prefix('/containers')->group(function (){
                    Route::get('/all', 'ContainerController@all');
                    Route::get('/data', 'ContainerController@data');
                    Route::post('/create', 'ContainerController@create');
                    Route::delete('/delete', 'ContainerController@deleteAdvanced');
                    Route::put('/update-field/{modelId}', 'ContainerController@update');
                    Route::get('/get-sections-tree/{containerId}', 'ContainerController@getSectionsTree');
                    Route::post('/set-enabled-sections/{containerId}', 'ContainerController@setEnabledSections');
                    Route::post('/set-section-superadmin/{containerId}/{sectionId}', 'ContainerController@setSectionSuperadmin');
                });

                Route::prefix('/permissions')->group(function (){
                    Route::get('/all', 'PermissionController@all');
                    Route::get('/data', 'PermissionController@data');
                    Route::post('/create', 'PermissionController@create');
                    Route::delete('/delete', 'PermissionController@deleteAdvanced');
                    Route::put('/update-field/{modelId}', 'PermissionController@update');
                });

                Route::prefix('/roles')->group(function (){
                    Route::get('/all', 'RoleController@all');
                    Route::get('/data', 'RoleController@data');
                    Route::get('/config', 'RoleController@config');
                    Route::post('/create', 'RoleController@create');
                    Route::delete('/delete', 'RoleController@deleteAdvanced');
                    Route::put('/update-field/{modelId}', 'RoleController@update');
                });

                Route::prefix('/sweeten-roles')->group(function (){
                    Route::get('/data', 'SweetenRoleController@data');
                    Route::post('/create', 'SweetenRoleController@create');
                    Route::post('/set-permission/{roleId}/{containerId}/{sectionId}/{permissionId}', 'SweetenRoleController@setPermission');
                    Route::delete('/delete', 'SweetenRoleController@deleteAdvanced');
                    Route::put('/update-field/{modelId}', 'SweetenRoleController@update');
                    Route::post('/set-containers/{roleId}', 'SweetenRoleController@setContainers');
                    Route::get('/get-container-data/{roleId}/{containerId}', 'SweetenRoleController@getContainerData');
                });

                Route::prefix('/users')->group(function (){
                    Route::get('/config', 'UserController@config');
                    Route::get('/logged-user', 'UserController@loggedUser');
                    Route::get('/data', 'UserController@data');
                    Route::post('/set-roles/{userId}', 'UserController@setRoles');
                    Route::delete('/delete', 'UserController@deleteAdvanced');
                    Route::put('/update-field/{userId}', 'UserController@update');
                    Route::post('/create', 'UserController@create');
                    Route::get('/get-containers/{userId}', 'UserController@getContainers');
                    Route::get('/get-roles-permission-tree/{userId}/{containerId}', 'UserController@getRolesPermissionTree');
                    Route::get('/get-user-permission-tree/{userId}/{containerId}', 'UserController@getUserPermissionTree');
                    Route::post('/set-user-permission/{userId}/{containerId}/{sectionId}/{permissionId}', 'UserController@setUserPermission');
                });
            });
    }
}
