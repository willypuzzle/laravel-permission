<?php

namespace Idsign\Permission\Models;

use Idsign\Permission\Libraries\Config;
use Idsign\Permission\Libraries\ModelSupport;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Idsign\Permission\PermissionRegistrar;
use Idsign\Permission\Traits\RefreshesPermissionCache;
use Idsign\Permission\Exceptions\SectionDoesNotExist;
use Idsign\Permission\Exceptions\SectionAlreadyExists;
use Idsign\Permission\Contracts\Section as SectionContract;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use function Idsign\Permission\Helpers\getModelForGuard;
use Willypuzzle\Helpers\Facades\General\Database;
use Idsign\Permission\Contracts\Container as ContainerContract;

class Section extends Model implements SectionContract
{
    use RefreshesPermissionCache;

    public $guarded = ['id'];

    protected $casts = [
        'label' => 'array',
        'meta' => 'array',
        'superadmin' => 'boolean'
    ];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(Config::sectionsTable());
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            // $model->users_from_roles()->detach();
            $model->users_from_permissions()->detach();

            $model->roles()->detach();
            $model->permissions_from_roles()->detach();
            $model->permissions_from_users()->detach();
        });

        static::saving(function($model) {
            if($model->order === null){
                $order = static::where('section_id', $model->section_id)->max('order');
                $model->order = ($order !== null ? $order + 1 : 0);
            }
        });
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        if (static::getSections()->where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
            throw SectionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    /*public function users_from_roles(): BelongsToMany
    {
        return $this->belongsToMany(
            getModelForGuard($this->attributes['guard_name']),
            config('permission.table_names.role_has_permissions')
        );
    }*/

    public function users_from_permissions($containerId = null, $permissionId = null): MorphToMany
    {
        $relation = $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            Config::modelHasPermissionsTable(),
            'section_id',
            'model_id'
        )->withPivot(['enabled']);

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($permissionId){
            $relation = $relation->wherePivot('permission_id', '=', $permissionId);
        }

        return $relation;
    }

    /**
     * A permission can be applied to roles.
     */
    public function roles($containerId = null, $permissionId = null): BelongsToMany
    {
        $relation = $this->belongsToMany(
            Config::roleModel(),
            Config::roleHasPermissionsTable(),
            'section_id',
            'role_id'
        );

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($permissionId){
            $relation = $relation->wherePivot('permission_id', '=', $permissionId);
        }

        return $relation;
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions_from_roles($containerId = null, $roleId = null): BelongsToMany
    {
        $relation =  $this->belongsToMany(
            Config::permissionModel(),
            Config::roleHasPermissionsTable(),
            'section_id',
            'permission_id'
        );

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($roleId){
            $relation = $relation->wherePivot('role_id', '=', $roleId);
        }

        return $relation;
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions_from_users($containerId = null, $userId = null): BelongsToMany
    {
        $relation = $this->belongsToMany(
            Config::permissionModel(),
            Config::modelHasPermissionsTable(),
            'section_id',
            'permission_id'
        )->withPivot(['enabled']);

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($userId){
            $relation = $relation->wherePivot('model_id', $userId)
                ->wherePivot('model_type', Database::getMorphedModelInverse(Config::userModel($this->guard_name)));
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
    public static function findByName(string $name, $guardName = null): SectionContract
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        $permission = static::getSections()->where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            throw SectionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getSections(): Collection
    {
        return app(PermissionRegistrar::class)->getSections();
    }

    protected function isForceDeleting(){
        return true;
    }

    public function scopeEnabled($query, $state = SectionContract::ENABLED)
    {
        return $query->where('state', $state);
    }

    public function parent()
    {
        return $this->belongsTo(Config::sectionModel(), 'section_id');
    }

    public function children($onlyEnabled = true)
    {
        $relation = $this->hasMany(Config::sectionModel(), 'section_id');

        if($onlyEnabled){
            $relation = $relation->where('state',SectionContract::ENABLED);
        }

        return $relation;
    }

    /**
     * @param string|ContainerContract $container
     * @param bool $onlyEnabled
     * @param null|integer $rootId
     * @return array
     */
    protected static function tree($container, $onlyEnabled = true, $rootId = null, $global = false) : array
    {
        if($global){
            $query = static::where('guard_name', $container);
        }else{
            $query = static::where('guard_name', $container->guard_name);
            $query = $query->with('containers');
            $deltaSections = $container->sections()->get()->toArray();
        }

        $query = $query->where(Config::sectionsTable().'.section_id', $rootId);
        $query = $query->orderBy(Config::sectionsTable().'.order', 'asc');

        if($onlyEnabled){
            $query = $query->where('state', SectionContract::ENABLED);
        }

        $data = $query->get()->toArray();
        if(isset($deltaSections)){
            $deltaSectionsIds = array_map(function ($el){
                return $el['id'];
            }, $deltaSections);
            $data = array_map(function ($el) use ($deltaSectionsIds){
                $el['is'] = in_array($el['id'], $deltaSectionsIds);
                return $el;
            }, $data);
        }
        $return = [];
        foreach ($data as $model){
            $children = self::tree($container, $onlyEnabled, $model['id'], $global);
            $d = [
                'model' => $model,
                'children' => $children,
            ];

            if(!$global){
                $d['superadmin-forced'] = ModelSupport::elaborateSuperadmin($d, $container);
            }

            $return[] = $d;
        }

        return $return;
    }

    public function containers() : BelongsToMany
    {
        $relationship = $this->belongsToMany(
            Config::containerModel(),
            Config::containerSectionTable(),
            'section_id',
            'container_id'
        )->withPivot(['superadmin']);

        return $relationship;
    }

    /**
     * @param string $guard
     * @param bool $onlyEnabled
     * @return array
     */
    public static function globalTree(string $guard, $onlyEnabled = true)
    {
        return self::tree($guard, $onlyEnabled, null, true);
    }

    /**
     * @param ContainerContract $container
     * @param bool $onlyEnabled
     * @return array
     */
    public static function containerTree(ContainerContract $container, $onlyEnabled = true)
    {
        return self::tree($container, $onlyEnabled, null, false);
    }
}
