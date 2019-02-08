<?php
namespace Idsign\Permission\Libraries;

class ModelSupport
{
    public static function elaborateSuperadmin($element, $container)
    {
        $relativeSuperadmin = array_filter($element['model']['containers'] ?? [], function ($el) use ($container){
            return $el['id'] == $container->id;
        });

        $relativeSuperadmin = isset($relativeSuperadmin[0]['pivot']['superadmin']) ? $relativeSuperadmin[0]['pivot']['superadmin'] : null;

        if($relativeSuperadmin === null){
            return $element['model']['superadmin'];
        }

        return $relativeSuperadmin;
    }
}
