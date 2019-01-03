<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Idsign\Permission\Contracts\Section as SectionContract;
use Willypuzzle\Helpers\Contracts\HttpCodes;

class SectionController extends PermissionRoleSectionController
{
    public function __construct()
    {
        $this->addPermittedRoles([config('permission.roles.superuser')]);
    }

    protected function delta() : string
    {
        return self::SECTION;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function all(Request $request)
    {
        $this->checkForPermittedRoles();

        $where = ['guard_name' => $this->usedGuard()];

        $parent = $request->input('section_id');

        if($parent){
            $where['section_id'] = $parent;
        }

        return response()->json($this->getModel()->where($where)->get()->toArray());
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Idsign\Permission\Exceptions\DoesNotUseProperTraits
     */
    public function getTree(Request $request)
    {
        $this->validate($request, [
            'type' => [
                Rule::in('complete')
            ]
        ]);

        $type = $request->input('type');

        switch ($type){
            case 'complete':
                $this->checkForPermittedRoles();
                return app(SectionContract::class)->globalTree($this->usedGuard(), false);
                break;
        }
    }

    public function move(Request $request)
    {
        $this->checkForPermittedRoles();

        $parent = $request->input('parent');

        $this->validate($request, [
            'section' => [
                'exists:'.config('permission.table_names.sections').',id',
            ],
            'parent' => [
                $parent ? 'exists:'.config('permission.table_names.sections').',id' : Rule::in([null]),
            ],
            'position' => [
                'integer'
            ],
            'siblings' => [
                'array'
            ]
        ]);

        $siblingsIds = $request->input('siblings');

        $siblings = app(SectionContract::class)->whereIn('id', $siblingsIds)->get();

        if($siblings->count() != count($siblingsIds)){
            return response()->json([
                'note' => 'some sibling doesn\'t exist'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        if($siblings->filter(function ($sibling){
            // TODO check if sibling has the same parent with section
        }));
    }

}
