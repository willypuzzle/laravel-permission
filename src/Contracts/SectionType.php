<?php

namespace Idsign\Permission\Contracts;

interface SectionType
{
    const ENABLED = 1;
    const DISABLED = 0;
    const ALL_STATES  = [ self::DISABLED, self::ENABLED ];
}
