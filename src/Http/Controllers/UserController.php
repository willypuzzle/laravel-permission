<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Libraries\Config;
use Idsign\Permission\Contracts\Role;
use Idsign\Vuetify\Facades\Datatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Willypuzzle\Helpers\Contracts\HttpCodes;

abstract class UserController extends RoleCheckerController
{
    protected $rolesField = 'roles';
    protected $passwordField = 'password';

    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser'), config('permission.roles.admin')]);
    }

    public function config()
    {
        return Config::userConfig();
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function all()
    {
        $this->checkForPermittedRoles();

        $collection = $this->getUserModel()::all();

        if(!$this->isSuperuser()){
            $collection = $collection->filter(function ($el1){
               return $el1->roles()->get()->filter(function ($el2){
                    return $el2->name == config('permission.roles.superuser');
               })->count() == 0;
            });
        }

        $array = $collection->all();

        usort($array, function ($el1, $el2){
            return self::sorter($el1, $el2);
        });

        return response()->json($array);
    }

    public static function sorter($el1, $el2)
    {
        $el1Key = $el1->surname ? $el1->surname.' '.$el1->name : $el1->name;
        $el2Key = $el2->surname ? $el2->surname.' '.$el2->name : $el2->name;

        $delta = strcmp($el1Key, $el2Key);

        return $delta;
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
            if(!$this->isSuperuser()){
                if(app(Role::class)->whereIn('id', $data[$this->rolesField])->get()->filter(function ($el){
                    return $el->name === config('permission.roles.superuser');
                })->count() > 0){
                    return response()->json([], HttpCodes::FORBIDDEN);
                }
            }
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

        $query = $this->getUserModel()->newQuery()->where('deleted_at', NULL);

        if(!$this->isSuperuser()){
            $superuserRoleModel = app(Role::class)->findByName(config('permission.roles.superuser'), $this->usedGuard());
            $userModel = app(config('permission.user.model.'.$this->usedGuard().'.model'));
            $query->whereNotExists(function ($query) use ($userModel, $superuserRoleModel){
                $pivotTableName = config('permission.table_names.model_has_roles');
                $query->select(DB::raw(1))
                        ->from($pivotTableName)
                        ->whereRaw($userModel->getTable().'.'.$userModel->getKeyName().' = '.$pivotTableName.'.model_id')
                        ->where($pivotTableName.'.model_type', $userModel->roles()->getMorphClass())
                        ->where($pivotTableName.'.role_id', $superuserRoleModel->id);
            });
        }

        $data = Datatable::of($query)->make(true);

        $dataComplete = $data->getData(true);
        $data = $dataComplete['data'];

        foreach ($data as $key => $d){
            $data[$key]['roles'] = $this->getUserModel()->findOrFail($d['id'])->roles()->get();
        }

        $dataComplete['data'] = $data;

        return response()->json($dataComplete);
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

        if(!$this->isSuperuser()){
            if($user->roles()->get()->filter(function ($el){
                return $el->name === config('permission.roles.superuser');
            })->count() > 0)
            {
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

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
            if(!$this->isSuperuser()){
                if($user->roles()->get()->filter(function ($el){
                        return $el->name === confing('permission.roles.superuser');
                    })->count() > 0)
                {
                    return response()->json([], HttpCodes::FORBIDDEN);
                }
            }
        }

        foreach (json_decode($validatedData['items'], true) as $item){
            $user = $this->getUserModel()->findOrFail($item['id']);
            $user->delete();
        }
    }

    /**
     * @param Request $request
     * @param $userId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function update(Request $request, $userId)
    {
        $this->checkForPermittedRoles();

        $this->validateUpdate($request, $userId);

        $user = $this->getUserModel()->findOrFail($userId);

        if(!$this->isSuperuser()){
            if($user->roles()->get()->filter(function ($el){
                return $el->name === config('permission.roles.superuser');
            })->count() > 0)
            {
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

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

        return $this->updateCompleteAddons($request, $user);
    }

    protected function updateCompleteAddons(Request $request, $user)
    {
        //This function is useful to perform more action on updateComplete
    }
}
