<?php

namespace Idsign\Permission\Traits;

use Illuminate\Foundation\Auth\User;
use Idsign\Permission\Exceptions\UnauthorizedException;

trait PermissionChecker
{
    use UserManagement;

    private $section = null;

    protected function setSection($section)
    {
        $this->section = $section;
    }

    protected function getSection()
    {
        return $this->section;
    }

    /**
     * @param User $user
     * @param $permissions
     * @param $container
     * @param null $sections
     */
    protected function checkPermission(User $user, $permissions, $container, $sections = null)
    {
        if($this->isSuperuser()){
            return;
        }

        if(is_string($permissions)){
            $permissions = [$permissions];
        }

        if(is_string($sections)){
            $sections = [$sections];
        }

        foreach ($permissions as $permission){
            if(is_null($sections)){
                if(!$user->hasPermissionTo($permission, $this->section, $container)){
                    throw UnauthorizedException::forPermissions([$permission, $this->section]);
                }
            }else{
                $sectionCtrl = false;
                foreach ($sections as $section){
                    if($user->hasPermissionTo($permission, $section, $container)){
                        $sectionCtrl = true;
                    }
                }
                if(!$sectionCtrl){
                    throw UnauthorizedException::forPermissions([$permission, $section]);
                }
            }
        }
    }

    /**
     * @param $permissions
     * @param $container
     * @param null $sections
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    protected function checkPermissionForLoggedUser($permissions, $container, $sections = null)
    {
        $user = $this->getLoggedUser();

        $this->checkPermission($user, $permissions, $container, $sections);
    }
}
