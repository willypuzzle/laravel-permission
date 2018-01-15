<?php

namespace Idsign\Permission\Http\Controllers;

class RoleController extends PermissionRoleSectionController
{
    protected function delta() : string
    {
        return self::ROLE;
    }
}
