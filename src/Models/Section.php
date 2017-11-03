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

class Section extends Model implements SectionContract
{
    use RefreshesPermissionCache;

    public $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(config('permission.table_names.sections'));
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

    /**
     * A role belongs to some users of the model associated with its guard related to roles.
     */
    public function users_from_roles(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            config('permission.table_names.model_has_roles'),
            'section_id',
            'model_id'
        );
    }

    /**
     * A role belongs to some users of the model associated with its guard related to roles.
     */
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
        return app(PermissionRegistrar::class)->getSection();
    }
}
