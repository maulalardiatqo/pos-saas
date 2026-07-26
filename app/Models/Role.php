<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Permission;


class Role extends Model
{

    use HasFactory, HasUlids, SoftDeletes;


    protected $guarded = ['id'];



    public function company()
    {
        return $this->belongsTo(Company::class);
    }



    public function users()
    {
        return $this->hasMany(User::class);
    }



    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions'
        )
        ->using(RolePermission::class) 
        ->withTimestamps();           
    }



    public function hasPermission(string $permission): bool
    {
        return $this->permissions()
            ->where('code',$permission)
            ->exists();
    }

}