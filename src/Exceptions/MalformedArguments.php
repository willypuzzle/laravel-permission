<?php

namespace Idsign\Permission\Exceptions;

use InvalidArgumentException;

class MalformedArguments extends InvalidArgumentException
{
    public static function create(array $data)
    {
        $data = implode(',', $data);
        return new static("Parameters `{$data}` (array) are malformed.");
    }
}
