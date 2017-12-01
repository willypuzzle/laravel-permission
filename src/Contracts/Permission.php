<?php

namespace Idsign\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Permission
{
    const ENABLED = 1;
    const DISABLED = 0;
    const ALL_STATES  = [ self::DISABLED, self::ENABLED ];

    /**
     * Canonical permissions
     */
    const READ = 'read';
    const CREATE = 'create';
    const UPDATE = 'update';
    const DELETE = 'delete';


    /**
     * A permission can be applied to roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany;

    /**
     * Find a permission by its name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @throws \Idsign\Permission\Exceptions\PermissionDoesNotExist
     *
     * @return Permission
     */
    public static function findByName(string $name, $guardName): Permission;
}
