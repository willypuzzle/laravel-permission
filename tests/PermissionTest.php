<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Contracts\Permission;
use Idsign\Permission\Exceptions\PermissionAlreadyExists;
use Idsign\Permission\Models\Container;
use Idsign\Permission\Models\Section as SectionModel;
use Idsign\Permission\Models\Permission as PermissionModel;
use Idsign\Permission\Models\Role as RoleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionTest extends TestCase
{
    /** @test */
    public function it_throws_an_exception_when_the_permission_already_exists()
    {
        $this->expectException(PermissionAlreadyExists::class);

        app(Permission::class)->create(['name' => 'test-permission']);
        app(Permission::class)->create(['name' => 'test-permission']);
    }

    /** @test */
    public function it_belongs_to_a_guard()
    {
        $permission = app(Permission::class)->create(['name' => 'can-edit', 'guard_name' => 'admin']);

        $this->assertEquals('admin', $permission->guard_name);
    }

    /** @test */
    public function it_belongs_to_the_default_guard_by_default()
    {
        $this->assertEquals($this->app['config']->get('auth.defaults.guard'), $this->testUserPermission->guard_name);
    }

    /** @test */
    public function it_has_user_models_of_the_right_class()
    {
        $this->testAdmin->givePermissionTo($this->testAdminPermission, $this->testAdminSection, $this->testAdminContainer);

        $this->testUser->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->assertCount(1, $this->testUserPermission->users);
        $this->assertTrue($this->testUserPermission->users->first()->is($this->testUser));
        $this->assertInstanceOf(User::class, $this->testUserPermission->users->first());
    }

    /** @test */
    public function user_has_a_correct_permission_tree()
    {
        $section1 = SectionModel::create(['name' => 'section1']);

        $section2 = SectionModel::create(['name' => 'section2']);

        $section3 = SectionModel::create(['name' => 'section3']);

        $section4 = SectionModel::create(['name' => 'section4']);

        $section4sub1 = SectionModel::create(['name' => 'section4sub1']);

        $section4->children()->save($section4sub1);

        $permission1_1 = PermissionModel::create(['name' => 'permission1.1']);

        $permission1_2 = PermissionModel::create(['name' => 'permission1.2']);

        $permission2 = PermissionModel::create(['name' => 'permission2']);

        $permission3 = PermissionModel::create(['name' => 'permission3']);

        $permission4_1 = PermissionModel::create(['name' => 'permission4.1']);

        $permission4_2 = PermissionModel::create(['name' => 'permission4.2']);

        $permission4sub1_1 = PermissionModel::create(['name' => 'permission4sub1_1']);

        $crossPermissionSec1_2 = PermissionModel::create(['name' => 'cross-permission-sec1_2']);

        $crossPermissionSec2_4 = PermissionModel::create(['name' => 'cross-permission-sec2_4']);

        $role1 = RoleModel::create(['name' => 'role1']);

        $role2 = RoleModel::create(['name' => 'role2']);

        $container1 = Container::create(['name' => 'container1']);

        $container1->sections()->saveMany([
            $section3,
            $section4sub1,
            $section1,
            $section2,
            $section4
        ]);

        $role1->givePermissionTo($permission3, $section3, $container1);
        $role1->givePermissionTo($permission4sub1_1, $section4sub1, $container1);
        $role2->givePermissionTo($permission4_1, $section4, $container1);
        $role2->givePermissionTo($permission4_2, $section4, $container1);

        $user = $this->testUser;

        $user->assignRole($role1, $role2);

        $user->givePermissionTo($permission1_1, $section1, $container1);
        $user->givePermissionTo($permission1_2, $section1, $container1);
        $user->givePermissionTo($permission2, $section2, $container1);

        $user->givePermissionTo($crossPermissionSec1_2, $section1, $container1);
        $user->givePermissionTo($crossPermissionSec1_2, $section2, $container1);

        $user->givePermissionTo($crossPermissionSec2_4, $section2, $container1);
        $role2->givePermissionTo($crossPermissionSec2_4, $section4, $container1);

        $tree = $user->getPermissionsTree('container1');

        $this->assertTrue(isset($tree['section1']['permissions']['permission1.1']));
        $this->assertTrue(isset($tree['section1']['permissions']['permission1.2']));
        $this->assertTrue(isset($tree['section2']['permissions']['permission2']));
        $this->assertTrue(isset($tree['section3']['permissions']['permission3']));
        $this->assertTrue(isset($tree['section4']['permissions']['permission4.1']));
        $this->assertTrue(isset($tree['section4']['children']['section4sub1']['permissions']['permission4sub1_1']));
        $this->assertTrue(isset($tree['section4']['permissions']['permission4.2']));
        $this->assertTrue(isset($tree['section1']['permissions']['cross-permission-sec1_2']));
        $this->assertTrue(isset($tree['section2']['permissions']['cross-permission-sec1_2']));
        $this->assertTrue(isset($tree['section2']['permissions']['cross-permission-sec2_4']));
        $this->assertTrue(isset($tree['section4']['permissions']['cross-permission-sec2_4']));

        $this->assertFalse(isset($tree['section3']['permissions']['cross-permission-sec2_4']));
        $this->assertFalse(isset($tree['section1']['permissions']['permission2']));
    }

    /** @test */
    public function test_if_model_detach_on_delete_works()
    {
        $permission = new PermissionModel(['name' => 'john']);
        $permission->save();

        $section = new SectionModel(['name' => 'bio']);
        $section->save();

        $container = new Container(['name' => 'cic']);
        $container->save();

        $this->testUser->givePermissionTo($permission,$section,$container);
        $this->testUserRole->givePermissionTo($permission, $section,$container);

        $permissionId = $permission->id;

        $permission->delete();

        $this->assertCount(0, DB::table(config('permission.table_names.role_has_permissions'))->where('permission_id', $permissionId)->get());
        $this->assertCount(0, DB::table(config('permission.table_names.model_has_permissions'))->where('permission_id', $permissionId)->get());

    }
}
