<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Contracts\Role;
use Idsign\Permission\Models\Permission;
use Idsign\Permission\Exceptions\GuardDoesNotMatch;
use Idsign\Permission\Exceptions\RoleAlreadyExists;
use Idsign\Permission\Exceptions\PermissionDoesNotExist;
use Idsign\Permission\Models\Section;
use Illuminate\Support\Facades\DB;

class RoleTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Permission::create(['name' => 'other-permission']);

        Permission::create(['name' => 'wrong-guard-permission', 'guard_name' => 'admin']);
    }

    /** @test */
    public function it_has_user_models_of_the_right_class()
    {
        $this->testAdmin->assignRole($this->testAdminRole);

        $this->testUser->assignRole($this->testUserRole);

        $this->assertCount(1, $this->testUserRole->users);
        $this->assertTrue($this->testUserRole->users->first()->is($this->testUser));
        $this->assertInstanceOf(User::class, $this->testUserRole->users->first());
    }

    /** @test */
    public function it_throws_an_exception_when_the_role_already_exists()
    {
        $this->expectException(RoleAlreadyExists::class);

        app(Role::class)->create(['name' => 'test-role']);
        app(Role::class)->create(['name' => 'test-role']);
    }

    /** @test */
    public function it_can_be_given_a_permission()
    {
        $this->testUserRole->givePermissionTo('edit-articles', 'blog');

        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));
    }

    /** @test */
    public function it_throws_an_exception_when_given_a_permission_that_does_not_exist()
    {
        $this->expectException(PermissionDoesNotExist::class);

        $this->testUserRole->givePermissionTo('create-evil-empire', 'blog');
    }

    /** @test */
    public function it_throws_an_exception_when_given_a_permission_that_belongs_to_another_guard()
    {
        $this->expectException(PermissionDoesNotExist::class);

        $this->testUserRole->givePermissionTo('admin-permission', 'blog');

        $this->expectException(GuardDoesNotMatch::class);

        $this->testUserRole->givePermissionTo($this->testAdminPermission, $this->testAdminSection);
    }

    /** @test */
    public function it_can_be_given_multiple_permissions_using_an_array()
    {
        $this->testUserRole->givePermissionTo(['edit-articles', 'edit-news'], 'blog');

        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));
        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-news', 'blog'));
    }

    /** @test */
    public function it_can_be_given_multiple_permissions_using_multiple_arguments()
    {
        $this->testUserRole->givePermissionTo(['edit-articles', 'edit-news'], 'blog');

        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));
        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-news', 'blog'));
    }

    /** @test */
    public function it_can_sync_permissions()
    {
        $this->testUserRole->givePermissionTo('edit-articles', 'blog');

        $this->testUserRole->syncPermissions('edit-news', 'blog');

        $this->assertFalse($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));

        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-news', 'blog'));
    }

    /** @test */
    public function it_throws_an_exception_when_syncing_permissions_that_do_not_exist()
    {
        $this->testUserRole->givePermissionTo('edit-articles', 'blog');

        $this->expectException(PermissionDoesNotExist::class);

        $this->testUserRole->syncPermissions('permission-does-not-exist', 'blog');
    }

    /** @test */
    public function it_throws_an_exception_when_syncing_permissions_that_belong_to_a_different_guard()
    {
        $this->testUserRole->givePermissionTo('edit-articles', 'blog');

        $this->expectException(PermissionDoesNotExist::class);

        $this->testUserRole->syncPermissions('admin-permission', 'blog');

        $this->expectException(GuardDoesNotMatch::class);

        $this->testUserRole->syncPermissions($this->testAdminPermission, $this->testAdminSection);
    }

    /** @test */
    public function it_will_remove_all_permissions_when_passing_an_empty_array_to_sync_permissions()
    {
        $this->testUserRole->givePermissionTo('edit-articles', 'blog');

        $this->testUserRole->givePermissionTo('edit-news', 'blog');

        $this->testUserRole->syncPermissions([], 'blog');

        $this->assertFalse($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));

        $this->assertFalse($this->testUserRole->hasPermissionTo('edit-news', 'blog'));
    }

    /** @test */
    public function it_can_revoked_a_permission()
    {
        $this->testUserRole->givePermissionTo('edit-articles', 'blog');

        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));

        $this->testUserRole->revokePermissionTo('edit-articles', 'blog');

        $this->testUserRole = $this->testUserRole->fresh();

        $this->assertFalse($this->testUserRole->hasPermissionTo('edit-articles', 'blog'));
    }

    /** @test */
    public function it_can_be_given_a_permission_using_objects()
    {
        $this->testUserRole->givePermissionTo($this->testUserPermission, $this->testUserSection);

        $this->assertTrue($this->testUserRole->hasPermissionTo($this->testUserPermission, $this->testUserSection));
    }

    /** @test */
    public function it_returns_false_if_it_does_not_have_the_permission()
    {
        $this->assertFalse($this->testUserRole->hasPermissionTo('other-permission', 'blog'));
    }

    /** @test */
    public function it_throws_an_exception_if_the_permission_does_not_exist()
    {
        $this->expectException(PermissionDoesNotExist::class);

        $this->testUserRole->hasPermissionTo('doesnt-exist', 'blog');
    }

    /** @test */
    public function it_returns_false_if_it_does_not_have_a_permission_object()
    {
        $permission = app(Permission::class)->findByName('other-permission');

        $this->assertFalse($this->testUserRole->hasPermissionTo($permission, 'blog'));
    }

    /** @test */
    public function it_throws_an_exception_when_a_permission_of_the_wrong_guard_is_passed_in()
    {
        $this->expectException(GuardDoesNotMatch::class);

        $permission = app(Permission::class)->findByName('wrong-guard-permission', 'admin');

        $this->testUserRole->hasPermissionTo($permission, 'blog');
    }

    /** @test */
    public function it_belongs_to_a_guard()
    {
        $role = app(Role::class)->create(['name' => 'admin', 'guard_name' => 'admin']);

        $this->assertEquals('admin', $role->guard_name);
    }

    /** @test */
    public function it_belongs_to_the_default_guard_by_default()
    {
        $this->assertEquals($this->app['config']->get('auth.defaults.guard'), $this->testUserRole->guard_name);
    }

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
        $role->givePermissionTo($permission, $section);

        $roleId = $role->id;

        $role->delete();

        $this->assertCount(0, DB::table(config('permission.table_names.role_has_permissions'))->where('role_id', $roleId)->get());
        $this->assertCount(0, DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $roleId)->get());

    }
}
