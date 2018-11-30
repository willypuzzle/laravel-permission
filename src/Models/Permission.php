<?php

namespace Idsign\Permission\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Idsign\Permission\PermissionRegistrar;
use Idsign\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Idsign\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Idsign\Permission\Exceptions\PermissionAlreadyExists;
use Idsign\Permission\Contracts\Permission as PermissionContract;

class Permission extends Model implements PermissionContract
{
    use RefreshesPermissionCache;

    public $guarded = ['id'];

    protected $casts = [
        'label' => 'array'
    ];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(config('permission.table_names.permissions'));
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
            $model->users()->detach();
        });
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        if (static::getPermissions()->where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    /**
     * A permission can be applied to roles.
     */
    public function roles($sectionId = null, $containerId = null, $roleId = null): BelongsToMany
    {
        $relation = $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.role_has_permissions'),
            'permission_id',
            'role_id',
            'id',
            'id'
        );

        if($sectionId){
            $relation = $relation->wherePivot('section_id', '=', $sectionId);
        }

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($roleId){
            $relation = $relation->wherePivot('role_id', '=', $roleId);
        }

        return $relation;
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     */
    public function users($sectionId = null, $containerId = null): MorphToMany
    {
        $relation =  $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            config('permission.table_names.model_has_permissions'),
            'permission_id',
            'model_id'
        );

        if($sectionId){
            $relation = $relation->wherePivot('section_id', '=', $sectionId);
        }

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        return $relation;
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @throws \Idsign\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return \Idsign\Permission\Contracts\Permission
     */
    public static function findByName(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        $permission = static::getPermissions()->where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions(): Collection
    {
        return app(PermissionRegistrar::class)->getPermissions();
    }

    protected function isForceDeleting(){
        return true;
    }

    public function scopeEnabled($query, $state = \Idsign\Permission\Contracts\Permission::ENABLED)
    {
        return $query->where('state', $state);
    }
}
