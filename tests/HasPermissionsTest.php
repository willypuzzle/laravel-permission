<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Contracts\Permission;
use Idsign\Permission\Contracts\Role;
use Idsign\Permission\Contracts\Section;
use Idsign\Permission\Exceptions\GuardDoesNotMatch;
use Idsign\Permission\Exceptions\PermissionDoesNotExist;
use Idsign\Permission\Libraries\Config;

class HasPermissionsTest extends TestCase
{
    /** @test */
    public function it_can_assign_a_permission_to_a_user()
    {
        $this->testUser->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

    /** @test */
    public function it_doesnt_allow_a_permission_to_a_user_when_user_is_disabled()
    {
        $this->testUser->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $stateField = Config::userStateFieldName();

        $this->testUser->$stateField = Config::userStateDisabled()[0];

        $this->testUser->save();

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

    /** @test */
    public function it_doesnt_allow_a_permission_to_a_user_when_role_is_disabled()
    {
        $this->testUserRole->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->testUserRole->state = Role::DISABLED;

        $this->testUserRole->save();

        $this->testUser->syncRoles($this->testUserRole);

        $this->testUser->save();

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

    /** @test */
    public function it_doesnt_allow_a_permission_to_a_user_when_permission_is_disabled_via_role()
    {
        $this->testUserPermission->state = Permission::DISABLED;

        $this->testUserPermission->save();

        $this->testUserRole->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->testUser->syncRoles($this->testUserRole);

        $this->testUser->save();

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

    /** @test */
    public function it_doesnt_allow_a_permission_to_a_user_when_permission_is_disabled_via_permission()
    {
        $this->testUserPermission->state = Permission::DISABLED;

        $this->testUserPermission->save();

        $this->testUser->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

    /** @test */
    public function it_doesnt_allow_a_permission_to_a_user_when_section_is_disabled()
    {
        $this->testUser->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->testUserSection->state = Section::DISABLED;

        $this->testUserSection->save();

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

    /** @test */
    public function it_throws_an_exception_when_assigning_a_permission_that_does_not_exist()
    {
        $this->expectException(PermissionDoesNotExist::class);

        $this->testUser->givePermissionTo('permission-does-not-exist', 'blog', 'idsign');
    }

    /** @test */
    public function it_throws_an_exception_when_assigning_a_permission_to_a_user_from_a_different_guard()
    {
        $this->expectException(GuardDoesNotMatch::class);

        $this->testUser->givePermissionTo($this->testAdminPermission, $this->testUserSection, $this->testUserContainer);

        $this->expectException(PermissionDoesNotExist::class);

        $this->testUser->givePermissionTo('admin-permission', 'blog', 'idsign');
    }

    /** @test */
    public function it_can_revoke_a_permission_from_a_user()
    {
        $this->testUser->givePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));

        $this->testUser->revokePermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer);

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission, $this->testUserSection, $this->testUserContainer));
    }

//    /** @test */
//    public function it_can_scope_users_using_a_string()
//    {
//        $user1 = User::create(['email' => 'user1@test.com']);
//        $user2 = User::create(['email' => 'user2@test.com']);
//        $user1->givePermissionTo(['edit-articles', 'edit-news'], 'blog');
//        $this->testUserRole->givePermissionTo('edit-articles', 'blog');
//        $user2->assignRole('testRole');
//
//        $scopedUsers1 = User::permission('edit-articles')->get();
//        $scopedUsers2 = User::permission(['edit-news'])->get();
//
//        $this->assertEquals($scopedUsers1->count(), 2);
//        $this->assertEquals($scopedUsers2->count(), 1);
//    }

//    /** @test */
//    public function it_can_scope_users_using_an_array()
//    {
//        $user1 = User::create(['email' => 'user1@test.com']);
//        $user2 = User::create(['email' => 'user2@test.com']);
//        $user1->givePermissionTo(['edit-articles', 'edit-news']);
//        $this->testUserRole->givePermissionTo('edit-articles');
//        $user2->assignRole('testRole');
//
//        $scopedUsers1 = User::permission(['edit-articles', 'edit-news'])->get();
//        $scopedUsers2 = User::permission(['edit-news'])->get();
//
//        $this->assertEquals($scopedUsers1->count(), 2);
//        $this->assertEquals($scopedUsers2->count(), 1);
//    }
//
//    /** @test */
//    public function it_can_scope_users_using_a_collection()
//    {
//        $user1 = User::create(['email' => 'user1@test.com']);
//        $user2 = User::create(['email' => 'user2@test.com']);
//        $user1->givePermissionTo(['edit-articles', 'edit-news']);
//        $this->testUserRole->givePermissionTo('edit-articles');
//        $user2->assignRole('testRole');
//
//        $scopedUsers1 = User::permission(collect(['edit-articles', 'edit-news']))->get();
//        $scopedUsers2 = User::permission(collect(['edit-news']))->get();
//
//        $this->assertEquals($scopedUsers1->count(), 2);
//        $this->assertEquals($scopedUsers2->count(), 1);
//    }
//
//    /** @test */
//    public function it_can_scope_users_using_an_object()
//    {
//        $user1 = User::create(['email' => 'user1@test.com']);
//        $user1->givePermissionTo($this->testUserPermission->name);
//
//        $scopedUsers1 = User::permission($this->testUserPermission)->get();
//        $scopedUsers2 = User::permission([$this->testUserPermission])->get();
//
//        $this->assertEquals($scopedUsers1->count(), 1);
//        $this->assertEquals($scopedUsers2->count(), 1);
//    }
//
//    /** @test */
//    public function it_can_scope_users_without_permissions_only_role()
//    {
//        $user1 = User::create(['email' => 'user1@test.com']);
//        $user2 = User::create(['email' => 'user2@test.com']);
//        $this->testUserRole->givePermissionTo('edit-articles');
//        $user1->assignRole('testRole');
//        $user2->assignRole('testRole');
//
//        $scopedUsers = User::permission('edit-articles')->get();
//
//        $this->assertEquals($scopedUsers->count(), 2);
//    }
//
//    /** @test */
//    public function it_can_scope_users_without_permissions_only_permission()
//    {
//        $user1 = User::create(['email' => 'user1@test.com']);
//        $user2 = User::create(['email' => 'user2@test.com']);
//        $user1->givePermissionTo(['edit-news']);
//        $user2->givePermissionTo(['edit-articles', 'edit-news']);
//
//        $scopedUsers = User::permission('edit-news')->get();
//
//        $this->assertEquals($scopedUsers->count(), 2);
//    }
//
//    /** @test */
//    public function it_throws_an_exception_when_trying_to_scope_a_permission_from_another_guard()
//    {
//        $this->expectException(PermissionDoesNotExist::class);
//
//        User::permission('testAdminPermission')->get();
//
//        $this->expectException(GuardDoesNotMatch::class);
//
//        User::permission($this->testAdminPermission)->get();
//    }

    /** @test */
    public function it_doesnt_detach_permissions_when_soft_deleting()
    {
        $user = SoftDeletingUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo(['edit-news'], 'blog', 'idsign');
        $user->delete();

        $user = SoftDeletingUser::withTrashed()->find($user->id);

        $this->assertTrue($user->hasPermissionTo('edit-news', 'blog', 'idsign'));
    }
}
