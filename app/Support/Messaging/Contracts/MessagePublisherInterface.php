<?php

declare(strict_types=1);

namespace App\Support\Messaging\Contracts;

interface MessagePublisherInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function publish(string $topic, array $payload, ?string $key = null, array $headers = []): void;
}
