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
                    Route::get('/data', 'ContainerController@data');
                });
            });
    }
}
