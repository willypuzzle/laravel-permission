<?php

namespace Idsign\Permission\Traits;
use Illuminate\Database\Eloquent\Model;

trait GuardManagement{

    protected function usedGuard() : string
    {
        return config('auth.defaults.guard');
    }
}