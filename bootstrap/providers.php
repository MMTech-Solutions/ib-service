<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\PlansServiceProvider;
use App\SharedFeatures\User\UserServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    PlansServiceProvider::class,
    UserServiceProvider::class,
];
