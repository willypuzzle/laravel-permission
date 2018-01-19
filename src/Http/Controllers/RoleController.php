<?php

namespace Idsign\Permission\Http\Controllers;

class RoleController extends PermissionRoleSectionController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::ROLE;
    }
}
