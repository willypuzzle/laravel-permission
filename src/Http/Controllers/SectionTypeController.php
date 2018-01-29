<?php

namespace Idsign\Permission\Http\Controllers;

use Illuminate\Http\Request;

class SectionTypeController extends PermissionRoleSectionController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::SECTION_TYPE;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function all(Request $request){
        return response()->json($this->getModel()->where(['guard_name' => $this->usedGuard()])->get()->toArray());
    }
}
