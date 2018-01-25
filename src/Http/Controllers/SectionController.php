<?php

namespace Idsign\Permission\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Idsign\Vuetify\Facades\Datatable;

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
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function all($type = null){
        $this->checkForPermittedRoles();

        $where = ['guard_name' => $this->usedGuard()];

        if($type){
            $where['section_type_id'] = $type;
        }

        return response()->json($this->getModel()->where($where)->get()->toArray());
    }
}
