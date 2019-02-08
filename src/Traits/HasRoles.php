<?php

namespace Idsign\Permission\Traits;

use Idsign\Permission\Contracts\{Constants, Container, Permission, Role, Section};
use Idsign\Permission\Exceptions\RoleDoesNotExist;
use Idsign\Permission\Libraries\Config;
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
        $fieldName = Config::userStateFieldName();

        if($fieldName && isset($this->attributes[$fieldName])){
            $enabledValues = Config::userStateEnabled() ?? [];
            $disabledValues = Config::userStateDisabled() ?? [];
            $value = $this->attributes[$fieldName];
            if(in_array($value, $disabledValues)){
                return false;
            }
            if(in_array($value, $enabledValues)){
                return true;
            }
            return Config::userStateDefault() ?? false;
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
            Config::roleModel(),
            'model',
            Config::modelHasRolesTable(),
            'model_id',
            'role_id'
        );
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions($sectionId = null, $containerId = null, $permissionId = null): MorphToMany
    {
        $relation =  $this->morphToMany(
            Config::permissionModel(),
            'model',
            Config::modelHasPermissionsTable(),
            'model_id',
            'permission_id'
        )->withPivot(['enabled']);

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

    public function sections_from_permissions($containerId = null, $permissionId = null, $sectionId = null): MorphToMany
    {
        $relation = $this->morphToMany(
            Config::sectionModel(),
            'model',
            Config::modelHasPermissionsTable(),
            'model_id',
            'section_id'
        );

        if($containerId){
            $relation = $relation->wherePivot('container_id', '=', $containerId);
        }

        if($permissionId){
            $relation = $relation->wherePivot('permission_id', '=', $permissionId);
        }

        if($sectionId){
            $relation = $relation->wherePivot('section_id', '=', $sectionId);
        }

        return $relation;
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
            return $this->resolveClass($role, Role::class);
        }, $roles);

        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere(Config::rolesTable().'.id', $role->id);
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
                $roles = $this->resolveClass($roles, Role::class);
            }catch (RoleDoesNotExist $ex){
                return false;
            }
        }

        if ($roles instanceof Role) {
            if($checkEnabled){
                $set = $this->roles()->where([
                    'state' => Role::ENABLED,
                    Config::rolesTable().'.id' => $roles->id
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
     * @param string|\Idsign\Permission\Contracts\Container $container
     * @param boolean $checkEnabled
     *
     * @return bool
     */
    public function hasPermissionTo($permission, $section, $container, $checkEnabled = true): bool
    {
        if($checkEnabled && !$this->isEnabled()){
            return false;
        }

        return $this->hasDirectPermission($permission, $section, $container, $checkEnabled) || $this->hasPermissionViaRole($permission, $section, $container, $checkEnabled);
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array $permissions
     *
     * @return bool
     */
    public function hasAnyPermission(array $permissions, $section, $container, $checkEnabled = true): bool
    {
        if(empty($permissions)){
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission, $section, $container, $checkEnabled)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissions, $section, $container, $checkEnabled = true)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission, $section, $container, $checkEnabled)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     *
     * @param string|\Idsign\Permission\Contracts\Permission $permission
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|\Idsign\Permission\Contracts\Container $container
     * @param boolean $checkEnabled
     *
     * @return bool
     */
    protected function hasPermissionViaRole($permission, $section, $container, $checkEnabled = true): bool
    {
        if($checkEnabled && !$this->isEnabled()){
            return false;
        }

        $permission = $this->resolveClass($permission, Permission::class);
        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        if(!$permission || !$section || !$container){
            return false;
        }

        if($checkEnabled && ($permission->state != Permission::ENABLED || $section->state != Section::ENABLED || $container->state != Container::ENABLED)){
            return false;
        }

        if($checkEnabled){
            return $this->hasRole($permission->roles($section->id, $container->id, null)->where('state', Role::ENABLED)->get(), $checkEnabled);
        }else{
            return $this->hasRole($permission->roles($section->id, $container->id, null)->get(), $checkEnabled);
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
    public function hasDirectPermission($permission, $section, $container, $checkEnabled = true): bool
    {
        if($checkEnabled && !$this->isEnabled()){
            return false;
        }

        $permission = $this->resolveClass($permission, Permission::class);
        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        if (!$permission || !$section || !$container) {
            return false;
        }

        if($checkEnabled && ($section->state != Section::ENABLED || $container->state != Container::ENABLED || $permission->state != Permission::ENABLED)){
            return false;
        }

        $permission = $this->permissions($section->id, $container->id, $permission->id)->first();

        return ($permission !== null && $permission->pivot->enabled);
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|Container $container
     * @param boolean $checkEnabled
     *
     * Return all permissions the directory coupled to the model.
     */
    public function getDirectPermissions($section, $container, $checkEnabled = true): Collection
    {
        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        if($checkEnabled && (!$this->isEnabled() || !$section || !$container || $section->state != Section::ENABLED || $container->state != Container::ENABLED)){
            return collect([]);
        }

        $relation = $this->permissions($section->id, $container->id);

        if($checkEnabled){
            $relation = $relation->where('state', Permission::ENABLED);
        }

        return $relation->get();
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
     * @param string|Container $container
     * @param boolean $checkEnabled
     *
     * Return all the permissions the model has via roles.
     */
    public function getPermissionsViaRoles($section, $container, $checkEnabled = true): Collection
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        if($checkEnabled && (!$this->isEnabled() || !$section || !$container || $section->state != Section::ENABLED || $container->state != Container::ENABLED)){
            return collect([]);
        }

        return $this->load('roles', 'roles.permissions')
            ->roles->filter(function ($role) use ($checkEnabled){
                return $checkEnabled ? $role->state == Role::ENABLED : true;
            })->flatMap(function (Role $role) use ($section, $container){
                return $role->permissions($section->id, $container->id)->get();
            })->filter(function ($permission) use ($checkEnabled){
                return $checkEnabled ? $permission->state == Permission::ENABLED : true;
            })->sort()->values();
    }

    /**
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|Container $container
     * @param boolean $checkEnabled
     *
     * Return all the permissions the model has, both directly and via roles.
     */
    public function getAllPermissions($section, $container, $checkEnabled = true): Collection
    {
        $directPermissions = $this->getDirectPermissions($section, $container, $checkEnabled);

        $directPermissionsFiltered = $directPermissions->filter(function ($permission){return $permission->pivot->enabled;});

        return $this->getPermissionsViaRoles($section, $container, $checkEnabled)->filter(function ($rolePermission) use ($directPermissions){
                if($directPermissions->first(function ($p) use ($rolePermission){
                    return $p->name == $rolePermission->name;
                })){
                    return $directPermissions->pivot->enabled;
                }

                return true;
            })->merge($directPermissionsFiltered)
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
        return $this->resolveClass($role, Role::class);
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

    public function getPermissionsTree($container, $type = Constants::TREE_TYPE_GLOBAL, $checkEnabled = true) : array
    {
        $container = $this->resolveClass($container, Container::class);

        $sections = app(Section::class)->containerTree($container, $checkEnabled);

        return $this->parseChidrenForTree($sections, $container, $type, $checkEnabled);
    }

    protected function getPermissionPerTreeType($type, $section, $container, $checkEnabled)
    {
        switch ($type){
            case Constants::TREE_TYPE_ROLE:
                $perms = $this->getPermissionsViaRoles($section, $container, $checkEnabled);
                break;
            case Constants::TREE_TYPE_USER:
                $perms = $this->getDirectPermissions($section, $container, $checkEnabled);
                break;
            default:
                $perms = $this->getAllPermissions($section, $container,$checkEnabled);
        }

        return $perms;
    }

    protected function parseChidrenForTree($sections, $container, $type, $checkEnabled)
    {
        $result = [];
        foreach ($sections as $section){
            $children = $this->parseChidrenForTree($section['children'], $container, $type, $checkEnabled);
            $permissions = $this->parseCollectionForPermissionTree($this->getPermissionPerTreeType($type, $section['model'], $container, $checkEnabled));
            $result[is_array($section['model']) ? $section['model']['name'] : $section['model']->name] = [
                'permissions' => $permissions,
                'children' => $children,
                'model' => is_array($section['model']) ? $section['model'] : $section['model']->toArray(),
                'superadmin-reserved-section' => $section['superadmin-forced']
            ];
        }

        return $result;
    }

    public function isSuperuser()
    {
        return $this->hasRole(Config::superuser());
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
