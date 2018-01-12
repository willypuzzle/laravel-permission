<?php

namespace Idsign\Permission\Exceptions;

use InvalidArgumentException;
use Illuminate\Support\Collection;

class MalformedParameter extends InvalidArgumentException
{
    public static function create(string $string) : MalformedParameter
    {
        return new static("String `{$string}` is malformed.");
    }
}
