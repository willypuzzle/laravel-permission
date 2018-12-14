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

    public function routes()
    {
        Route::namespace('Idsign\Permission\Http\Controllers')->prefix('/acl')->group(function (){
            Route::prefix('/sections')->group(function (){
                Route::get('get-tree', 'SectionController@getTree');
            });
        });
    }
}
