<?php

namespace Idsign\Permission\Models;

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

class Section extends Model implements SectionContract
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

        $this->setTable(config('permission.table_names.sections'));
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->users_from_roles()->detach();
            $model->users_from_permissions()->detach();

            $model->roles()->detach();
            $model->permissions_from_roles()->detach();
            $model->permissions_from_users()->detach();
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

    public function users_from_roles(): BelongsToMany
    {
        return $this->belongsToMany(
            getModelForGuard($this->attributes['guard_name']),
            config('permission.table_names.role_has_permissions')
        );
    }

    public function users_from_permissions(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            config('permission.table_names.model_has_permissions'),
            'section_id',
            'model_id'
        );
    }

    /**
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.role_has_permissions')
        );
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions_from_roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.permission'),
            config('permission.table_names.role_has_permissions')
        );
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions_from_users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.permission'),
            config('permission.table_names.model_has_permissions')
        );
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

    public function scopeEnabled($query, $state = \Idsign\Permission\Contracts\Section::ENABLED)
    {
        return $query->where('state', $state);
    }

    public function parent()
    {
        return $this->belongsTo(config('permission.models.section'), 'section_id');
    }

    public function childrens()
    {
        return $this->hasMany(config('permission.models.section'), 'section_id');
    }

    public function tree()
    {

    }
}
