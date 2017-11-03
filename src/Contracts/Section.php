<?php

namespace Idsign\Permission\Contracts;

interface Section
{
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
    public static function findByName(string $name, $guardName): Section;
}
