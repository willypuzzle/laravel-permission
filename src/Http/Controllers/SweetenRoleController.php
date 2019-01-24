<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Exceptions\UnsupportedDatabaseType;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Idsign\Vuetify\Facades\Datatable;
use Illuminate\Validation\Rule;
use Willypuzzle\Helpers\Contracts\HttpCodes;
use Idsign\Permission\Contracts\Role as RoleInterface;

class SweetenRoleController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser'), config('permission.roles.admin')]);
    }

    protected function delta() : string
    {
        return self::ROLE;
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

        if($this->isSuperuser()){
            $query = $query->where('name', '!=', config('permission.roles.superuser'));
        }else if($this->isAdmin()){
            $query = $query->where('name', '!=', config('permission.roles.superuser'))
                           ->where('name', '!=', config('permission.roles.admin'));
        }

        return Datatable::of($query)->addColumn('containers', function ($role){
            return $role->containers->toArray();
        })->make(true);
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
            'state' => [
                'required',
                Rule::in($this->getInterfaceAllStates())
            ]
        ];

        $this->validate($request, $validationArray);

        $data = $request->all();

        $data['guard_name'] = $this->usedGuard();
        $label = [];
        $label[$data['locale']] = $data['label'];
        unset($data['locale']);

        $data['label'] = $label;

        $data['name'] = str_slug(str_random());

        $model = $this->getModel();

        $model->fill($data);

        $model->save();

        return response()->json([], HttpCodes::CREATED);
    }

    public function setContainers(Request $request, $roleId)
    {
        $this->validate($request, [
            'containers' => [
                'array'
            ]
        ]);

        $role = app(RoleInterface::class)->where('id', $roleId)->firstOrFail();

        if(!$this->isSuperuser()){
            if($this->filterModel($role)){
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

        $role->containers()->sync($request->input('containers'));
    }

    public function getContainerData($roleId, $containerId)
    {
        $role = app(RoleInterface::class)->where('id', $roleId)->firstOrFail();

        $container = $role->containers()->where(config('permission.table_names.containers').'.id', $containerId)->firstOrFail();

        return [
            'container' => $container->toArray(),
            'tree' => $role->permissionsTree($container)
        ];
    }
}
