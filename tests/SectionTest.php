<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Libraries\Config;
use Idsign\Permission\Models\Container;
use Idsign\Permission\Models\Permission;
use Idsign\Permission\Models\Section;
use Illuminate\Support\Facades\DB;

class SectionTest extends TestCase
{
    /** @test */
    public function test_if_model_detach_on_delete_works()
    {
        $role = new \Idsign\Permission\Models\Role(['name' => 'john']);
        $role->save();

        $permission = new Permission(['name' => 'Luke']);
        $permission->save();

        $section = new Section(['name' => 'bio']);
        $section->save();

        $container = new Container(['name' => 'pippo']);
        $container->save();

        $this->testUser->assignRole($role);
        $this->testUser->givePermissionTo($permission, $section, $container);
        $role->givePermissionTo($permission, $section, $container);

        $sectionId = $section->id;

        $section->delete();

        $this->assertCount(0, DB::table(Config::roleHasPermissionsTable())->where('section_id', $sectionId)->get());
        $this->assertCount(0, DB::table(Config::modelHasPermissionsTable())->where('section_id', $sectionId)->get());
        $this->assertCount(0, DB::table(Config::modelHasRolesTable())->where('section_id', $sectionId)->get());

    }
}
