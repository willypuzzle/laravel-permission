<?php

namespace Idsign\Permission\Traits;

use Illuminate\Support\Collection;
use Idsign\Permission\PermissionRegistrar;
use Idsign\Permission\Contracts\{
    Permission,
    Section
};
use Idsign\Permission\Exceptions\GuardDoesNotMatch;

trait HasPermissions
{
    /**
     * Grant the given permission(s) to a role.
     *
     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * @return $this
     */
    public function givePermissionTo($permissions, $section)
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        $permissions = collect([$permissions])
            ->flatten()
            ->map(function ($permission) {
                return $this->getStoredPermission($permission);
            })
            ->each(function ($permission) {
                $this->ensureModelSharesGuard($permission);
            })
            ->all();

        foreach($permissions as $permission){
            $this->permissions()->save($permission, ['section_id' => $section->id]);
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param string|array|\Idsign\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * @return $this
     */
    public function syncPermissions($permissions, $section)
    {
        $this->permissions()->detach();

        return $this->givePermissionTo($permissions, $section);
    }

    /**
     * Revoke the given permission.
     *
     * @param \Idsign\Permission\Contracts\Permission|string $permission
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * @return $this
     */
    public function revokePermissionTo($permission, $section)
    {
        if (is_string($section)) {
            $section = app(Section::class)->findByName($section, $this->getDefaultGuardName());
        }

        $this->permissions()->wherePivot('section_id', '=', $section->id)->detach($this->getStoredPermission($permission));

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
        if (is_string($permissions)) {
            return app(Permission::class)->findByName($permissions, $this->getDefaultGuardName());
        }

        if (is_array($permissions)) {
            return app(Permission::class)
                ->whereIn('name', $permissions)
                ->where('guard_name', $this->getGuardNames())
                ->get();
        }

        return $permissions;
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
