<?php

namespace Idsign\Permission\Models;

use Idsign\Permission\Libraries\Config;
use Idsign\Permission\Libraries\ModelSupport;
use Illuminate\Database\Eloquent\Model;
use Idsign\Permission\Traits\HasPermissions;
use Idsign\Permission\Exceptions\RoleDoesNotExist;
use Idsign\Permission\Exceptions\GuardDoesNotMatch;
use Idsign\Permission\Exceptions\RoleAlreadyExists;
use Idsign\Permission\Contracts\Role as RoleContract;
use Idsign\Permission\Contracts\Permission as PermissionContract;
use Idsign\Permission\Contracts\Container as ContainerContract;
use Idsign\Permission\Contracts\Section as SectionContract;
use Idsign\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
//use Illuminate\Database\Query\JoinClause;
use DB;
use Illuminate\Support\Collection;
use function Idsign\Permission\Helpers\getModelForGuard;
use Idsign\Permission\Contracts\Container as ContainerInterface;
use Idsign\Permission\Contracts\Section as SectionInterface;

class Role extends Model implements RoleContract
{
    use HasPermissions;
    use RefreshesPermissionCache;

    public $guarded = ['id'];

    protected $casts = [
        'label' => 'array',
        'meta' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(Config::rolesTable());
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->users()->detach();
            $model->permissions()->detach();
        });
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        if (static::where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions($sectionId = null, $containerId = null, $permissionId = null): BelongsToMany
    {
        $relation =  $this->belongsToMany(
            Config::permissionModel(),
            Config::roleHasPermissionsTable(),
            'role_id',
            'permission_id',
            'id',
            'id'
        );

        if($sectionId){
            $relation = $relation->wherePivot('section_id', '=', $sectionId);
        }

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($permissionId){
            $relation = $relation->wherePivot('permission_id', '=', $permissionId);
        }

        return $relation;
    }

    public function containers()
    {
        return $this->belongsToMany(
            Config::containerModel(),
            Config::containerRoleTable(),
            'role_id',
            'container_id',
            'id',
            'id'
        );
    }

    /**
     * A role belongs to some users of the model associated with its guard.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            Config::modelHasRolesTable(),
            'role_id',
            'model_id'
        );
    }

    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Idsign\Permission\Contracts\Role|\Idsign\Permission\Models\Role
     *
     * @throws \Idsign\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): RoleContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        $role = static::where('name', $name)->where('guard_name', $guardName)->first();

        if (! $role) {
            throw RoleDoesNotExist::create($name);
        }

        return $role;
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|PermissionContract $permission
     *
     * @param string|SectionContract $section
     *
     * @param string|ContainerContract $container
     *
     * @return bool
     *
     * @throws \Idsign\Permission\Exceptions\GuardDoesNotMatch
     */
    public function hasPermissionTo($permission, $section, $container, $checkEnabled = true): bool
    {
        $permission = $this->resolveClass($permission, PermissionContract::class);
        $section = $this->resolveClass($section, SectionContract::class);
        $container = $this->resolveClass($container, ContainerContract::class);

        if(!$permission || !$section || !$container){
            return false;
        }

        if($this->guard_name != $permission->guard_name){
            throw new GuardDoesNotMatch();
        }

        if($checkEnabled && $this->state != RoleContract::ENABLED){
            return false;
        }

        if(!$checkEnabled){
            return $this->permissions($section->id, $container->id, $permission->id)->count() > 0;
        }else{
            return $this->permissions($section->id, $container->id, $permission->id)->where('state', RoleContract::ENABLED)->count() > 0;
        }

    }

    protected function isForceDeleting(){
        return true;
    }

    public function scopeEnabled($query, $state = RoleContract::ENABLED)
    {
        return $query->where('state', $state);
    }

    public function permissionsTree(ContainerInterface $container, $checkSuperadmin = true)
    {
        $tree = app(SectionInterface::class)->containerTree($container, false);

        $tree = $this->permissionBuilder($tree, $container, $checkSuperadmin);

        return $tree;
    }

    private function permissionBuilder($tree, $container, $checkSuperadmin)
    {
        return array_map(function ($el) use ($container, $checkSuperadmin){
            $el['permissions'] = $this->permissions($el['model']['id'], $container->id)->get()->toArray();
            $el['superadmin'] = $checkSuperadmin ? $this->elaborateSuperadmin($el, $container) : null;
            if(isset($el['children']) && is_array($el['children']) && count($el['children'])){
                $el['children'] = $this->permissionBuilder($el['children'], $container, $checkSuperadmin);
            }
            return $el;
        }, $tree);
    }

    private function elaborateSuperadmin($element, $container)
    {
        return ModelSupport::elaborateSuperadmin($element, $container);
    }

    public function isSuperuser()
    {
        return $this->name == Config::superuser();
    }

    public function isAdmin()
    {
        return $this->name == Config::admin();
    }
}
