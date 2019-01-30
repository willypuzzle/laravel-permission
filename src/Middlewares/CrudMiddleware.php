<?php

namespace Idsign\Permission\Middlewares;

use Closure;
use Idsign\Permission\Exceptions\MalformedParameter;
use Idsign\Permission\Libraries\Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Idsign\Permission\Exceptions\UnauthorizedException;

class CrudMiddleware
{
    public function handle(Request $request, Closure $next, $argumentsx)
    {
        if (Auth::guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $arguments = explode(',', $argumentsx);

        if(count($arguments) < 2){
            throw MalformedParameter::create($argumentsx);
        }

        $section = $arguments[0];
        $container = $arguments[1];

        $nullable = true;
        if(count($arguments) > 2){
            if($arguments[2] != 'nullable'){
                $nullable = false;
            }
        }

        list($object, $index) = $this->getRouteParameters($request->route());

        $permissions = $this->resolveIndex($index);

        foreach ($permissions as $permission){
            if($nullable && $permission === null){
                return $next($request);
            }else if(!$nullable && $permission === null){
                //not accepted
            }else if (Auth::user()->can($permission, [$section, $container])) {
                return $next($request);
            }
        }

        throw UnauthorizedException::forPermissions([$permissions]);
    }

    /**
     * @return array
     * */
    private function getRouteParameters(Route $route) : array {
        $routeCompleteName = $route->getName();

        $data = explode('.', $routeCompleteName);

        $index = array_pop($data);

        return [implode('.', $data), $index];
    }

    /**
     * @return array
     * */
    private function resolveIndex(string $index) : array {
        return array_flatten([Config::crud($index)]);
    }
}
