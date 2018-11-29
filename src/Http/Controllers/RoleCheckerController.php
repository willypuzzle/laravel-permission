<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Exceptions\UnauthorizedException;
use Idsign\Permission\Traits\UserManagement;
use Idsign\Permission\Http\Controllers\Controller;

class RoleCheckerController extends Controller
{
    use UserManagement;

    private $roles = [];

    protected function addPermittedRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws UnauthorizedException
     */
    protected function checkForPermittedRoles()
    {
        if(count($this->roles) != 0){
            if (!$this->checkIfLoggeUserHasRole($this->roles)) {
                throw UnauthorizedException::forRoles([]);
            }
        }
    }
}
