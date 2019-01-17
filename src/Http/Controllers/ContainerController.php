<?php

namespace Idsign\Permission\Http\Controllers;

use Illuminate\Http\Request;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Idsign\Permission\Contracts\Container as ContainerInterface;
use Willypuzzle\Helpers\Contracts\HttpCodes;

class ContainerController extends PermissionRoleSectionContainerController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::CONTAINER;
    }

    public function labels()
    {
        return config('permission.container.labels');
    }

    /**
     * @param Request $request
     * @param $containerId
     * @return mixed
     * @throws \Exception
     */
    public function getSectionsTree(Request $request, $containerId)
    {
        $container = $this->getContainer($containerId);

        return app(SectionInterface::class)->containerTree($container, $request->input('type') === 'complete' ? false : true);
    }

    /**
     * @param Request $request
     * @param $containerId
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits|\Exception
     */
    public function setEnabledSections(Request $request, $containerId)
    {
        $this->checkForPermittedRoles();

        $container = $this->getContainer($containerId);

        $this->validate($request, [
            'sections' => [
                'array'
            ]
        ]);

        $sectionIds = $request->input('sections');

        if(app(SectionInterface::class)->whereIn('id', $sectionIds)->where('guard_name', $this->usedGuard())->count() !== count($sectionIds)){
            return response()->json([
                'note' => 'sections out of guard'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        $this->syncSectionsToContainer($container, $sectionIds);

        return [
            'tree' => app(SectionInterface::class)->containerTree($container, false)
        ];
    }

    private function syncSectionsToContainer(ContainerInterface $container, array $sections)
    {
        $container->sections()->sync($sections);
    }

    /**
     * @param Request $request
     * @param $containerId
     * @param $sectionId
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits|\Exception
     */
    public function setSectionSuperadmin(Request $request, $containerId, $sectionId)
    {
        $this->checkForPermittedRoles();

        $container = $this->getContainer($containerId);

        $section = $container->sections()->where(config('permission.table_names.sections').'.id', $sectionId)->firstOrFail();

        $value = $request->input('value');

        $value = ($value === 'enabled' ? 1 : ($value === 'disabled' ? 0 : null));

        $container->sections()->updateExistingPivot($section->id, ['superadmin' => $value]);
    }

    /**
     * @param $containerId
     * @return mixed
     * @throws \Exception
     */
    private function getContainer($containerId) : ContainerInterface
    {
        $model = $this->getModel()->where('id', $containerId)->firstOrFail();

        if($model->guard_name != $this->usedGuard()){
            throw new \Exception('wrong guard in container');
        }

        return $model;
    }
}
