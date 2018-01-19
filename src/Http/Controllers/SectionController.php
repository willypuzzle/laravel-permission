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
     * @return mixed
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function data($type = null)
    {
        $this->checkForPermittedRoles();

        $query = $this->getModel()->query()->where(['guard_name' => $this->usedGuard()]);

        if($type){
            $query->where('type', $type);
        }

        return Datatable::of($query)->make(true);
    }
}
