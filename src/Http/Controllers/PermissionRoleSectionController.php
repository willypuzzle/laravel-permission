<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Permission as PermissionInterface;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Idsign\Permission\Contracts\Role as RoleInterface;
use Idsign\Permission\Contracts\SectionType as SectionTypeInterface;
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
    const SECTION_TYPE = 'section_type';

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
            case self::SECTION_TYPE:
                return SectionTypeInterface::class;
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
            case self::SECTION_TYPE:
                return config('permission.table_names.section_types');
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
            case self::SECTION_TYPE:
                return SectionTypeInterface::ALL_STATES;
            default:
                throw MalformedParameter::create($delta);
        }
    }

    protected function getModel() : Model
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
        return response()->json($this->getModel()->where(['guard_name' => $this->usedGuard()])->get()->toArray());
    }

    /**
     * @param Request $request
     * @throws AuthenticationException
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function create(Request $request)
    {
        $this->checkForPermittedRoles();

        $validationArray = [
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
        ];

        if($this->delta() == self::SECTION){
            $validationArray['section_type_id'] = [
                'exists:'.$this->getTableName().',id'
            ];
        }

        $this->validate($request, $validationArray);

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

        $validationArrayRuleIn = ['name','state', 'label'];

        if($this->delta() == self::SECTION){
            $validationArray[] = 'section_type_id';
        }

        $this->validate($request, [
            'field' => [
                'required',
                Rule::in($validationArrayRuleIn)
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
            case 'section_type_id':
                $this->validate($request, [
                    $field => [
                        'required',
                        'exists:'.$this->getTableName().',id'
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
