<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Exceptions\UnsupportedDatabaseType;
use Idsign\Permission\Libraries\Config;
use Idsign\Permission\Models\Role;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Idsign\Vuetify\Facades\Datatable;
use Illuminate\Validation\Rule;
use Willypuzzle\Helpers\Contracts\HttpCodes;
use Idsign\Permission\Contracts\Permission as PermissionInterface;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Idsign\Permission\Contracts\Container as ContainerInterface;

class SweetenRoleController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([Config::superuser(), Config::admin()]);
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
            $query = $query->where('name', '!=', Config::superuser());
        }else if($this->isAdmin()){
            $query = $query->where('name', '!=', Config::superuser())
                           ->where('name', '!=', Config::admin());
        }

        return Datatable::of($query)
                    ->addColumn('containers', function ($role){
                        return $role->containers()->where('operative', false)->get()->toArray();
                    })
                    ->addColumn('operative_containers', function ($role){
                        return $role->containers()->where('operative', true)->get()->toArray();
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

    /**
     * @param Request $request
     * @param $roleId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setContainers(Request $request, $roleId)
    {
        $this->validate($request, [
            'containers' => [
                'array'
            ]
        ]);

        $role = $this->getRole($roleId);

        if(!$this->isSuperuser()){
            if($this->filterModel($role)){
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

        $role->containers()->sync($request->input('containers'));
    }

    /**
     * @param $roleId
     * @param $containerId
     * @return array
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function getContainerData($roleId, $containerId)
    {
        $role = $this->getRole($roleId);

        $container = $role->containers()->where(Config::containersTable().'.id', $containerId)->firstOrFail();

        return [
            'container' => $container->toArray(),
            'tree' => $role->permissionsTree($container, !$this->isSuperuser())
        ];
    }

    /**
     * @param Request $request
     * @param $roleId
     * @param $containerId
     * @param $sectionId
     * @param $permissionId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setPermission(Request $request, $roleId, $containerId, $sectionId, $permissionId)
    {
        $this->validate($request, [
            'value' => [
                'required',
                'boolean'
            ]
        ]);

        $role = $this->getRole($roleId);

        if(!$this->isSuperuser()){
            if($this->filterModel($role)){
                return response()->json([], HttpCodes::FORBIDDEN);
            }
        }

        $permission = app(PermissionInterface::class)->where([
            'id' => $permissionId,
            'guard_name' => $this->usedGuard()
        ])->firstOrFail();

        $container = app(ContainerInterface::class)->where([
            'id' => $containerId,
            'guard_name' => $this->usedGuard()
        ])->firstOrFail();

        $section = app(SectionInterface::class)->where([
            'id' => $sectionId,
            'guard_name' => $this->usedGuard()
        ])->firstOrFail();

        if(!$this->isSuperuser()){
            $relatedContainer = $section->containers()->where(Config::containersTable().'.id', $container->id)->first();
            $superadmin = null;
            if($relatedContainer->pivot->superadmin === null){
                $superadmin = $section->superadmin;
            }else{
                $superadmin = $relatedContainer->pivot->superadmin;
            }
            if($superadmin){
                return response()->json([], HttpCodes::CONFLICT);
            }
        }

        if($request->input('value')){
            $role->givePermissionTo($permission, $section, $container);
        }else{
            $role->revokePermissionTo($permission, $section, $container);
        }
    }

    private function getRole($roleId) : Role
    {
        return $this->getModel()->where('id', $roleId)->firstOrFail();
    }
}
