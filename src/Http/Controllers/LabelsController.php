<?php

namespace Idsign\Permission\Http\Controllers;

class LabelsController extends Controller
{
    public function all()
    {
        return config('permission.labels');
    }
}
