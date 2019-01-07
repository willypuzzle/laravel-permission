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

        $this->validate($request, [
            /*'section' => [
                'exists:'.config('permission.table_names.sections').',id',
            ],*/
            /*'parent' => [
                $parent ? 'exists:'.config('permission.table_names.sections').',id' : Rule::in([null]),
            ],*/
            'position' => [
                'integer'
            ],
            'siblings' => [
                'array'
            ]
        ]);

        $section = app(SectionContract::class)->find($request->input('section'));

        $siblingsIds = $request->input('siblings');

        $siblings = app(SectionContract::class)->whereIn('id', $siblingsIds)->get();

        $position = $request->input('position');

        $parentParameter = $request->input('parent');

        if($parentParameter){
            $parent = app(SectionContract::class)->find($parentParameter);
        }else{
            $parent = null;
        }

        if(!$section){
            return response()->json([
                'note' => 'section doesn\'t exist'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        if($parentParameter && !$parent){
            return response()->json([
                'note' => 'parent doesn\'t exist'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        if($siblings->count() != count($siblingsIds)){
            return response()->json([
                'note' => 'some sibling doesn\'t exist'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        if($siblings->filter(function ($sibling) use ($parent){
            return $sibling->section_id == ($parent ? $parent->id : null);
        })->count() != $siblings->count()){
            return response()->json([
                'note' => 'some sibling doesn\'t share same parent'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }


    }

}
