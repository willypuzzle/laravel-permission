<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 14/12/18
 * Time: 17:45
 */

namespace Idsign\Permission\Seeds;

use Illuminate\Database\Seeder;
use Idsign\Permission\Models\Permission as PermissionModel;
use Idsign\Permission\Contracts\Permission as PermissionContract;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $read = new PermissionModel();
        $read->name = PermissionContract::READ;
        $read->label = [
            'it' => "Lettura",
            'en' => "Read"
        ];
        $read->meta = [
            'order' => 1
        ];
        $read->save();

        $create = new PermissionModel();
        $create->name = PermissionContract::CREATE;
        $create->label = [
            'it' => "Scrittura",
            'en' => "Create"
        ];
        $create->meta = [
            'order' => 2
        ];
        $create->save();

        $update = new PermissionModel();
        $update->name = PermissionContract::UPDATE;
        $update->label = [
            'it' => "Modifica",
            'en' => "Update"
        ];
        $update->meta = [
            'order' => 3
        ];
        $update->save();

        $delete = new PermissionModel();
        $delete->name = PermissionContract::DELETE;
        $delete->label = [
            'it' => "Cancellazione",
            'en' => "delete"
        ];
        $delete->meta = [
            'order' => 4
        ];
        $delete->save();
    }
}
