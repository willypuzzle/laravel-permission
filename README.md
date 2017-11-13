# Associate users with permissions and roles

This package allows you to manage user permissions and roles in a database.
This is a modified version of [spatie/laravel-permission](https://github.com/spatie/laravel-permission)

Once installed you can do stuff like this:

```php
// Adding permissions to a user dividing them by section
$user->givePermissionTo('edit articles', 'blog');

// Adding permissions via a role
$user->assignRole('writer');

and always by section
$role->givePermissionTo('edit articles', 'blog');
```

If you're using multiple guards we've got you covered as well. Every guard will have its own set of permissions and roles that can be assigned to the guard's users. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

Because all permissions will be registered on [Laravel's gate](https://laravel.com/docs/5.5/authorization), you can test if a user has a permission with Laravel's default `can` function:

```php
$arguments['section'] = 'blog';
$user->can('edit articles', $arguments);
```

To remember that for permission associated to role or user section is always mandatory.


## Installation

This package can be used in Laravel 5.4 or higher.

You can install the package via composer:

``` bash
composer require willypuzzle/laravel-permission
```

In Laravel 5.5 the service provider will automatically get registered. In older versions of the framework just add the service provider in `config/app.php` file:

```php
'providers' => [
    // ...
    Idsign\Permission\PermissionServiceProvider::class,
];
```

You can publish [the migration](https://github.com/willypuzzle/laravel-permission/blob/master/database/migrations/create_permission_tables.php.stub) with:

```bash
php artisan vendor:publish --provider="Idsign\Permission\PermissionServiceProvider" --tag="migrations"
```

After the migration has been published you can create the role- and permission-tables by running the migrations:

```bash
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Idsign\Permission\PermissionServiceProvider" --tag="config"
```

When published, [the `config/permission.php` config file](https://github.com/willypuzzle/laravel-permission/blob/master/config/permission.php) contains:

```php
return [

    'models' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Idsign\Permission\Contracts\Permission` contract.
         */

        'permission' => Idsign\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Idsign\Permission\Contracts\Role` contract.
         */

        'role' => Idsign\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'roles' => 'roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'permissions' => 'permissions',
        
        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your sections. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */
        
        'sections' => 'sections',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models roles. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    /*
     * By default all permissions will be cached for 24 hours unless a permission or
     * role is updated. Then the cache will be flushed immediately.
     */

    'cache_expiration_time' => 60 * 24,
];
```

## Usage

First, add the `Idsign\Permission\Traits\HasRoles` trait to your `User` model(s):

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Idsign\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // ...
}
```

> - note that if you need to use `HasRoles` trait with another model ex.`Page` you will also need to add `protected $guard_name = 'web';` as well to that model or you would get an error
>
>```php
>use Illuminate\Database\Eloquent\Model;
>use Idsign\Permission\Traits\HasRoles;
>
>class Page extends Model
>{
>    use HasRoles;
>
>    protected $guard_name = 'web'; // or whatever guard you want to use
>
>    // ...
>}
>```

This package allows for users to be associated with permissions and roles but by sections. Every role is associated with multiple sections and every section for that role is associated with permissions.
A `Role`, `Section` and a `Permission` are regular Eloquent models. They require a `name` and can be created like this:

```php
use Idsign\Permission\Models\Role;
use Idsign\Permission\Models\Permission;
use Idsign\Permission\Models\Section;

$role = Role::create(['name' => 'writer']);
$permission = Permission::create(['name' => 'edit articles']);
$section = Section::create(['name' => 'blog']);
```

If you're using multiple guards the `guard_name` attribute needs to be set as well. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

The `HasRoles` trait adds Eloquent relationships to your models, which can be accessed directly or used as a base query:

```php
// get a list of all permissions directly assigned to the user
$permissions = $user->getPermissions('blog');//you have to pass the section

// get all permissions inherited by the user via roles
$permissions = $user->getAllPermissions('blog');

// get a collection of all defined roles
$roles = $user->getRoleNames(); // Returns a collection
```

The `HasRoles` trait also adds a `role` scope to your models to scope the query to certain roles:

```php
$users = User::role('writer')->get(); // Returns only users with the role 'writer'
```

The `role` scope can accept a string, a `\Idsign\Permission\Models\Role` object or an `\Illuminate\Support\Collection` object.

### Using "direct" permissions (see below to use both roles and permissions)

A permission can be given to any user:

```php
$user->givePermissionTo('edit articles', 'blog');//always indicate the section

// You may also pass an array
$user->givePermissionTo(['edit articles', 'delete articles'], 'blog');
```

A permission can be revoked from a user:

```php
$user->revokePermissionTo('edit articles', 'blog');
```

Or revoke & add new permissions in one go:

```php
$user->syncPermissions(['edit articles', 'delete articles'], 'blog');
```

You can test if a user has a permission:

```php
$user->hasPermissionTo('edit articles', 'blog');
```

...or if a user has multiple permissions:

```php
$user->hasAnyPermission(['edit articles', 'publish articles', 'unpublish articles'], 'blog');
```

Saved permissions will be registered with the `Illuminate\Auth\Access\Gate` class for the default guard. So you can
test if a user has a permission with Laravel's default `can` function:

```php
$arguments['section'] = 'blog';
$user->can('edit articles', $arguments);
```

### Using permissions via roles

A role can be assigned to any user:

```php
$user->assignRole('writer');

// You can also assign multiple roles at once
$user->assignRole('writer', 'admin');
// or as an array
$user->assignRole(['writer', 'admin']);
```

A role can be removed from a user:

```php
$user->removeRole('writer');
```

Roles can also be synced:

```php
// All current roles will be removed from the user and replaced by the array given
$user->syncRoles(['writer', 'admin']);
```

You can determine if a user has a certain role:

```php
$user->hasRole('writer');
```

You can also determine if a user has any of a given list of roles:

```php
$user->hasAnyRole(Role::all());
```

You can also determine if a user has all of a given list of roles:

```php
$user->hasAllRoles(Role::all());
```

The `assignRole`, `hasRole`, `hasAnyRole`, `hasAllRoles`  and `removeRole` functions can accept a
 string, a `\Idsign\Permission\Models\Role` object or an `\Illuminate\Support\Collection` object.

A permission can be given to a role:

```php
$role->givePermissionTo('edit articles', 'blog');
```

You can determine if a role has a certain permission:

```php
$role->hasPermissionTo('edit articles', 'blog');
```

A permission can be revoked from a role:

```php
$role->revokePermissionTo('edit articles', 'blog');
```

The `givePermissionTo` and `revokePermissionTo` functions can accept a
string or a `Idsign\Permission\Models\Permission` object and a string or a `Idsign\Permission\Models\Section` as section.


Permissions are inherited from roles automatically. 
Additionally, individual permissions can be assigned to the user too. 
For instance:

```php
$role = Role::findByName('writer');
$role->givePermissionTo('edit articles', 'blog');

$user->assignRole('writer');

$user->givePermissionTo('delete articles', 'blog');
```

In the above example, a role is given permission to edit articles and this role is assigned to a user. 
Now the user can edit articles and additionally delete articles. The permission of 'delete articles' is the user's direct permission because it is assigned directly to them.
When we call `$user->hasDirectPermission('delete articles', 'blog')` it returns `true`, 
but `false` for `$user->hasDirectPermission('edit articles', 'blog')`.

This method is useful if one builds a form for setting permissions for roles and users in an application and wants to restrict or change inherited permissions of roles of the user, i.e. allowing to change only direct permissions of the user.

You can list all of these permissions:

```php
// Direct permissions
$user->getDirectPermissions('blog')

// Permissions inherited from the user's roles
$user->getPermissionsViaRoles('blog');

// All permissions which apply on the user (inherited and direct)
$user->getAllPermissions('blog');
```

All these responses are collections of `Idsign\Permission\Models\Permission` objects.

If we follow the previous example, the first response will be a collection with the `delete article` permission and 
the second will be a collection with the `edit article` permission and the third will contain both.

### Get Permissions' tree of a user
It could be useful to get the permission tree (permission divided by sections) of a certain user. In order to do this you can use the method $user->getPermissionsTree() of HasRole trait.

### Using Blade directives
This package also adds Blade directives to verify whether the currently logged in user has all or any of a given list of roles. 

Optionally you can pass in the `guard` that the check will be performed on as a second argument.

#### Blade and Roles
Test for a specific role:
```php
@role('writer')
    I am a writer!
@else
    I am not a writer...
@endrole
```
is the same as
```php
@hasrole('writer')
    I am a writer!
@else
    I am not a writer...
@endhasrole
```

Test for any role in a list:
```php
@hasanyrole($collectionOfRoles)
    I have one or more of these roles!
@else
    I have none of these roles...
@endhasanyrole
// or
@hasanyrole('writer|admin')
    I am either a writer or an admin or both!
@else
    I have none of these roles...
@endhasanyrole
```
Test for all roles:

```php
@hasallroles($collectionOfRoles)
    I have all of these roles!
@else
    I do not have all of these roles...
@endhasallroles
// or
@hasallroles('writer|admin')
    I am both a writer and an admin!
@else
    I do not have all of these roles...
@endhasallroles
```

#### Blade and Permissions
This package doesn't add any permission-specific Blade directives. Instead, use Laravel's native `@can` directive to check if a user has a certain permission.

```php
@can('edit articles')
  //
@endcan
```
or
```php
@if(auth()->user()->can('edit articles') && $some_other_condition)
  //
@endif
```

## Using multiple guards

When using the default Laravel auth configuration all of the above methods will work out of the box, no extra configuration required.

However, when using multiple guards they will act like namespaces for your permissions and roles. Meaning every guard has its own set of permissions and roles that can be assigned to their user model.

### Using permissions and roles with multiple guards

By default the default guard (`config('auth.defaults.guard')`) will be used as the guard for new permissions and roles. When creating permissions and roles for specific guards you'll have to specify their `guard_name` on the model:

```php
// Create a superadmin role for the admin users
$role = Role::create(['guard_name' => 'admin', 'name' => 'superadmin']);

// Define a `publish articles` permission for the admin users belonging to the admin guard
$permission = Permission::create(['guard_name' => 'admin', 'name' => 'publish articles']);

// Define a *different* `publish articles` permission for the regular users belonging to the web guard
$permission = Permission::create(['guard_name' => 'web', 'name' => 'publish articles']);
```

To check if a user has permission for a specific guard:

```php
$user->hasPermissionTo('publish articles', 'blog', 'admin');
```

### Assigning permissions and roles to guard users

You can use the same methods to assign permissions and roles to users as described above in [using permissions via roles](#using-permissions-via-roles). Just make sure the `guard_name` on the permission or role matches the guard of the user, otherwise a `GuardDoesNotMatch` exception will be thrown.

### Using blade directives with multiple guards

You can use all of the blade directives listed in [using blade directives](#using-blade-directives) by passing in the guard you wish to use as the second argument to the directive:

```php
@role('super-admin', 'admin')
    I am a super-admin!
@else
    I am not a super-admin...
@endrole
```

## Using a middleware

This package comes with `RoleMiddleware` and `PermissionMiddleware` middleware. You can add them inside your `app/Http/Kernel.php` file.

```php
protected $routeMiddleware = [
    // ...
    'role' => \Idsign\Permission\Middlewares\RoleMiddleware::class,
    'permission' => \Idsign\Permission\Middlewares\PermissionMiddleware::class,
];
```

Then you can protect your routes using middleware rules:

```php
Route::group(['middleware' => ['role:super-admin']], function () {
    //
});

Route::group(['middleware' => ['permission:publish articles:blog']], function () {
    //In case of permission Make sure to pass section separated by : or a exception will be raised.
});

Route::group(['middleware' => ['role:super-admin','permission:publish articles']], function () {
    //
});
```

Alternatively, you can separate multiple roles or permission with a `|` (pipe) character:

```php
Route::group(['middleware' => ['role:super-admin|writer']], function () {
    //
});

Route::group(['middleware' => ['permission:publish articles:blog|edit articles:blog']], function () {
    //
});
```

You can protect your controllers similarly, by setting desired middleware in the constructor:

```php
public function __construct()
{
    $this->middleware(['role:super-admin','permission:publish articles:blog|edit articles:blog']);
}
```

You can protect crud routes with \Idsign\Permission\Middlewares\CrudMiddleware, see config file for more informations.

### Catching role and permission failures
If you want to override the default `403` response, you can catch the `UnauthorizedException` using your app's exception handler:

```php
public function render($request, Exception $exception)
{
    if ($exception instanceof \Idsign\Permission\Exceptions\UnauthorizedException) {
        // Code here ...
    }

    return parent::render($request, $exception);
}

```

## State Management

### User

You can set parameters for user state manage (see config for more information). You can call isEnabled method (of HasRole trait) to check if a user is enabled.
If a User is disabled it couldn't be allowed to enter in any section for any role or permission.

State management is even for roles and permission, you have to set the state field in the database, use constant in Role and Permission contracts to retrieve the value.

When state is disabled in permission any request will be not allowed in that permission for every user that is linked at that permission.
When state is disable in role any request on permission linked to that role will be not allowed.

## Using artisan commands

You can create a role or permission from a console with artisan commands.

```bash
php artisan permission:create-role writer
```

```bash
php artisan permission:create-permission 'edit articles'
```

When creating permissions and roles for specific guards you can specify the guard names as a second argument:

```bash
php artisan permission:create-role writer web
```

```bash
php artisan permission:create-permission 'edit articles' web
```

## Unit Testing

In your application's tests, if you are not seeding roles and permissions as part of your test `setUp()` then you may run into a chicken/egg situation where roles and permissions aren't registered with the gate (because your tests create them after that gate registration is done). Working around this is simple: In your tests simply add a `setUp()` instruction to re-register the permissions, like this:

```php
    public function setUp()
    {
        // first include all the normal setUp operations
        parent::setUp();

        // now re-register all the roles and permissions
        $this->app->make(\Idsign\Permission\PermissionRegistrar::class)->registerPermissions();
    }
```

## Database Seeding

Two notes about Database Seeding:

1. It is best to flush the `idsign.permission.cache.permissions` and `idsign.permission.cache.sections` before seeding, to avoid cache conflict errors. This can be done from an Artisan command (see Troubleshooting: Cache section, later) or directly in a seeder class (see example below).

2. Here's a sample seeder, which clears the cache, creates permissions and then assigns permissions to roles:

	```php
	use Illuminate\Database\Seeder;
	use Idsign\Permission\Models\Role;
	use Idsign\Permission\Models\Permission;
    use Idsign\Permission\Models\Section;

	class RolesAndPermissionsSeeder extends Seeder
	{
	    public function run()
    	{
        	// Reset cached roles and permissions
	        app()['cache']->forget('idsign.permission.cache.permissions');
            app()['cache']->forget('idsign.permission.cache.sections');

	        // create permissions
	        Permission::create(['name' => 'edit articles']);
	        Permission::create(['name' => 'delete articles']);
	        Permission::create(['name' => 'publish articles']);
	        Permission::create(['name' => 'unpublish articles']);
        
            Section::create(['blog' => 'unpublish articles']);

	        // create roles and assign existing permissions
	        $role = Role::create(['name' => 'writer']);
	        $role->givePermissionTo('edit articles', 'blog');
	        $role->givePermissionTo('delete articles', 'blog');

	        $role = Role::create(['name' => 'admin']);
	        $role->givePermissionTo('publish articles', 'blog');
	        $role->givePermissionTo('unpublish articles', 'blog');
	    }
	}

	```

## Extending

If you need to extend or replace the existing `Role` or `Permission` models you just need to
keep the following things in mind:

- Your `Role` model needs to implement the `Idsign\Permission\Contracts\Role` contract
- Your `Permission` model needs to implement the `Idsign\Permission\Contracts\Permission` contract
- Your `Section` model needs to implement the `Idsign\Permission\Contracts\Section` contract
- You can publish the configuration with this command:

```bash
php artisan vendor:publish --provider="Idsign\Permission\PermissionServiceProvider" --tag="config"
```
  
  And update the `models.role`, `models.permission` and `models.section` values


## Cache

Role and Permission data are cached to speed up performance.

When you use the supplied methods for manipulating roles and permissions, the cache is automatically reset for you:

```php
$user->assignRole('writer');
$user->removeRole('writer');
$user->syncRoles(params);
$role->givePermissionTo('edit articles', 'blog');
$role->revokePermissionTo('edit articles', 'blog');
$role->syncPermissions(params, 'blog');
```

HOWEVER, if you manipulate permission/role data directly in the database instead of calling the supplied methods, then you will not see the changes reflected in the application unless you manually reset the cache.

### Manual cache reset
To manually reset the cache for this package, run:
```bash
php artisan cache:forget idsign.permission.cache.permissions
```
```bash
php artisan cache:forget idsign.permission.cache.sections
```

### Cache Identifier

TIP: If you are leveraging a caching service such as `redis` or `memcached` and there are other sites 
running on your server, you could run into cache clashes. It is prudent to set your own cache `prefix` 
in `/config/cache.php` to something unique for each application. This will prevent other applications 
from accidentally using/changing your cached data.


### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security-related issues, please email [domenico.rizzo@gmail.com](mailto:domenico.rizzo@gmail.com) instead of using the issue tracker.

## Credits

- [Freek Van der Herten](https://github.com/freekmurze)(Original developer)
- [Domenico Rizzo](https://github.com/willypuzzle)(who made the section extension)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
