<?php

namespace Idsign\Permission\Http\Controllers;

class SectionController extends PermissionRoleSectionController
{
    protected function delta() : string
    {
        return self::SECTION;
    }
}
