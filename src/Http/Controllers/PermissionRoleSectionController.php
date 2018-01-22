<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Permission as PermissionInterface;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Idsign\Permission\Contracts\Role as RoleInterface;
use Idsign\Permission\Exceptions\MalformedParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Idsign\Vuetify\Facades\Datatable;

abstract class PermissionRoleSectionController extends RoleCheckerController
{
    const SECTION = 'section';
    const PERMISSION = 'permission';
    const ROLE = 'role';

    private function getInterfaceName()
    {
        $delta = $this->delta();

        switch ($delta){
            case self::SECTION:
                return SectionInterface::class;
            case self::PERMISSION:
                return PermissionInterface::class;
            case self::ROLE:
                return RoleInterface::class;
            default:
                throw MalformedParameter::create($delta);
        }
    }

    private function getTableName()
    {
        $delta = $this->delta();

        switch ($delta){
            case self::SECTION:
                return config('permission.table_names.sections');
            case self::PERMISSION:
                return config('permission.table_names.permissions');
            case self::ROLE:
                return config('permission.table_names.roles');
            default:
                throw MalformedParameter::create($delta);
        }
    }

    private function getInterfaceAllStates()
    {
        $delta = $this->delta();

        switch ($delta){
            case self::SECTION:
                return SectionInterface::ALL_STATES;
            case self::PERMISSION:
                return PermissionInterface::ALL_STATES;
            case self::ROLE:
                return RoleInterface::ALL_STATES;
            default:
                throw MalformedParameter::create($delta);
        }
    }

    private function getModel() : Model
    {
        return app($this->getInterfaceName());
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function all(){
        $this->checkForPermittedRoles();
        return response()->json($this->getModel()->where(['guard_name' => $this->usedGuard()])->toArray());
    }

    /**
     * @param Request $request
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function create(Request $request)
    {
        $this->checkForPermittedRoles();

        $this->validate($request, [
            'label' => [
                'required'
            ],
            'locale' => [
                'required'
            ],
            'name' => [
                'required',
                Rule::unique($this->getTableName())
            ],
            'state' => [
                'required',
                Rule::in($this->getInterfaceAllStates())
            ]
        ]);

        $data = $request->all();

        $data['guard_name'] = $this->usedGuard();
        $label = [];
        $label[] = $data['label'];
        unset($data['locale']);

        $data['label'] = $label;


        $model = $this->getModel();

        $model->fill($data);

        $model->save();
    }

    /**
     * @return mixed
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function data()
    {
        $this->checkForPermittedRoles();
        return Datatable::of($this->getModel()->query()->where(['guard_name' => $this->usedGuard()]))->make(true);
    }

    /**
     * @param $modelId
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function delete($modelId)
    {
        $this->checkForPermittedRoles();
        $model = $this->getModel()->where(['id' => $modelId, 'guard_name' => $this->usedGuard()])->firstOrFail();

        $model->delete();
    }

    /**
     * @param $modelId
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function get($modelId)
    {
        $this->checkForPermittedRoles();
        return response()->json($this->getModel()->where(['id' => $modelId, 'guard_name' => $this->usedGuard()])->firstOrFail()->toArray());
    }

    /**
     * @param Request $request
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function multi_delete(Request $request)
    {
        $this->checkForPermittedRoles();

        $validatedData = $request->validate([
            'items' => 'required|json'
        ]);

        foreach (json_decode($validatedData['items'], true) as $item){
            $model = $this->getModel()->where(['id' => $item['id'], 'guard_name' => $this->usedGuard()])->firstOrFail();
            $model->delete();
        }
    }

    /**
     * @param Request $request
     * @param $modelId
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function update(Request $request, $modelId)
    {
        $this->checkForPermittedRoles();

        $model = $this->getModel()->where(['id' => $modelId, 'guard_name' => $this->usedGuard()])->firstOrFail();

        $this->validate($request, [
            'field' => [
                'required',
                Rule::in(['name','state', 'label'])
            ]
        ]);

        $field = $request->input('field');

        switch ($field){
            case 'name':
                $this->validate($request, [
                    $field => [
                        'required',
                        Rule::unique($this->getTableName())->ignore($modelId)
                    ]
                ]);
                break;
            case 'state':
                $this->validate($request, [
                    $field => [
                        'required',
                        Rule::in($this->getInterfaceAllStates())
                    ]
                ]);
                break;
            case 'label':
                $this->validate($request, [
                    $field => [
                        'required'
                    ],
                    'locale' => [
                        'required'
                    ]
                ]);
                break;
        }

        $data = $request->all();
        unset($data['field']);

        if($field != 'label'){
            $model->update($data);
        }else{
            $label = $model->label;
            $label[$data['locale']] = $data['label'];
            $model->label = $label;
            $model->save();
        }

    }

    abstract protected function delta() : string;
}
