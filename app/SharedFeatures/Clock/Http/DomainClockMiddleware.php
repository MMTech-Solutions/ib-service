<?php

declare(strict_types=1);

namespace App\SharedFeatures\Clock\Http;

use App\SharedFeatures\Clock\DomainClock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DomainClockMiddleware
{
    public function __construct(private readonly DomainClock $clock) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->clock->end();
        try {
            $this->clock->begin();

            return $next($request);
        } finally {
            $this->clock->end();
        }
    }
}
