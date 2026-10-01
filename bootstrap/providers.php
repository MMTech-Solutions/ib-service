<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\PlansServiceProvider;
use App\Providers\ProgramsServiceProvider;
use App\Providers\ProgressionServiceProvider;
use App\Providers\RewardsServiceProvider;
use App\Providers\RulesServiceProvider;
use App\Providers\SubscriptionsServiceProvider;
use App\SharedFeatures\User\UserServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    PlansServiceProvider::class,
    ProgramsServiceProvider::class,
    ProgressionServiceProvider::class,
    RulesServiceProvider::class,
    RewardsServiceProvider::class,
    SubscriptionsServiceProvider::class,
    UserServiceProvider::class,
];
