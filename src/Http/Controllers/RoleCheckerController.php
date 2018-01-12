<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Traits\GuardManagement;
use Idsign\Permission\Traits\UserManagement;
use Illuminate\Auth\AuthenticationException;
use Idsign\Permission\Http\Controllers\Controller;

class RoleCheckerController extends Controller
{
    use GuardManagement, UserManagement;

    private $roles = [];

    protected function addPermittedRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    protected function checkForPermittedRoles()
    {
        if(count($this->roles) != 0){
            if (!$this->checkIfLoggeUserHasRole($this->roles)) {
                throw new AuthenticationException('Wrong or unset role', [$this->usedGuard()]);
            }
        }
    }
}
