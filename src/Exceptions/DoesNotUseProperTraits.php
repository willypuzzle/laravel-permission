<?php

namespace Idsign\Permission\Exceptions;

class DoesNotUseProperTraits extends \Exception
{
    public static function create($model) : DoesNotUseProperTraits
    {
        return new static("'".(get_class($model))."' does not use proper trait.");
    }
}
