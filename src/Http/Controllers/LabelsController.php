<?php

namespace Idsign\Permission\Http\Controllers;

use Idsign\Permission\Libraries\Config;

class LabelsController extends Controller
{
    public function all()
    {
        return Config::labels();
    }
}
