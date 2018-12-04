<?php

namespace Idsign\Permission\Traits;

use Illuminate\Support\Collection;
use Idsign\Permission\PermissionRegistrar;
use Idsign\Permission\Contracts\{Container, Permission, Role, Section};
use Idsign\Permission\Exceptions\GuardDoesNotMatch;

trait HasPermissions
{
    /**
     * Grant the given permission(s) to a role.
     *
     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|\Idsign\Permission\Contracts\Container $container
     * @param boolean|array
     *
     * @return $this
     */
    public function givePermissionTo($permissions, $section, $container, $flags = true)
    {
        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        $permissions = collect([$permissions])
            ->flatten()
            ->map(function ($permission) {
                return $this->getStoredPermission($permission);
            })
            ->each(function ($permission) {
                $this->ensureModelSharesGuard($permission);
            })
            ->all();

        $flagsArray = is_array($flags) ? $flags : [];
        if(is_bool($flags)){
            foreach ($permissions as $key => $value){
                $flagsArray[] = $flags;
            }
        }

        if(count($flagsArray) < count($permissions)){
            for($i = count($flagsArray); $i < count($permissions); $i++){
                $flagsArray[$i] = false;
            }
        }

        foreach($permissions as $key => $permission){
            $data = [
                'section_id' => $section->id,
                'container_id' => $container->id
            ];

            if( !($this instanceof Role) ){
                $data['enabled'] = $flagsArray[$key];
            }

            $this->permissions()->save($permission, $data);
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * @param mixed $name
     * @param string $class
     * @return mixed
     */
    protected function resolveClass($name, $class)
    {
        if (is_string($name)) {
            $name = app($class)->findByName($name, $this->getDefaultGuardName());
        }

        return $name;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|\Idsign\Permission\Contracts\Container $container
     * @param boolean|array
     *
     * @return $this
     */
    public function syncPermissions($permissions, $section, $container, $flags = true)
    {
        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        $this->permissions()
             ->wherePivot('section_id', '=', $section->id)
             ->wherePivot('container_id', '=', $container->id)
             ->detach();

        return $this->givePermissionTo($permissions, $section, $container, $flags);
    }

    /**
     * Revoke the given permission.
     *
     * @param array|\Idsign\Permission\Contracts\Permission|string $permissions
     * @param string|\Idsign\Permission\Contracts\Section $section
     * @param string|\Idsign\Permission\Contracts\Container $container
     *
     * @return $this
     */
    public function revokePermissionTo($permissions, $section, $container)
    {
        $section = $this->resolveClass($section, Section::class);
        $container = $this->resolveClass($container, Container::class);

        $this->permissions()
             ->wherePivot('section_id', '=', $section->id)
             ->wherePivot('container_id', '=', $container->id)
             ->detach($this->getStoredPermission($permissions));

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return \Idsign\Permission\Contracts\Permission|Collection|\Idsign\Permission\Contracts\Permission
     */
    protected function getStoredPermission($permissions)
    {
        if (is_array($permissions)) {
            return collect($permissions)->map(function ($permission) {
                return $this->resolveClass($permission, Permission::class);
            });
            /*return app(Permission::class)
                ->whereIn('name', $permissions)
                ->where('guard_name', $this->getGuardNames())
                ->get();*/
        }

        return $this->resolveClass($permissions, Permission::class);
    }

    /**
     * @param \Idsign\Permission\Contracts\Permission|\Idsign\Permission\Contracts\Role $roleOrPermission
     *
     * @throws \Idsign\Permission\Exceptions\GuardDoesNotMatch
     */
    protected function ensureModelSharesGuard($roleOrPermission)
    {
        if (! $this->getGuardNames()->contains($roleOrPermission->guard_name)) {
            throw GuardDoesNotMatch::create($roleOrPermission->guard_name, $this->getGuardNames());
        }
    }

    protected function getGuardNames(): Collection
    {
        if ($this->guard_name) {
            return collect($this->guard_name);
        }

        return collect(config('auth.guards'))
            ->map(function ($guard) {
                return config("auth.providers.{$guard['provider']}.model");
            })
            ->filter(function ($model) {
                return get_class($this) === $model;
            })
            ->keys();
    }

    protected function getDefaultGuardName(): string
    {
        $default = config('auth.defaults.guard');

        return $this->getGuardNames()->first() ?: $default;
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
