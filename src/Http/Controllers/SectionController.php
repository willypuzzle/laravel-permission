<?php

namespace Idsign\Permission\Http\Controllers;

use Illuminate\Http\Request;

class SectionController extends PermissionRoleSectionController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::SECTION;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function all(Request $request){
        $this->checkForPermittedRoles();

        $where = ['guard_name' => $this->usedGuard()];

        $parent = $request->input('section_id');

        if($parent){
            $where['section_id'] = $parent;
        }

        return response()->json($this->getModel()->where($where)->get()->toArray());
    }
}
