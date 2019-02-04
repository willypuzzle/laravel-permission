<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Libraries\Config;
use Idsign\Permission\Contracts\Role as RoleInterface;
use Idsign\Vuetify\Facades\Datatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Willypuzzle\Helpers\Contracts\HttpCodes;

class UserController extends RoleCheckerController
{
    protected $rolesField = 'roles';
    protected $passwordField = 'password';

    public function __construct()
    {
        $this->addPermittedRoles([Config::superuser(), Config::admin()]);
    }

    public function config()
    {
        return Config::userConfig();
    }

    /**
     * @return array
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function loggedUser()
    {
        $userModel = $this->getLoggedUser();
        $userArray = $userModel->toArray();
        $userArray['roles'] = $userModel->roles->toArray();
        return $userArray;
    }

    /**
     * @return mixed
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function data()
    {
        $this->checkForPermittedRoles();

        list($query, $model) = $this->buildQueryForData();

        list($loggedUser, $allRoles) = $this->getStuffForData();

        return Datatable::of($query)
            ->editColumn('roles', function($c) use ($model) {
                return $model
                        ->findOrFail($c->id)
                        ->roles()
                        ->get()
                        ->map(function ($el){
                            return $el->name;
                        })
                        ->toArray();
            })
            ->editColumn('all-roles', function ($c) use ($allRoles, $loggedUser){
                if($c->id == $loggedUser->id) {
                    $allRoles = $allRoles->map(function ($el){
                        $el = $el->toArray();
                        if(($this->isSuperuser() && $el['name'] == Config::superuser()) || (!$this->isSuperuser() && $el['name'] == Config::admin())){
                            $el['disable'] = true;
                        }else{
                            $el['disable'] = false;
                        }
                        return $el;
                    });
                }
                return array_map(function ($el){
                    $el['value'] = $el['name'];
                    return $el;
                }, $allRoles->values()->toArray());
            })
            ->editColumn('no-delete', function ($c) use ($loggedUser){
                return $c->id == $loggedUser->id;
            })
            ->editColumn('no-check', function ($c) use ($loggedUser){
                return $c->id == $loggedUser->id;
            })->make(true);
    }

    private function getStuffForData()
    {
        $loggedUser = $this->getLoggedUser();

        $allRoles = app(RoleInterface::class)
            ->where('guard_name', $this->usedGuard())
            ->get()->filter(function ($el){
                if(!$this->isSuperuser()){
                    return $el->name != Config::superuser();
                }
                return true;
            });

        return [$loggedUser, $allRoles];
    }

    /**
     * @return array
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    private function buildQueryForData()
    {
        $model = $this->getUserModel();
        $useSoftDelete = in_array(SoftDeletes::class, class_uses($model));
        if($useSoftDelete){
            $query = $model->newQuery()->where('deleted_at', NULL);
        }else{
            $query = $model->newQuery();
        }

        if(!$this->isSuperuser()){
            $superuserRoleModel = app(RoleInterface::class)->findByName(Config::superuser(), $this->usedGuard());
            $userModel = app(Config::userModel($this->usedGuard()));
            $query->whereNotExists(function ($query) use ($userModel, $superuserRoleModel){
                $pivotTableName = Config::modelHasRolesTable();
                $query->select(DB::raw(1))
                    ->from($pivotTableName)
                    ->whereRaw($userModel->getTable().'.'.$userModel->getKeyName().' = '.$pivotTableName.'.model_id')
                    ->where($pivotTableName.'.model_type', $userModel->roles()->getMorphClass())
                    ->where($pivotTableName.'.role_id', $superuserRoleModel->id);
            });
        }

        return [$query, $model];
    }

    /**
     * @param Request $request
     * @param $userId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function setRoles(Request $request, $userId)
    {
        $this->checkForPermittedRoles();

        $this->validate($request, [
            'roles' => [
                'array'
            ]
        ]);

        $rolesNames = $request->input('roles');
        $roles = app(RoleInterface::class)->where([
            'guard_name' => $this->usedGuard()
        ])->whereIn('name', $rolesNames)->get();

        if(count($rolesNames) != $roles->count()){
            return response()->json([
                'note' => 'roles don\'t match'
            ], HttpCodes::CONFLICT);
        }

        $user = $this->getUserModel()->where(Config::userIdFieldName(), $userId)->firstOrFail();

        $user->syncRoles($roles);
    }

    public function deleteAdvanced(Request $request)
    {
        $this->checkForPermittedRoles();

        $validatedData = $request->validate([
            'items' => 'required|array'
        ]);

        $loggedUser = $this->getLoggedUser();

        $idFieldName = Config::userIdFieldName();

        $models = [];

        foreach ($validatedData['items'] as $item){
            $model = $this->getUserModel()->where([$idFieldName => $item])->firstOrFail();
            if($model->isSuperuser() && !$this->isSuperuser()){
                return response()->json([], HttpCodes::FORBIDDEN);
            }
            $models[] = $model;
        }

        foreach ($validatedData['items'] as $item){
            if($item == $loggedUser->$idFieldName){
                return response()->json([], HttpCodes::CONFLICT);
            }
        }

        collect($models)->each(function ($model){
            $model->delete();
        });
    }

    public function update(Request $request, $userId)
    {
        $this->checkForPermittedRoles();

        list(
                $idFieldName,
                $nameFieldName,
                $surnameFieldName,
                $stateFieldName,
                $usernameFieldName
            ) = $this->getFieldNames();

        $model = $this->getUserModel()->where([$idFieldName => $userId])->firstOrFail();

        $this->validate($request, [
            'field' => [
                'required',
                Rule::in([
                    $nameFieldName,
                    $surnameFieldName,
                    $stateFieldName,
                    $usernameFieldName,
                ])
            ],
        ]);

        if($model->isSuperuser() && !$this->isSuperuser()){
            return response()->json([], HttpCodes::FORBIDDEN);
        }

        $loggedUser = $this->getLoggedUser();

        $field = $request->input('field');

        if($loggedUser->$idFieldName == $model->$idFieldName){
            switch ($field){
                case $stateFieldName:
                    return response()->json([], HttpCodes::CONFLICT);
            }
        }

        $this->updateValidation($field, $request);

        $data = $request->all();
        unset($data['field']);

        $model->update($data);
    }

    private function updateValidation($field, $request)
    {
        list(
            $idFieldName,
            $nameFieldName,
            $surnameFieldName,
            $stateFieldName,
            $usernameFieldName
            ) = $this->getFieldNames();

        switch ($field){
            case $nameFieldName:
            case $surnameFieldName:
                $this->validate($request, [
                    $field => [
                        'required',
                    ]
                ]);
                break;
            case $stateFieldName:
                $this->validate($request, [
                    $field => [
                        'required',
                        Rule::in(array_merge(Config::userStateEnabled(), Config::userStateDisabled())),

                    ]
                ]);
                break;
            case $usernameFieldName:
                $this->validate($request, [
                    $field => [
                        'required',
                        Config::userUsernameRules(),
                        Rule::unique($this->getUserModel()->getTable())
                    ]
                ]);
                break;
        }
    }

    private function getFieldNames()
    {
        return [
            Config::userIdFieldName(),
            Config::userNameFieldName(),
            Config::userSurnameFieldName(),
            Config::userStateFieldName(),
            Config::userUsernameFieldName(),
        ];
    }

    /*public function all()
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

    protected function validateCreation(Request $request)
    {

    }

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

    public function get($userId)
    {
        $this->checkForPermittedRoles();

        return response()->json($this->getUserModel()->findOrFail($userId)->toArray());
    }

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

    protected function validateUpdate(Request $request, $userId)
    {

    }

    protected function validateUpdateComplete(Request $request, $userId)
    {

    }

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
    }*/
}
