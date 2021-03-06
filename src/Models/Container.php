<?php

namespace Idsign\Permission\Models;

use Idsign\Permission\Exceptions\ContainerAlreadyExists;
use Idsign\Permission\Exceptions\ContainerDoesNotExist;
use Idsign\Permission\Libraries\Config;
use Idsign\Permission\PermissionRegistrar;
use Idsign\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Willypuzzle\Helpers\Facades\General\Database;
use Idsign\Permission\Contracts\Container as ContainerInterface;

class Container extends Model implements ContainerInterface
{
    use RefreshesPermissionCache;

    protected $guarded = ['id'];

    protected $casts = [
        'label' => 'array',
        'meta' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(Config::containersTable());
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        if (static::where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
            throw ContainerAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    public static function findByName(string $name, $guardName = null): ContainerInterface
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        $container = static::getContainers()->where('name', $name)->where('guard_name', $guardName)->first();

        if (!$container) {
            throw ContainerDoesNotExist::create($name, $guardName);
        }

        return $container;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getContainers(): Collection
    {
        return app(PermissionRegistrar::class)->getContainers();
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function (Container $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->permissions_from_users()->detach();
            $model->permissions_from_roles()->detach();
        });
    }

    public function permissions_from_users($modelId = null, $sectionId = null)
    {
        $relationship = $this->belongsToMany(Config::permissionModel(), Config::modelHasPermissionsTable(), 'container_id');

        if($modelId){
            $relationship = $relationship->wherePivot('model_id', $modelId)
                                         ->wherePivot('model_type', Database::getMorphedModelInverse(Config::userModel($this->guard_name)));
        }

        if($sectionId){
            $relationship = $relationship->wherePivot('section_id', $sectionId);
        }

        return $relationship;
    }

    public function permissions_from_roles($roleId = null, $sectionId = null)
    {
        $relationship = $this->belongsToMany(
            Config::permissionModel(),
            Config::roleHasPermissionsTable(),
            'container_id',
            'permission_id'
        );

        if($roleId){
            $relationship = $relationship->wherePivot('role_id', $roleId);
        }

        if($sectionId){
            $relationship = $relationship->wherePivot('section_id', $sectionId);
        }

        return $relationship;
    }

    public function roles()
    {
        return $this->belongsToMany(
            Config::roleModel(),
            Config::containerRoleTable(),
            'container_id',
            'role_id',
            'id',
            'id'
        );
    }

    public function sections() : BelongsToMany
    {
        $relationship = $this->belongsToMany(
            Config::sectionModel(),
            Config::containerSectionTable(),
            'container_id',
            'section_id'
        )->withPivot(['superadmin']);

        return $relationship;
    }
}
