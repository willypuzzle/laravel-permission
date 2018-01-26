<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Section;
use Idsign\Permission\Contracts\Permission;
use Idsign\Permission\Contracts\Role;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Willypuzzle\Helpers\Contracts\HttpCodes;

class MatrixController extends RoleCheckerController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser'), config('permission.roles.admin')]);
    }

    /**
     * @param $sectionId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function roleMatrixInit($sectionId)
    {
        $this->checkForPermittedRoles();

        $guard = $this->usedGuard();

        $section = app(Section::class)->where(['id' => $sectionId, 'guard_name' => $guard])->firstOrFail();

        $roles = app(Role::class)::where('guard_name', $guard)->all();

        if(!$this->isSuperuser()){
            $roles = $roles->filter(function ($el){
                return $el->name !== config('permission.roles.superuser');
            });
        }

        $permissions = app(Permission::class)->where('guard_name', $guard)->get();

        $matrix = [];
        foreach ($roles as $role){
            $matrix[$role->id] = [];
            foreach ($permissions as $permission){
                $matrix[$role->id][$permission->id] = [
                    'type' => 'boolean',
                    'value' => $role->hasPermissionTo($permission, $section, false)
                ];
            }
        }

        return response()->json($matrix);
    }

    /**
     * @param Request $request
     * @param $sectionId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function roleMatrixUpdate(Request $request, $sectionId)
    {
        $this->checkForPermittedRoles();

        $guard = $this->usedGuard();

        $section = app(Section::class)->where(['id' => $sectionId, 'guard_name' => $guard])->firstOrFail();


        $this->validate($request, [
            'row' => [
                'required',
                'exists:roles,id'
            ],
            'column' => [
                'required',
                'exists:'.config('permission.table_names.permissions').',id'
            ],
            'value' => [
                'required',
                'boolean'
            ],
            'type' => [
                'required',
                Rule::in(['boolean']),
            ]
        ]);


        $roleId = $request->input('row');
        $permissionId = $request->input('column');
        $value = $request->input('value');

        $role = app(Role::class)->where(['id' => $roleId, 'guard_name' => $guard])->firstOrFail();

        if(!$this->isSuperuser() && $role->name == config('permission.roles.superuser')){
            return response()->json([], HttpCodes::FORBIDDEN);
        }

        $permission = app(Permission::class)->where(['id' => $permissionId, 'guard_name' => $guard])->firstOrFail();

        if($value){
            if(!$role->hasPermissionTo($permission, $section, false)){
                $role->givePermissionTo($permission, $section);
            }
        }else{
            if($role->hasPermissionTo($permission, $section, false)){
                $role->revokePermissionTo($permission, $section);
            }
        }
    }

    /**
     * @param $sectionId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function userMatrixInit($sectionId)
    {
        $this->checkForPermittedRoles();

        $guard = $this->usedGuard();

        $section = app(Section::class)->where(['id' => $sectionId, 'guard_name' => $guard])->firstOrFail();

        $users = $this->getUserModel()::all();

        if(!$this->isSuperuser()){
            $users = $users->filter(function ($el1){
                return $el1->roles()->get()->filter(function ($el2){
                    return $el2 == config('permission.roles.superuser');
                })->count() == 0;
            });
        }

        $permissions = app(Permission::class)->where('guard_name', $guard)->get();

        $matrix = [];
        foreach ($users as $user){
            $matrix[$user->id] = [];
            foreach ($permissions as $permission){
                $matrix[$user->id][$permission->id] = [
                    'type' => 'boolean',
                    'value' => $user->hasDirectPermission($permission, $section, false)
                ];
            }
        }

        return response()->json($matrix);
    }

    /**
     * @param Request $request
     * @param $sectionId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function userMatrixUpdate(Request $request, $sectionId)
    {
        $this->checkForPermittedRoles();

        $guard = $this->usedGuard();

        $section = app(Section::class)->where(['id' => $sectionId, 'guard_name' => $guard])->firstOrFail();

        $this->validate($request, [
            'row' => [
                'required',
                'exists:users,id'
            ],
            'column' => [
                'required',
                'exists:'.config('permission.table_names.permissions').',id'
            ],
            'value' => [
                'required',
                'boolean'
            ],
            'type' => [
                'required',
                Rule::in(['boolean']),
            ]
        ]);

        $userId = $request->input('row');
        $permissionId = $request->input('column');
        $value = $request->input('value');

        $user = $this->getUserModel()->where('id', $userId)->firstOrFail();

        if(!$this->isSuperuser()){
            if($user->roles()->get()->filter(function ($el2){ return $el2 == config('permission.roles.superuser'); })->count() > 0)
            {
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

        $permission = app(Permission::class)->where(['id' => $permissionId, 'guard_name' => $guard])->firstOrFail();

        if($value){
            if(!$user->hasDirectPermission($permission, $section, false)){
                $user->givePermissionTo($permission, $section);
            }
        }else{
            if($user->hasDirectPermission($permission, $section, false)){
                $user->revokePermissionTo($permission, $section);
            }
        }
    }
}
