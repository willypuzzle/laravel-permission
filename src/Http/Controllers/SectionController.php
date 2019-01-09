<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Exceptions\SectionDoesNotExist;
use Idsign\Permission\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'position' => [
                'integer'
            ],
            'siblings' => [
                'array'
            ],
            'pre-siblings' => [
                'array'
            ]
        ]);

        $section = app(SectionContract::class)->find($request->input('section'));

        $siblingsIds = $request->input('siblings');

        $siblings = app(SectionContract::class)->whereIn('id', $siblingsIds)->get();

        $preSiblingsIds = $request->input('pre-siblings');

        $preSiblings = app(SectionContract::class)->whereIn('id', $preSiblingsIds)->get();

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

        if($preSiblings->count() != count($preSiblingsIds)){
            return response()->json([
                'note' => 'some pre-sibling doesn\'t exist'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        if($preSiblings->filter(function ($sibling) use ($parent){
                return $sibling->section_id == ($parent ? $parent->id : null);
            })->count() != $preSiblings->count()){
            return response()->json([
                'note' => 'some pre-sibling doesn\'t share same parent'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($parent, $position,$section, $siblings, $preSiblings){
            $section->section_id = $parent ? $parent->id : null;
            $section->order = $position;
            $section->save();

            $index = $position - 1;
            $preSiblings = $preSiblings->reverse();
            foreach ($preSiblings as $sibling){
                $sibling->order = $index;
                $sibling->save();
                $index--;
            }

            $index = $position + 1;
            foreach ($siblings as $sibling){
                $sibling->order = $index;
                $sibling->save();
                $index++;
            }
        });
    }

    public function add(Request $request)
    {
        $this->validate($request, [
            'state' => [
                Rule::in(SectionContract::ALL_STATES)
            ],
            'code' => [
                'required'
            ],
            'name' => [
                'required'
            ],
            'locale' => [
                'required',
                'min:2',
                'max:5'
            ],
            'superadmin' => [
                'required',
                'boolean'
            ]
        ]);

        $parentId = $request->input('parent');
        if($parentId){
            if( !($parent = app(SectionContract::class)->find($parentId)) ){
                return response()->json([
                    'note' => 'parent not found'
                ], HttpCodes::UNPROCESSABLE_ENTITY);
            }
        }else{
            $parent = null;
        }

        $name = $request->input('name');
        $code = str_slug($request->input('code'));
        $state = $request->input('state');
        $locale = $request->input('locale');
        $superadmin = $request->input('superadmin');

        try{
            $section = app(SectionContract::class)->findByName($code, $this->usedGuard());
        }catch (SectionDoesNotExist $ex){
            $section = null;
        }

        if($section){
            return response()->json([
                'note' => 'section already exists',
                'code_exists' => true
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        $siblings = app(SectionContract::class)->where('section_id', $parent ? $parent->id : null)->get();

        $section = $this->getModel();
        $section->label = [
            $locale => $name
        ];
        $section->name = $code;
        $section->state = $state;
        $section->superadmin = $superadmin;
        $section->section_id = $parent ? $parent->id : null;
        $section->order = 0;

        DB::transaction(function () use (&$section, $siblings){
            $section->save();

            $siblings->each(function ($sibling){
                $sibling->order = $sibling->order + 1;
                $sibling->save();
            });
        });

        return response()->json([
            'section' => $section->toArray(),
            'parent' => $parent ? $parent->toArray() : null
        ], HttpCodes::CREATED);
    }

    /**
     * @param Request $request
     * @throws \Exception
     */
    public function change(Request $request)
    {
        $this->validate($request, [
            'field' => [
                'required',
                Rule::in(['code', 'name', 'state', 'superadmin'])
            ],
            'section' => [
                'required'
            ],
            'value' => [
                'required'
            ],
            'locale' => [
                'required',
                'min:2',
                'max:5'
            ]
        ]);

        return $this->changeChoose($request->input('field'), $request->input('section'), $request->input('value'), $request->input('locale'));
    }

    /**
     * @param $field
     * @param $sectionId
     * @param $value
     * @throws \Exception
     */
    private function changeChoose($field, $sectionId, $value, $locale)
    {
        $section = app(SectionContract::class)->findOrFail($sectionId);

        if($section->guard_name != $this->usedGuard()){
            throw new \Exception('guard must coincide');
        }

        switch ($field){
            case 'code':
                return $this->changeCode($section, $value);
            case 'name':
                return $this->changeName($section, $value, $locale);
            case 'state':
                return $this->changeState($section, $value);
            case 'superadmin':
                return $this->changeSuperadmin($section, $value);
            default:
                throw new \Exception("'{$field}' is a unknown field");
        }
    }

    private function changeCode(SectionContract $section, $value)
    {
        try{
            $check = app(SectionContract::class)->findByName($value, $this->usedGuard());
        }catch (SectionDoesNotExist $ex){
            $check = null;
        }

        if($check){
            return response()->json(['code_exists' => true], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        $section->name = $value;
        $section->save();
    }

    private function changeName(SectionContract $section, $value, $locale)
    {
        $label = $section->label ?? [];
        $label[$locale] = $value;
        $section->label = $label;

        $section->save();
    }

    private function changeState(SectionContract $section, $value)
    {
        if(!in_array($value, SectionContract::ALL_STATES)){
            return response()->json([
                'note' => 'incorrect state value'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        $section->state = $value;
        $section->save();
    }

    private function changeSuperadmin(SectionContract $section, $value)
    {
        if(!is_bool($value)){
            return response()->json([
                'note' => 'superadmin is not boolean'
            ], HttpCodes::UNPROCESSABLE_ENTITY);
        }

        $section->superadmin = $value;
        $section->save();
    }
}
