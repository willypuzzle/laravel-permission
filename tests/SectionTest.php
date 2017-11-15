<?php

namespace Idsign\Permission\Test;

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

        $this->testUser->assignRole($role);
        $this->testUser->givePermissionTo($permission, $section);
        $role->givePermissionTo($permission, $section);

        $sectionId = $section->id;

        $section->delete();

        $this->assertCount(0, DB::table(config('permission.table_names.role_has_permissions'))->where('section_id', $sectionId)->get());
        $this->assertCount(0, DB::table(config('permission.table_names.model_has_permissions'))->where('section_id', $sectionId)->get());
        $this->assertCount(0, DB::table(config('permission.table_names.model_has_roles'))->where('section_id', $sectionId)->get());

    }
}
