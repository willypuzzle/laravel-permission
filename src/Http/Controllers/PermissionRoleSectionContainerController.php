<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Permission as PermissionInterface;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Idsign\Permission\Contracts\Role as RoleInterface;
use Idsign\Permission\Contracts\Container as ContainerInterface;
use Idsign\Permission\Exceptions\MalformedParameter;
use Idsign\Permission\Exceptions\UnsupportedDatabaseType;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Idsign\Vuetify\Facades\Datatable;
use Illuminate\Support\Facades\DB;
use Willypuzzle\Helpers\Contracts\HttpCodes;

abstract class PermissionRoleSectionContainerController extends RoleCheckerController
{
    const SECTION = 'section';
    const PERMISSION = 'permission';
    const ROLE = 'role';
    const CONTAINER = 'container';

    protected $databaseDriver = null;

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
            case self::CONTAINER:
                return ContainerInterface::class;
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
            case self::CONTAINER:
                return config('permission.table_names.containers');
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
            case self::CONTAINER:
                return ContainerInterface::ALL_STATES;
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
    public function all(Request $request){
        $this->checkForPermittedRoles();

        $collection = $this->getModel()->where(['guard_name' => $this->usedGuard()])->get();

        if($this->delta() == self::ROLE && !$this->isSuperuser()){
            $collection = $collection->filter(function ($el){
                return $el->name != config('permission.roles.superuser');
            });
        }

        $locale = $request->input('locale');

        $array = $collection->all();

        usort($array, function ($el1, $el2) use ($locale){
            return self::sorter($el1, $el2, $locale);
        });

        return response()->json($array);
    }

    public static function sorter($el1, $el2, $locale = null)
    {
        if($locale){
            $el1Key = isset($el1->label[$locale]) ? $el1->label[$locale] : $el1->name;
            $el2Key = isset($el2->label[$locale]) ? $el2->label[$locale] : $el2->name;


        }else{
            $el1Key = $el1->name;
            $el2Key = $el2->name;
        }

        return strcmp($el1Key, $el2Key);
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
            $validationArray['section_id'] = [
                'exists:'.$this->getTableName().',id'
            ];
        }

        $this->validate($request, $validationArray);

        $data = $request->all();

        $data['guard_name'] = $this->usedGuard();
        $label = [];
        $label[$data['locale']] = $data['label'];
        unset($data['locale']);

        $data['label'] = $label;


        $model = $this->getModel();

        $model->fill($data);

        $model->save();

        return response()->json([], HttpCodes::CREATED);
    }

    /**
     * @param Request $request
     * @param null $type
     * @return mixed
     * @throws AuthenticationException
     * @throws UnsupportedDatabaseType
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function data($type = null)
    {
        $this->checkForPermittedRoles();

        $query = $this->getModel()->query()->where(['guard_name' => $this->usedGuard()]);

        return Datatable::of($query)->make(true);
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

        if($this->delta() == self::ROLE && !$this->isSuperuser()){
            if($model->name == config('permission.roles.superuser')){
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

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
            $model = $this->getModel()->find($item['id']);

            if($this->delta() == self::ROLE && !$this->isSuperuser()){
                if($model->name == config('permission.roles.superuser')){
                    return response()->json([], HttpCodes::FORBIDDEN);
                }
            }
        }

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

        $validationArrayRuleIn = ['state', 'label'];

        if($this->isSuperuser()){
            $validationArrayRuleIn[] = 'name';
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
        }

        $data = $request->all();

        if($this->delta() == self::ROLE && $data['field'] == 'state'){
            if($model->name == config('permission.roles.superuser')){
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

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
