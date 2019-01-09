<?php

namespace Idsign\Permission\Http\Controllers;

class PermissionController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::PERMISSION;
    }
}
