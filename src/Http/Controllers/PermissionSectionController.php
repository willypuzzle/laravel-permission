<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Contracts\Permission as PermissionInterface;
use Idsign\Permission\Contracts\Section as SectionInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Idsign\Vuetify\Facades\Datatable;
use Idsign\Permission\Http\Controllers\Controller;

abstract class PermissionSectionController extends Controller
{
    const SECTION = 'section';
    const PERMISSION = 'permission';

    public function all(){
        return response()->json(app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class)->newQuery()->get()->toArray());
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'required'
            ],
            'state' => [
                'required',
                Rule::in($this->delta() == self::PERMISSION ? PermissionInterface::ALL_STATES : SectionInterface::ALL_STATES)
            ]
        ]);

        $data = $request->all();

        $data['guard_name'] = $this->guard();

        $model = app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class);

        $model->fill($data);

        $model->save();
    }

    public function data()
    {
        return Datatable::of(app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class)->query())->make(true);
    }

    public function delete($modelId)
    {
        $model = app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class)->where('id', $modelId)->firstOrFail();

        $model->delete();
    }

    public function get($modelId)
    {
        return response()->json(app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class)->where('id', $modelId)->firstOrFail()->toArray());
    }

    public function multi_delete(Request $request)
    {
        $validatedData = $request->validate([
            'items' => 'required|json'
        ]);

        foreach (json_decode($validatedData['items'], true) as $item){
            $model = app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class)->where('id', '=', $item['id'])->firstOrFail();
            $model->delete();
        }
    }

    public function update(Request $request, $modelId)
    {
        $model = app($this->delta() == self::PERMISSION ? PermissionInterface::class : SectionInterface::class)->where('id', $modelId)->firstOrFail();

        $this->validate($request, [
            'field' => [
                'required',
                Rule::in(['name','state'])
            ]
        ]);

        $field = $request->input('field');

        switch ($field){
            case 'name':
                $this->validate($request, [
                    $field => [
                        'required'
                    ]
                ]);
                break;
            case 'state':
                $this->validate($request, [
                    $field => [
                        'required',
                        Rule::in(PermissionInterface::ALL_STATES)
                    ]
                ]);
                break;
        }

        $data = $request->all();
        unset($data['field']);
        $model->update($data);
    }

    abstract protected function delta() : string;

    abstract protected function guard() : string;
}
