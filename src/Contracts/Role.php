<?php

namespace Idsign\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Role
{
    /**
     * A role may be given various permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions(): BelongsToMany;

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
     * @return bool
     */
    public function hasPermissionTo($permission, $section): bool;
}
