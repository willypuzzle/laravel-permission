<?php

namespace Idsign\Permission\Traits;

use Idsign\Permission\Contracts\{
    Permission,
    Role,
    Section
};
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasRoles
{
    use HasPermissions;

    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
            $model->permissions()->detach();
        });
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            'model_id',
            'role_id'
        );
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            'model_id',
            'permission_id'
        );
    }

    /**
     * A model may have multiple roles.
     */
    public function sections_from_roles(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.section'),
            'model',
            config('permission.table_names.model_has_roles'),
            'model_id',
            'section_id'
        );
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function sections_from_permissions(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.section'),
            'model',
            config('permission.table_names.model_has_permissions'),
            'model_id',
            'section_id'
        );
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRole(Builder $query, $roles): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->toArray();
        }

        if (! is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) {
            if ($role instanceof Role) {
                return $role;
            }

            return app(Role::class)->findByName($role, $this->getDefaultGuardName());
        }, $roles);

        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere(config('permission.table_names.roles').'.id', $role->id);
                }
            });
        });
    }

//    /**
//     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
//     *
//     * @return array
//     */
//    protected function convertToPermissionModels($permissions): array
//    {
//        if ($permissions instanceof Collection) {
//            $permissions = $permissions->toArray();
//        }
//
//        $permissions = array_wrap($permissions);
//
//        return array_map(function ($permission) {
//            if ($permission instanceof Permission) {
//                return $permission;
//            }
//
//            return app(Permission::class)->findByName($permission, $this->getDefaultGuardName());
//        }, $permissions);
//    }

//    /**
//     * Scope the model query to certain permissions only.
//     *
//     * @param \Illuminate\Database\Eloquent\Builder $query
//     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
//     *
//     * @return \Illuminate\Database\Eloquent\Builder
//     */
//    public function scopePermission(Builder $query, $permissions): Builder
//    {
//        $permissions = $this->convertToPermissionModels($permissions);
//
//        $rolesWithPermissions = array_unique(array_reduce($permissions, function ($result, $permission) {
//            return array_merge($result, $permission->roles->all());
//        }, []));
//
//        return $query->
//            where(function ($query) use ($permissions, $rolesWithPermissions) {
//                $query->whereHas('permissions', function ($query) use ($permissions) {
//                    $query->where(function ($query) use ($permissions) {
//                        foreach ($permissions as $permission) {
//                            $query->orWhere(config('permission.table_names.permissions').'.id', $permission->id);
//                        }
//                    });
//                });
//                if (count($rolesWithPermissions) > 0) {
//                    $query->orWhereHas('roles', function ($query) use ($rolesWithPermissions) {
//                        $query->where(function ($query) use ($rolesWithPermissions) {
//                            foreach ($rolesWithPermissions as $role) {
//                                $query->orWhere(config('permission.table_names.roles').'.id', $role->id);
//                            }
//                        });
//                    });
//                }
//            });
//    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|\Idsign\Permission\Contracts\Role ...$roles
     *
     * @return $this
     */
    public function assignRole(...$roles)
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                return $this->getStoredRole($role);
            })
            ->each(function ($role) {
                $this->ensureModelSharesGuard($role);
            })
            ->all();

        $this->roles()->saveMany($roles);

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Idsign\Permission\Contracts\Role $role
     */
    public function removeRole($role)
    {
        $this->roles()->detach($this->getStoredRole($role));
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|\Idsign\Permission\Contracts\Role|string ...$roles
     *
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $this->roles()->detach();

        return $this->assignRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|array|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasRole($roles): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($this->roles)->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * @param string|array|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param string|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAllRoles($roles): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect($this->roles->pluck('name')) == $roles;
    }

    /**
     * Determine if the model may perform the given permission.
     *
     * @param string|\Idsign\Permission\Contracts\Permission $permission
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|null $guardName
     *
     * @return bool
     */
    public function hasPermissionTo($permission, $section, $guardName = null): bool
    {
        if (is_string($permission)) {
            $permission = app(Permission::class)->findByName(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (is_string($section)) {
            $section = app(Section::class)->findByName(
                $section,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        return $this->hasDirectPermission($permission, $section) || $this->hasPermissionViaRole($permission, $section);
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array $permissions
     *
     * @return bool
     */
    public function hasAnyPermission(array $permissions, string $section): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission, $section)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     *
     * @param \Idsign\Permission\Contracts\Permission $permission
     *
     * @return bool
     */
    protected function hasPermissionViaRole(Permission $permission, $section): bool
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());

            if (! $section) {
                return false;
            }
        }

        return $this->hasRole($permission->roles()->wherePivot('section_id', '=', $section->id)->get());;
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param string|\Idsign\Permission\Contracts\Permission $permission
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * @return bool
     */
    public function hasDirectPermission($permission, $section): bool
    {
        if (is_string($permission)) {
            $permission = app(Permission::class)->findByName($permission, $this->getDefaultGuardName());

            if (! $permission) {
                return false;
            }
        }

        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());

            if (! $section) {
                return false;
            }
        }

        return $this->permissions()->wherePivot('permission_id', '=', $permission->id)->wherePivot('section_id', '=', $section->id)->get()->count() > 0;
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all permissions the directory coupled to the model.
     */
    public function getDirectPermissions($section): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        return $this->permissions()->wherePivot('section_id', '=', $section->id)->get();
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all the permissions the model has via roles.
     */
    public function getPermissions($section): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        return $this->permissions()->wherePivot('section_id', '=', $section->id)->get();
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all the permissions the model has via roles.
     */
    public function getPermissionsViaRoles($section): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        return $this->load('roles', 'roles.permissions')
            ->roles->flatMap(function ($role) use ($section){
                return $role->permissions()->wherePivot('section_id', '=', $section->id)->get();
            })->sort()->values();
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all the permissions the model has, both directly and via roles.
     */
    public function getAllPermissions($section): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        return $this->permissions()->wherePivot('section_id', '=', $section->id)->get()
            ->merge($this->getPermissionsViaRoles($section))
            ->sort()
            ->values();
    }

    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    protected function getStoredRole($role): Role
    {
        if (is_string($role)) {
            return app(Role::class)->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }

    public function getPermissionsTree() : array
    {
        $sections = app(Section::class)->where([
            'guard_name' => $this->getDefaultGuardName()
        ])->get();

        $result = [];
        foreach ($sections as $section){
            $result[$section->name] = $this->parseCollectionForPermissionTree($this->getAllPermissions($section));
        }

        return $result;
    }

    private function parseCollectionForPermissionTree(Collection $collection) : array
    {
        $result = [];
        foreach ($collection as $c){
            $result[$c->name] = $c->toArray();
        }

        return $result;
    }
}
