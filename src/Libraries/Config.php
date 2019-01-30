<?php
namespace Idsign\Libraries;


class Config
{
    const ROOT = 'permission.';

    public static function userConfig()
    {
        return self::config('user');
    }

    public static function rolesConfig()
    {
        return self::config('roles');
    }

    private static function config($key)
    {
        return config(self::ROOT.$key);
    }
}
