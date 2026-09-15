<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\PlansServiceProvider;
use App\Providers\ProgramsServiceProvider;
use App\Providers\RulesServiceProvider;
use App\SharedFeatures\User\UserServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    PlansServiceProvider::class,
    ProgramsServiceProvider::class,
    RulesServiceProvider::class,
    UserServiceProvider::class,
];
