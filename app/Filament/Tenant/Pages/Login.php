<?php

namespace App\Filament\Tenant\Pages;   
use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    protected string $view = 'filament.tenant.login';
}