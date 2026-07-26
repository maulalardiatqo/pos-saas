<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;


class Permission extends Model
{

    use HasUlids;


    protected $guarded = ['id'];



    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions'
        )
        ->using(RolePermission::class)
        ->withTimestamps();
    }

}