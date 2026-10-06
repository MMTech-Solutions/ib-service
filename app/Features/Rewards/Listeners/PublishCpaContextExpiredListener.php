<?php

declare(strict_types=1);

namespace App\Features\Rewards\Listeners;

use App\Features\Rewards\Contracts\Events\V1\CpaContextExpired;
use App\Features\Rewards\Services\Pushers\Service\ServiceEventPusher;
use Illuminate\Contracts\Queue\ShouldQueue;

final class PublishCpaContextExpiredListener implements ShouldQueue
{
    public function __construct(private readonly ServiceEventPusher $pusher) {}

    public function handle(CpaContextExpired $event): void
    {
        $this->pusher->pushCpaContextExpired($event);
    }
}
