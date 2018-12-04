<?php

namespace Idsign\Permission\Middlewares;

use Closure;
use Idsign\Permission\Exceptions\MalformedParameter;
use Illuminate\Support\Facades\Auth;
use Idsign\Permission\Exceptions\UnauthorizedException;

class PermissionMiddleware
{
    public function handle($request, Closure $next, $permission)
    {
        if (Auth::guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission);

        foreach ($permissions as $permissionx) {
            $permission = explode(':', $permissionx);
            if(count($permission) != 2){
                throw MalformedParameter::create($permissionx);
            }
            $arguments = explode(',', $permission[1]);
            if (Auth::user()->can($permission[0], $arguments)) {
                return $next($request);
            }
        }

        throw UnauthorizedException::forPermissions($permissions);
    }
}
