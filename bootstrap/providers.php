<?php

use App\Providers\AppServiceProvider;
use App\Providers\ClockServiceProvider;
use App\Providers\KafkaServiceProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\PlansServiceProvider;
use App\Providers\ProgramsServiceProvider;
use App\Providers\ProgressionServiceProvider;
use App\Providers\RewardsServiceProvider;
use App\Providers\RulesServiceProvider;
use App\Providers\SchedulingServiceProvider;
use App\Providers\SubscriptionsServiceProvider;
use App\SharedFeatures\User\UserServiceProvider;

return [
    AppServiceProvider::class,
    ClockServiceProvider::class,
    KafkaServiceProvider::class,
    ModulesServiceProvider::class,
    PlansServiceProvider::class,
    ProgramsServiceProvider::class,
    ProgressionServiceProvider::class,
    RewardsServiceProvider::class,
    RulesServiceProvider::class,
    SchedulingServiceProvider::class,
    SubscriptionsServiceProvider::class,
    UserServiceProvider::class,
];
