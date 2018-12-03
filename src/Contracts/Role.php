<?php

namespace Idsign\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Role
{
    const ENABLED = 1;
    const DISABLED = 0;
    const ALL_STATES  = [ self::DISABLED, self::ENABLED ];

    /**
     * A role may be given various permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions($sectionId = null, $containerId = null, $permissionId = null): BelongsToMany;

    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Idsign\Permission\Contracts\Role
     *
     * @throws \Idsign\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name, $guardName): Role;

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|\Idsign\Permission\Contracts\Permission $permission
     *
     * @param string|\Idsign\Permission\Contracts\Section $section
     *
     * @param string|\Idsign\Permission\Contracts\Container $container
     *
     * @return bool
     */
    public function hasPermissionTo($permission, $section, $container, $checkEnabled = true): bool;
}
