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

class Permission extends Seeder
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
        $read->save();

        $create = new PermissionModel();
        $create->name = PermissionContract::CREATE;
        $create->label = [
            'it' => "Creazione",
            'en' => "Create"
        ];
        $create->save();

        $update = new PermissionModel();
        $update->name = PermissionContract::UPDATE;
        $update->label = [
            'it' => "Aggiornamento",
            'en' => "Update"
        ];
        $update->save();

        $delete = new PermissionModel();
        $delete->name = PermissionContract::DELETE;
        $delete->label = [
            'it' => "Cancellazione",
            'en' => "delete"
        ];
        $delete->save();
    }
}
