<?php

namespace Idsign\Permission\Traits;
use Idsign\Permission\Libraries\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Willypuzzle\Helpers\Facades\General\Traits;
use Idsign\Permission\Exceptions\DoesNotUseProperTraits;
use Illuminate\Foundation\Auth\User as Authenticatable;

trait UserManagement{

    use GuardManagement;

    /**
     * @return Model
     * @throws DoesNotUseProperTraits
     */
    protected function getUserModel() : Model
    {
        $guard = $this->usedGuard();

        $userConfigs = Config::userModel($guard, true);

        $model = app($userConfigs['model']);

        $this->checkUserModelProperTrait($model);

        return $model;
    }

    /**
     * @return Authenticatable
     * @throws DoesNotUseProperTraits
     */
    protected function getLoggedUser() : Authenticatable
    {
        $user = Auth::guard($this->usedGuard())->user();
        $this->checkUserModelProperTrait($user);
        return $user;
    }

    /**
     * @param $roles
     * @param string|array|\Idsign\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @return bool
     * @throws DoesNotUseProperTraits
     */
    protected function checkIfLoggeUserHasRole($roles) : bool
    {
        $user = $this->getLoggedUser();
        return $user->hasRole($roles);
    }

    /**
     * @param Model $model
     * @throws DoesNotUseProperTraits
     */
    private function checkUserModelProperTrait(Model $model){
        if(!Traits::use($model, 'Idsign\Permission\Traits\HasRoles')){
            throw DoesNotUseProperTraits::create($model);
        }
    }

    /**
     * @return bool
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    protected function isSuperuser()
    {
        return $this->isRole(Config::superuser());
    }

    protected function isAdmin()
    {
        return $this->isRole(Config::admin());
    }

    protected function isRole($role)
    {
        return $this->getLoggedUser()->roles()->get()->filter(function ($el) use ($role){
                return $el->name == $role;
            })->count() > 0;
    }
}
