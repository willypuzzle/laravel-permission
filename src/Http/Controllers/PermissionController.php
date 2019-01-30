<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Libraries\Config;

class PermissionController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([Config::superuser()]);
    }

    protected function delta() : string
    {
        return self::PERMISSION;
    }
}
