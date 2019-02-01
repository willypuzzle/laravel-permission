<?php

namespace Idsign\Permission\Traits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait GuardManagement{

    protected function usedGuard() : string
    {
        if(isset($this->guardUsed) && is_string($this->guardUsed)){
            return $this->guardUsed;
        }
        return config('auth.defaults.guard');
    }

    protected function getUsedGuard()
    {
        $guards = array_keys(config('auth.guards'));
        foreach ($guards as $guard) {
            if(Auth::guard($guard)->check()){
                return $guard;
            }
        }
    }
}
