<?php

namespace Idsign\Permission\Traits;

use Illuminate\Foundation\Auth\User;
use Idsign\Permission\Exceptions\UnauthorizedException;

trait PermissionChecker
{
    private $section = null;

    protected function setSection($section)
    {
        $this->section = $section;
    }

    protected function getSection()
    {
        return $this->section;
    }

    protected function checkPermission(User $user, $permissions, $sections = null)
    {
        if(is_string($permissions)){
            $permissions = [$permissions];
        }

        if(is_string($sections)){
            $sections = [$sections];
        }

        foreach ($permissions as $permission){
            if(is_null($sections)){
                if(!$user->hasPermissionTo($permission, $this->section)){
                    throw UnauthorizedException::forPermissions([$permission, $this->section]);
                }
            }else{
                $sectionCtrl = false;
                foreach ($sections as $section){
                    if($user->hasPermissionTo($permission, $section)){
                        $sectionCtrl = true;
                    }
                }
                if(!$sectionCtrl){
                    throw UnauthorizedException::forPermissions([$permission, $section]);
                }
            }
        }
    }
}
