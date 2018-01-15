<?php

namespace Idsign\Permission\Http\Controllers;

class PermissionController extends PermissionRoleSectionController
{
    protected function delta() : string
    {
        return self::PERMISSION;
    }
}
