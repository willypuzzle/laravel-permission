<?php

namespace Idsign\Permission\Models;

use Illuminate\Database\Eloquent\Model;

class SectionType extends Model
{
    protected $casts = [
        'label' => 'array'
    ];

    protected $fillable = [
        'guard_name',
        'label',
        'name',
        'state'
    ];
    public function scopeEnabled($query, $state = \Idsign\Permission\Contracts\SectionType::ENABLED)
    {
        return $query->where('state', $state);
    }

    public function sections()
    {
        return $this->hasMany(config('permission.models.section'));
    }
}
