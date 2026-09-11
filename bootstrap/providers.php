<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;
use App\SharedFeatures\User\UserServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    UserServiceProvider::class,
];
