<?php

namespace Idsign\Permission\Http\Controllers;

use Illuminate\Http\Request;
use Idsign\Permission\Contracts\Section as SectionInterface;

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

    public function getSectionsTree(Request $request, $containerId)
    {
        $container = $this->getModel()->where('id', $containerId)->firstOrFail();

        return app(SectionInterface::class)->containerTree($container, $request->input('type') === 'complete' ? false : true);
    }
}
