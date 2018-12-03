<?php

namespace Idsign\Permission\Models;

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

class Role extends Model implements RoleContract
{
    use HasPermissions;
    use RefreshesPermissionCache;

    public $guarded = ['id'];

    protected $casts = [
        'label' => 'array'
    ];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(config('permission.table_names.roles'));
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
            config('permission.models.permission'),
            config('permission.table_names.role_has_permissions'),
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

    /**
     * A role belongs to some users of the model associated with its guard.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            config('permission.table_names.model_has_roles'),
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

        if($checkEnabled && $this->state !== RoleContract::ENABLED){
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
}
