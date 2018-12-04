<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Contracts\Container;
use Monolog\Handler\TestHandler;
use Idsign\Permission\Contracts\Role;
use Illuminate\Database\Schema\Blueprint;
use Idsign\Permission\PermissionRegistrar;
use Idsign\Permission\Contracts\Permission;
use Idsign\Permission\Contracts\Section;
use Orchestra\Testbench\TestCase as Orchestra;
use Idsign\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @var \Idsign\Permission\Test\User */
    protected $testUser;

    /** @var \Idsign\Permission\Test\Admin */
    protected $testAdmin;

    /** @var \Idsign\Permission\Models\Role */
    protected $testUserRole;

    /** @var \Idsign\Permission\Models\Role */
    protected $testAdminRole;

    /** @var \Idsign\Permission\Models\Permission */
    protected $testUserPermission;

    /** @var \Idsign\Permission\Models\Permission */
    protected $testAdminPermission;

    /** @var \Idsign\Permission\Models\Section */
    protected $testUserSection;

    /** @var \Idsign\Permission\Models\Section */
    protected $testAdminSection;

    /** @var \Idsign\Permission\Models\Container */
    protected $testUserContainer;

    /** @var \Idsign\Permission\Models\Container */
    protected $testAdminContainer;

    public function setUp()
    {
        parent::setUp();

        $this->setUpDatabase($this->app);

        $this->testUser = User::first();
        $this->testUserRole = app(Role::class)->find(1);
        $this->testUserPermission = app(Permission::class)->find(1);
        $this->testUserSection = app(Section::class)->find(1);
        $this->testUserContainer = app(Container::class)->find(1);

        $this->testAdmin = Admin::first();
        $this->testAdminRole = app(Role::class)->find(3);
        $this->testAdminPermission = app(Permission::class)->find(3);
        $this->testAdminSection = app(Section::class)->find(2);
        $this->testAdminContainer = app(Container::class)->find(2);

        $this->clearLogTestHandler();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            PermissionServiceProvider::class,
        ];
    }

    /**
     * Set up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('view.paths', [__DIR__.'/resources/views']);

        // Set-up admin guard
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
        $app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);

        // Use test User model for users provider
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('permission.user.model.api.model', User::class);
        $app['config']->set('permission.user.model.web.model', User::class);

        $app['log']->getMonolog()->pushHandler(new TestHandler());
    }

    /**
     * Set up the database.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->integer('state')->default(1)->comment('0 = disabled, 1 = enabled');
            $table->softDeletes();
        });

        $app['db']->connection()->getSchemaBuilder()->create('admins', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->integer('state')->default(1)->comment('0 = disabled, 1 = enabled');
        });

        include_once __DIR__.'/../database/migrations/create_permission_tables.php.stub';

        (new \CreatePermissionTables())->up();

        User::create(['email' => 'test@user.com']);
        Admin::create(['email' => 'admin@user.com']);
        $app[Role::class]->create(['name' => 'testRole', 'state' => Role::ENABLED]);
        $app[Role::class]->create(['name' => 'testRole2', 'state' => Role::ENABLED]);
        $app[Role::class]->create(['name' => 'testAdminRole', 'guard_name' => 'admin', 'state' => Role::ENABLED]);
        $app[Permission::class]->create(['name' => 'edit-articles']);
        $app[Permission::class]->create(['name' => 'edit-news']);
        $app[Permission::class]->create(['name' => 'admin-permission', 'guard_name' => 'admin']);

        $app[Section::class]->create(['name' => 'blog']);
        $app[Section::class]->create(['name' => 'blog', 'guard_name' => 'admin']);

        $app[Container::class]->create(['name' => 'idsign']);
        $app[Container::class]->create(['name' => 'idsign', 'guard_name' => 'admin']);
    }

    /**
     * Reload the permissions.
     */
    protected function reloadPermissions()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Refresh the testuser.
     */
    public function refreshTestUser()
    {
        $this->testUser = $this->testUser->fresh();
    }

    /**
     * Refresh the testAdmin.
     */
    public function refreshTestAdmin()
    {
        $this->testAdmin = $this->testAdmin->fresh();
    }

    protected function clearLogTestHandler()
    {
        collect($this->app['log']->getMonolog()->getHandlers())->filter(function ($handler) {
            return $handler instanceof TestHandler;
        })->first(function (TestHandler $handler) {
            $handler->clear();
        });
    }

    protected function assertNotLogged($message, $level)
    {
        $this->assertFalse($this->hasLog($message, $level), "Found `{$message}` in the logs.");
    }

    protected function assertLogged($message, $level)
    {
        $this->assertTrue($this->hasLog($message, $level), "Couldn't find `{$message}` in the logs.");
    }

    /**
     * @param $message
     * @param $level
     *
     * @return bool
     */
    protected function hasLog($message, $level)
    {
        return collect($this->app['log']->getMonolog()->getHandlers())->filter(function ($handler) use ($message, $level) {
            return $handler instanceof TestHandler
                && $handler->hasRecordThatContains($message, $level);
        })->count() > 0;
    }
}
