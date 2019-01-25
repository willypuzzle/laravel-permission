<?php
namespace Idsign\Libraries;


class Config
{
    const ROOT = 'permission.';

    public static function userConfig()
    {
        return self::config(self::ROOT.'user');
    }

    private static function config($key)
    {
        return config($key);
    }
}
