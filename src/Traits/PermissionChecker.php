<?php

namespace Idsign\Permission\Traits;

use Idsign\Permission\Libraries\Config;
use Illuminate\Foundation\Auth\User;
use Idsign\Permission\Exceptions\UnauthorizedException;
use Illuminate\Http\Request;

trait PermissionChecker
{
    use UserManagement;

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

    /**
     * @param Request $request
     * @param $permissions
     * @param null $sections
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    protected function checkPermissionByRequest(Request $request, $permissions, $sections = null)
    {
        $requestKey = Config::keyRequestContainerConfig();

        $request->validate([
            $requestKey => 'required'
        ]);

        $containerId = $request->input($requestKey);

        $user = $this->getLoggedUser();

        $containers = $user->getContainers(true);

        $container = $containers->first(function ($container) use ($containerId){
            return $container->id == $containerId;
        });

        if(!$container){
            throw UnauthorizedException::forContainer($containerId);
        }

        $this->checkPermission($user, $permissions, $container, $sections);
    }

    /**
     * @param $permissions
     * @param null $sections
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    protected function checkPermissionForAnyContainer($permissions, $sections = null)
    {
        $user = $this->getLoggedUser();

        $containers = $user->getContainers(true);

        $delta = $containers->filter(function ($container) use ($user, $permissions, $sections){
            try{
                $this->checkPermission($user, $permissions, $container, $sections);
            }catch (UnauthorizedException $ex) {
                return false;
            }
            return true;
        })->count() > 0;

        if(!$delta){
            throw UnauthorizedException::forPermissions($permissions);
        }
    }
}
