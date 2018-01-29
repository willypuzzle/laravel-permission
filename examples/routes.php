<?php

Route::middleware(['auth'])->prefix('/console/')->group(function (){
    Route::namespace('Console\Acl')->group(function (){
        Route::prefix('/acl')->group(function (){
            Route::get('/matrix-hook/{section}/users/init', 'MatrixController@userMatrixInit');
            Route::put('/matrix-hook/{section}/users', 'MatrixController@userMatrixUpdate');
            Route::get('/users/all', 'UserController@all');
            Route::get('/users/data', 'UserController@data');
            Route::get('/users/{user}', 'UserController@get');
            Route::post('/users/', 'UserController@create');
            Route::put('/users/{user}', 'UserController@update');
            Route::delete('/users/multi_delete', 'UserController@multi_delete');
            Route::delete('/users/{user}', 'UserController@delete');

            Route::get('/matrix-hook/{section}/roles/init', 'MatrixController@roleMatrixInit');
            Route::put('/matrix-hook/{section}/roles', 'MatrixController@roleMatrixUpdate');
            Route::get('/roles/all', 'RoleController@all');
            Route::get('/roles/data', 'RoleController@data');
            Route::get('/roles/{role}', 'RoleController@get');
            Route::post('/roles/', 'RoleController@create');
            Route::put('/roles/{role}', 'RoleController@update');
            Route::delete('/roles/multi_delete', 'RoleController@multi_delete');
            Route::delete('/roles/{role}', 'RoleController@delete');

            Route::get('/permissions/all', 'PermissionController@all');
            Route::prefix('/permissions')->group(function (){
                Route::get('/data', 'PermissionController@data');
                Route::get('/{permission}', 'PermissionController@get');
                Route::post('/', 'PermissionController@create');
                Route::put('/{permission}', 'PermissionController@update');
                Route::delete('/multi_delete', 'PermissionController@multi_delete');
                Route::delete('/{permission}', 'PermissionController@delete');
            });

            Route::get('/sections/all/{type?}', 'SectionController@all');
            Route::prefix('/sections')->group(function (){
                Route::get('/data/{sectionType?}', 'SectionController@data');
                Route::get('/{section}', 'SectionController@get');
                Route::post('/', 'SectionController@create');
                Route::put('/{section}', 'SectionController@update');
                Route::delete('/multi_delete', 'SectionController@multi_delete');
                Route::delete('/{section}', 'SectionController@delete');
            });

            Route::get('/section_types/all', 'SectionTypeController@all');
            Route::prefix('/section_types')->group(function (){
                Route::get('/data', 'SectionTypeController@data');
                Route::get('/{section_type}', 'SectionTypeController@get');
                Route::post('/', 'SectionTypeController@create');
                Route::put('/{section_type}', 'SectionTypeController@update');
                Route::delete('/multi_delete', 'SectionTypeController@multi_delete');
                Route::delete('/{section_type}', 'SectionTypeController@delete');
            });
        });
    });
});