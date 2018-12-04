<?php

namespace Idsign\Permission;

use Idsign\Permission\Contracts\Container;
use Idsign\Permission\Contracts\Section;
use Idsign\Permission\Exceptions\MalformedArguments;
use Idsign\Permission\Exceptions\SectionDoesNotExist;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Idsign\Permission\Contracts\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Idsign\Permission\Exceptions\PermissionDoesNotExist;

class PermissionRegistrar
{
    /** @var \Illuminate\Contracts\Auth\Access\Gate */
    protected $gate;

    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    /** @var string */
    protected $cacheKeyPermissions = 'idsign.permission.cache.permissions';

    /** @var string */
    protected $cacheKeySections = 'idsign.permission.cache.sections';


    protected $cacheKeyContainers = 'idsign.permission.cache.containers';

    public function __construct(Gate $gate, Repository $cache)
    {
        $this->gate = $gate;
        $this->cache = $cache;
    }

    public function registerPermissions(): bool
    {
        $this->gate->before(function (Authenticatable $user, string $ability, array $arguments) {
            try {
                if (method_exists($user, 'hasPermissionTo')) {
                    if(count($arguments) != 2){
                        throw MalformedArguments::create($arguments);
                    }
                    return $user->hasPermissionTo($ability, $arguments[0], $arguments[1]) ?: null;
                }
            } catch (PermissionDoesNotExist $e) {
                return null;
            } catch (SectionDoesNotExist $e) {
                return null;
            }
        });

        return true;
    }

    public function forgetCachedPermissions()
    {
        $this->cache->forget($this->cacheKeyPermissions);
        $this->cache->forget($this->cacheKeySections);
        $this->cache->forget($this->cacheKeyContainers);
    }

    public function getPermissions(): Collection
    {
        return $this->cache->remember($this->cacheKeyPermissions, config('permission.cache_expiration_time'), function () {
            return app(Permission::class)->with('roles')->get();
        });
    }

    public function getSections(): Collection
    {
        return $this->cache->remember($this->cacheKeySections, config('permission.cache_expiration_time'), function () {
            return app(Section::class)->with([])->get();
        });
    }

    public function getContainers(): Collection
    {
        return $this->cache->remember($this->cacheKeyContainers, config('permission.cache_expiration_time'), function () {
            return app(Container::class)->with([])->get();
        });
    }
}
