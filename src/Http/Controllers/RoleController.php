<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Libraries\Config;

class RoleController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([Config::superuser()]);
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
