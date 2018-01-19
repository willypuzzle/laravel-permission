<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Role;
use Idsign\Vuetify\Facades\Datatable;
use Illuminate\Http\Request;
use Idsign\Permission\Traits\UserManagement;

abstract class UserController extends RoleCheckerController
{
    use UserManagement;

    protected $rolesField = 'roles';
    protected $passwordField = 'password';

    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function all()
    {
        $this->checkForPermittedRoles();

        return response()->json($this->getUserModel()::all()->toArray());
    }

    abstract protected function validateCreation(Request $request);

    /**
     * @param Request $request
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function create(Request $request)
    {
        $this->checkForPermittedRoles();

        $this->validateCreation($request);

        $data = $request->all();

        $user = $this->getUserModel()->fill($data);

        if($this->passwordField){
            $passwordField = $this->passwordField;
            $user->$passwordField = bcrypt(str_random(32));
        }

        $user->save();

        if(isset($data[$this->rolesField])){
            $roles = app(Role::class)->whereIn('id', $data[$this->rolesField])->get()->all();
            $user->syncRoles(...$roles);
        }

    }

    /**
     * @return mixed
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function data()
    {
        $this->checkForPermittedRoles();

        return Datatable::of($this->getUserModel()->newQuery()->where('deleted_at', NULL))->make(true);
    }

    /**
     * @param $userId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function delete($userId)
    {
        $this->checkForPermittedRoles();

        $user = $this->getUserModel()->findOrFail($userId);

        $user->delete();
    }

    /**
     * @param $userId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function get($userId)
    {
        $this->checkForPermittedRoles();

        return response()->json($this->getUserModel()->findOrFail($userId)->toArray());
    }

    /**
     * @param Request $request
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function multi_delete(Request $request)
    {
        $this->checkForPermittedRoles();

        $validatedData = $request->validate([
            'items' => 'required|json'
        ]);

        foreach (json_decode($validatedData['items'], true) as $item){
            $user = $this->getUserModel()->findOrFail($item['id']);
            $user->delete();
        }
    }

    /**
     * @param Request $request
     * @param $userId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function update(Request $request, $userId)
    {
        $this->validateUpdate($request, $userId);

        $user = $this->getUserModel()->findOrFail($userId);

        $rolesInput = $request->input($this->rolesField);

        if($rolesInput){
            $rolePseudoModels = app(Role::class)->whereIn('id', $rolesInput)->get()->all();
            $user->syncRoles(...$rolePseudoModels);
        }else{
            $user->update($request->all());
        }
    }

    abstract protected function validateUpdate(Request $request, $userId);

    abstract protected function validateUpdateComplete(Request $request, $userId);

    /**
     * @param Request $request
     * @param $userId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function updateComplete(Request $request, $userId)
    {
        $this->validateUpdateComplete($request, $userId);

        $user = $this->getUserModel()->findOrFail($userId);

        $user->update($request->all());
    }
}