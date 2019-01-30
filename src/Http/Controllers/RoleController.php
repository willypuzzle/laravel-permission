<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Libraries\Config;

class RoleController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::ROLE;
    }

    public function config()
    {
        return Config::rolesConfig();
    }
}
