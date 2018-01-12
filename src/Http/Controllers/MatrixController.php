<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Section;
use Idsign\Permission\Contracts\Permission;
use Idsign\Permission\Contracts\Role;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class MatrixController extends RoleCheckerController
{
    public function roleMatrixInit($sectionId)
    {
        $guard = $this->usedGuard();

        $section = app(Section::class)->where(['id' => $sectionId, 'guard_name' => $guard])->firstOrFail();

        $roles = app(Role::class)::all();
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

    public function roleMatrixUpdate(Request $request, $sectionId)
    {
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

    public function userMatrixInit($sectionId)
    {
        $guard = $this->usedGuard();

        $section = app(Section::class)->where(['id' => $sectionId, 'guard_name' => $guard])->firstOrFail();

        $users = $this->getUserModel()::all();

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

    public function userMatrixUpdate(Request $request, $sectionId)
    {
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
