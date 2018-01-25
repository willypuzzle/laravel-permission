<?php

namespace Idsign\Permission\Exceptions;

class UnsupportedDatabaseType extends \Exception
{
    public static function create(string $databaseNAme)
    {
        return new static("Database `{$databaseNAme}` is not supported.");
    }
}
