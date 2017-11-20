<?php

namespace Idsign\Permission\Traits;

use Idsign\Permission\Contracts\{
    Permission,
    Role,
    Section
};
use Idsign\Permission\Exceptions\RoleDoesNotExist;
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

    public function isEnabled()
    {
        $fieldName = config('permission.user.state.field_name');

        if($fieldName && isset($this->attributes[$fieldName])){
            $enabledValues = config('permission.user.state.values.enabled') ?? [];
            $disabledValues = config('permission.user.state.values.disabled') ?? [];
            $value = $this->attributes[$fieldName];
            if(in_array($value, $disabledValues)){
                return false;
            }
            if(in_array($value, $enabledValues)){
                return true;
            }
            return config('permission.user.state.values.default') ?? false;
        }else{
            return true;
        }

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
            config('permission.table_names.role_has_permissions'),
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
     * @param bool $checkEnabled check if the roles are enabled
     *
     * @return bool
     */
    public function hasRole($roles, bool $checkEnabled = true): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            try{
                $roles = app(Role::class)->findByName(
                    $roles,
                    $guardName ?? $this->getDefaultGuardName()
                );
            }catch (RoleDoesNotExist $ex){
                return false;
            }
        }

        if ($roles instanceof Role) {
            if($checkEnabled){
                $set = $this->roles()->where([
                    'state' => Role::ENABLED,
                    'id' => $roles->id
                ])->get();

                return $set->count() > 0;
            }else{
                return $this->roles->contains('id', $roles->id);
            }
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $checkEnabled)) {
                    return true;
                }
            }

            return false;
        }

        if(!$checkEnabled){
            return $roles->intersect($this->roles)->isNotEmpty();
        }else{
            return $roles->intersect($this->roles()->where(['state' => Role::ENABLED])->get())->isNotEmpty();
        }

    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * @param string|array|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole($roles, $checkEnabled = true): bool
    {
        return $this->hasRole($roles, $checkEnabled);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param string|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAllRoles($roles, $checkEnabled = true): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            if($checkEnabled){
                return $this->roles()->where('state', Role::ENABLED)->get()->contains('name', $roles);
            }else{
                return $this->roles->contains('name', $roles);
            }
        }

        if ($roles instanceof Role) {
            if($checkEnabled){
                return $this->roles()->where('state', Role::ENABLED)->get()->contains('id', $roles->id);
            }else{
                return $this->roles->contains('id', $roles->id);
            }
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        if($checkEnabled){
            return $roles->intersect($this->roles()->where('state', Role::ENABLED)->get()->pluck('name')) == $roles;
        }else{
            return $roles->intersect($this->roles->pluck('name')) == $roles;
        }
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
    public function hasPermissionTo($permission, $section, $guardName = null, $checkEnabled = true): bool
    {
        if($checkEnabled && !$this->isEnabled()){
            return false;
        }

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

        if($checkEnabled && $section->state != Section::ENABLED){
            return false;
        }



        return $this->hasDirectPermission($permission, $section, $checkEnabled) || $this->hasPermissionViaRole($permission, $section, $checkEnabled);
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array $permissions
     *
     * @return bool
     */
    public function hasAnyPermission(array $permissions, string $section, $checkEnabled = true): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission, $section, $checkEnabled)) {
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
    protected function hasPermissionViaRole(Permission $permission, $section, $checkEnabled = true): bool
    {
        if($checkEnabled && !$this->isEnabled()){
            return false;
        }

        if($checkEnabled && $permission->state != Permission::ENABLED){
            return false;
        }

        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());

            if (! $section) {
                return false;
            }
        }

        if($checkEnabled && $section->state != Section::ENABLED){
            return false;
        }

        if($checkEnabled){
            return $this->hasRole($permission->roles()->where('state', Role::ENABLED)->wherePivot('section_id', '=', $section->id)->get(), $checkEnabled);
        }else{
            return $this->hasRole($permission->roles()->wherePivot('section_id', '=', $section->id)->get(), $checkEnabled);
        }
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param string|\Idsign\Permission\Contracts\Permission $permission
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * @return bool
     */
    public function hasDirectPermission($permission, $section, $checkEnabled = true): bool
    {
        if($checkEnabled && !$this->isEnabled()){
            return false;
        }

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

        if($checkEnabled && $section->state != Section::ENABLED){
            return false;
        }

        $permission = $this->permissions()
                        ->wherePivot('permission_id', '=', $permission->id)
                        ->wherePivot('section_id', '=', $section->id)
                        ->first();

        return ($permission !== NULL) && ($checkEnabled ? $permission->state == Permission::ENABLED : true);
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all permissions the directory coupled to the model.
     */
    public function getDirectPermissions($section, $checkEnabled = true): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        if($checkEnabled && (!$this->isEnabled() || $section->state != Section::ENABLED)){
            return collect([]);
        }

        if($checkEnabled){
            return $this->permissions()
                ->where('state', Permission::ENABLED)
                ->wherePivot('section_id', '=', $section->id)
                ->get();
        }else{
            return $this->permissions()
                ->wherePivot('section_id', '=', $section->id)
                ->get();
        }
    }

//    /**
//     * @param string|\Idsign\Permission\Contracts\Section $section
//     *
//     * Return all the permissions the model has via roles.
//     */
//    public function getPermissions($section): Collection
//    {
//        if (is_string($section)) {
//            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
//        }
//
//        return $this->permissions()->wherePivot('section_id', '=', $section->id)->get();
//    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all the permissions the model has via roles.
     */
    public function getPermissionsViaRoles($section, $checkEnabled = true): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        if($checkEnabled && (!$this->isEnabled() || $section->state != Section::ENABLED)){
            return collect([]);
        }

        return $this->load('roles', 'roles.permissions')
            ->roles->filter(function ($role) use ($checkEnabled){
                return $checkEnabled ? $role->state == Role::ENABLED : true;
            })->flatMap(function ($role) use ($section){
                return $role->permissions()->wherePivot('section_id', '=', $section->id)->get();
            })->sort()->values();
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * Return all the permissions the model has, both directly and via roles.
     */
    public function getAllPermissions($section, $checkEnabled = true): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        return $this->getDirectPermissions($section, $checkEnabled)
            ->merge($this->getPermissionsViaRoles($section, $checkEnabled))
            ->sort()
            ->values();
    }

    public function getRoleNames($checkEnabled = true): Collection
    {
        if($checkEnabled && !$this->isEnabled()){
            return collect([]);
        }

        if($checkEnabled){
            return $this->roles()->where('state', Role::ENABLED)->pluck('name');
        }else{
            return $this->roles->pluck('name');
        }
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

    public function getPermissionsTree($checkEnabled = true) : array
    {
        if($checkEnabled && !$this->isEnabled()){
            return [];
        }

        if($checkEnabled){
            $sections = app(Section::class)->where([
                'guard_name' => $this->getDefaultGuardName(),
                'state' => Section::ENABLED
            ])->get();
        }else{
            $sections = app(Section::class)->where([
                'guard_name' => $this->getDefaultGuardName()
            ])->get();
        }


        $result = [];
        foreach ($sections as $section){
            $result[$section->name] = $this->parseCollectionForPermissionTree($this->getAllPermissions($section, true));
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
