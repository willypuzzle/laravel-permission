<?php

namespace Idsign\Permission\Traits;
use Illuminate\Database\Eloquent\Model;

trait GuardManagement{

    protected function usedGuard() : string
    {
        if(isset($this->guardUsed) && is_string($this->guardUsed)){
            return $this->guardUsed;
        }
        return config('auth.defaults.guard');
    }
}
