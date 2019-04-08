<?php
namespace Idsign\Permission\Libraries;


class Config
{
    const ROOT = 'permission.'; // ATTENTION! end this variable value with a point

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

    private static function containerConfig($key)
    {
        return self::config('container.'.$key);
    }

    private static function requestContainerConfig($key)
    {
        return self::containerConfig('request.'.$key);
    }

    public static function keyRequestContainerConfig()
    {
        return self::requestContainerConfig('key');
    }

    public static function tableNames($table = null)
    {
        if(!$table){
            return self::config('table_names');
        }else{
            return self::config('table_names.'.$table);
        }
    }

    public static function superuser()
    {
        return self::role('superuser');
    }

    public static function admin()
    {
        return self::role('admin');
    }

    public static function operator()
    {
        return self::role('operator');
    }

    private static function role($role)
    {
        return self::config('roles.'.$role);
    }

    public static function cacheExpirationTime()
    {
        return self::config('cache_expiration_time');
    }

    public static function sectionsTable()
    {
        return self::tableNames('sections');
    }

    public static function permissionsTable()
    {
        return self::tableNames('permissions');
    }

    public static function rolesTable()
    {
        return self::tableNames('roles');
    }

    public static function containersTable()
    {
        return self::tableNames('containers');
    }

    public static function roleHasPermissionsTable()
    {
        return self::tableNames('role_has_permissions');
    }

    public static function containerRoleTable()
    {
        return self::tableNames('container_role');
    }

    public static function containerSectionTable()
    {
        return self::tableNames('container_section');
    }

    public static function modelHasPermissionsTable()
    {
        return self::tableNames('model_has_permissions');
    }

    public static function modelHasRolesTable()
    {
        return self::tableNames('model_has_roles');
    }

    public static function labels()
    {
        return self::config('labels');
    }

    private static function model($model)
    {
        return self::config('models.'.$model);
    }

    public static function permissionModel()
    {
        return self::model('permission');
    }

    public static function roleModel()
    {
        return self::model('role');
    }

    public static function sectionModel()
    {
        return self::model('section');
    }

    public static function containerModel()
    {
        return self::model('container');
    }

    public static function userModel($guard, $complete = false)
    {
        if(!$complete){
            return self::config('user.model.'.$guard.'.model');
        }else{
            return self::config('user.model.'.$guard);
        }
    }

    public static function userModels()
    {
        return self::config('user.model');
    }

    public static function userFields($key = null)
    {
        if($key){
            return self::config('user.fields.'.$key);
        }else{
            return self::config('user.fields');
        }
    }

    public static function userIdFieldName()
    {
        return self::userFields('id.field_name');
    }

    public static function userNameFieldName()
    {
        return self::userFields('name.field_name');
    }

    public static function userSurnameFieldName()
    {
        return self::userFields('name.field_name');
    }

    public static function userUsernameFieldName()
    {
        return self::userFields('username.field_name');
    }

    public static function userPasswordFieldName()
    {
        return self::userFields('password.field_name');
    }

    public static function userUsernameRules()
    {
        return self::userFields('username.rules');
    }

    private static function userState($key)
    {
        return self::userFields('state.'.$key);
        // return self::config('user.fields.state.'.$key);
    }

    public static function userStateFieldName()
    {
        return self::userState('field_name');
    }

    public static function userStateEnabled()
    {
        return self::userState('values.enabled');
    }

    public static function userStateDisabled()
    {
        return self::userState('values.disabled');
    }

    public static function userStateDefault()
    {
        return self::userState('values.default');
    }

    public static function crud($index = null)
    {
        if(!$index){
            return self::config('crud');
        }else{
            return self::config('crud.'.$index);
        }
    }
}
