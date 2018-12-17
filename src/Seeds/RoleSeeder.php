<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 14/12/18
 * Time: 17:45
 */

namespace Idsign\Permission\Seeds;

use Idsign\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Idsign\Permission\Models\Permission as PermissionModel;
use Idsign\Permission\Contracts\Permission as PermissionContract;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $superuser = new Role();
        $superuser->fill([
            'name' => config('permission.roles.superuser'),
            'label' => [
                'it' => 'Superutente',
                'en' => 'Superuser'
            ],
        ]);
        $superuser->save();

        $admin = new Role();
        $admin->fill([
            'name' => config('permission.roles.admin'),
            'label' => [
                'it' => 'Amministratore',
                'en' => 'Administrator'
            ],
        ]);
        $admin->save();

        $operator = new Role();
        $operator->fill([
            'name' => config('permission.roles.operator'),
            'label' => [
                'it' => 'Operatore',
                'en' => 'Operator'
            ],
        ]);
        $operator->save();
    }
}
