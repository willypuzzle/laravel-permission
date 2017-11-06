<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Exceptions\PermissionAlreadyExists;
use Idsign\Permission\Middlewares\CrudMiddleware;
use Idsign\Permission\Models\Permission;
use Idsign\Permission\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Idsign\Permission\Middlewares\RoleMiddleware;
use Idsign\Permission\Exceptions\UnauthorizedException;
use Idsign\Permission\Middlewares\PermissionMiddleware;

class MiddlewareTest extends TestCase
{
    protected $roleMiddleware;
    protected $permissionMiddleware;
    protected $crudMiddleware;

    public function setUp()
    {
        parent::setUp();

        $this->roleMiddleware = new RoleMiddleware($this->app);

        $this->permissionMiddleware = new PermissionMiddleware($this->app);

        $this->crudMiddleware = new CrudMiddleware($this->app);
    }

    /** @test */
    public function a_guest_cannot_access_a_route_protected_by_the_role_middleware()
    {
        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, 'testRole'
            ), 403);
    }

    /** @test */
    public function a_user_can_access_a_route_protected_by_role_middleware_if_have_this_role()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole('testRole');

        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, 'testRole'
            ), 200);
    }

    /** @test */
    public function a_user_can_access_a_route_protected_by_this_role_middleware_if_have_one_of_the_roles()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole('testRole');

        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, 'testRole|testRole2'
            ), 200);

        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, ['testRole2', 'testRole']
            ), 200);
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_the_role_middleware_if_have_a_different_role()
    {
        Auth::login($this->testUser);

        $this->testUser->assignRole(['testRole']);

        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, 'testRole2'
            ), 403);
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_role_middleware_if_have_not_roles()
    {
        Auth::login($this->testUser);

        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, 'testRole|testRole2'
            ), 403);
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_role_middleware_if_role_is_undefined()
    {
        Auth::login($this->testUser);

        $this->assertEquals(
            $this->runMiddleware(
                $this->roleMiddleware, ''
            ), 403);
    }

    /** @test */
    public function a_guest_cannot_access_a_route_protected_by_the_permission_middleware()
    {
        $this->assertEquals(
            $this->runMiddleware(
                $this->permissionMiddleware, 'edit-articles:blog'
            ), 403);
    }

    /** @test */
    public function a_user_can_access_a_route_protected_by_permission_middleware_if_have_this_permission()
    {
        Auth::login($this->testUser);

        $this->testUser->givePermissionTo('edit-articles', 'blog');

        $this->assertEquals(
            $this->runMiddleware(
                $this->permissionMiddleware, 'edit-articles:blog'
            ), 200);
    }

    /** @test */
    public function a_user_can_access_a_route_protected_by_this_permission_middleware_if_have_one_of_the_permissions()
    {
        Auth::login($this->testUser);

        $this->testUser->givePermissionTo('edit-articles', 'blog');

        $this->assertEquals(
            $this->runMiddleware(
                $this->permissionMiddleware, 'edit-news:blog|edit-articles:blog'
            ), 200);

        $this->assertEquals(
            $this->runMiddleware(
                $this->permissionMiddleware, ['edit-news:blog', 'edit-articles:blog']
            ), 200);
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_the_permission_middleware_if_have_a_different_permission()
    {
        Auth::login($this->testUser);

        $this->testUser->givePermissionTo('edit-articles', 'blog');

        $this->assertEquals(
            $this->runMiddleware(
                $this->permissionMiddleware, 'edit-news:blog'
            ), 403);
    }

    /** @test */
    public function a_user_cannot_access_a_route_protected_by_permission_middleware_if_have_not_permissions()
    {
        Auth::login($this->testUser);

        $this->assertEquals(
            $this->runMiddleware(
                $this->permissionMiddleware, 'edit-articles:blog|edit-news:blog'
            ), 403);
    }

    /** @test */
    public function a_user_can_access_to_crud_when_it_have_crud_permissions()
    {
        Auth::login($this->testUser);

        list($routes, $permissions) = $this->getCrudStuff();

        $section = Section::create([
            'name' => 'blogx'
        ]);

        foreach ($permissions as $permission){
            try {
                $permissionModel = Permission::create([
                    'name' => $permission
                ]);

                $this->testUser->givePermissionTo($permissionModel, $section);
            }catch (PermissionAlreadyExists $ex){

            }
        }

        foreach ($routes as $route){
            $this->assertEquals(
                $this->runMiddleware(
                    $this->crudMiddleware, 'blogx', $route
                ), 200);
        }
    }

    /** @test */
    public function a_user_cannot_access_to_crud_when_it_doesnt_have_crud_permissions()
    {
        Auth::login($this->testUser);

        list($routes, $permissions) = $this->getCrudStuff();

        Section::create([
            'name' => 'blogx'
        ]);

        foreach ($permissions as $permission){
            try {
                Permission::create([
                    'name' => $permission
                ]);
            }catch (PermissionAlreadyExists $ex){

            }
        }

        foreach ($routes as $route){
            $this->assertEquals(
                $this->runMiddleware(
                    $this->crudMiddleware, 'blogx', $route
                ), 403);
        }
    }

    /** @test */
    public function a_user_can_access_to_not_crud_when_it_doesnt_have_crud_permissions_but_middleware_is_set_to_nullable()
    {
        Auth::login($this->testUser);

        list($routes, $permissions) = $this->getCrudStuff();

        Section::create([
            'name' => 'blogx'
        ]);

        foreach ($permissions as $permission){
            try {
                Permission::create([
                    'name' => $permission
                ]);
            }catch (PermissionAlreadyExists $ex){

            }
        }

        foreach ($routes as $route){
            $this->assertEquals(
                $this->runMiddleware(
                    $this->crudMiddleware, 'blogx', $route.".dummy"
                ), 200);
        }
    }

    /** @test */
    public function a_user_cannot_access_to_not_crud_when_it_doesnt_have_crud_permissions_but_middleware_is_set_to_not_nullable()
    {
        Auth::login($this->testUser);

        list($routes, $permissions) = $this->getCrudStuff();

        Section::create([
            'name' => 'blogx'
        ]);

        foreach ($permissions as $permission){
            try {
                Permission::create([
                    'name' => $permission
                ]);
            }catch (PermissionAlreadyExists $ex){

            }
        }

        foreach ($routes as $route){
            $this->assertEquals(
                $this->runMiddleware(
                    $this->crudMiddleware, 'blogx,not_nullable', $route.".dummy"
                ), 403);
        }
    }

    protected function getCrudStuff() : array
    {
        $data = config('permission.crud');

        $crudRoutes = array_keys($data);
        $crudPermissions = array_flatten(array_values($data));

        $crudRoutes = array_map(function($parRoute){
                return "dummy.{$parRoute}";
            }, $crudRoutes);

        return [$crudRoutes, $crudPermissions];
    }

    protected function runMiddleware($middleware, $parameter, $routeName = null)
    {
        $request = new Request();
        if($routeName){
            $route = new Route([], '/', function (){});
            $route->name($routeName);
            $request->setRouteResolver(function () use ($route){
                return $route;
            });
        }
        try {
            return $middleware->handle($request, function () {
                return (new Response())->setContent('<html></html>');
            }, $parameter)->status();
        } catch (UnauthorizedException $e) {
            return $e->getStatusCode();
        }
    }
}
