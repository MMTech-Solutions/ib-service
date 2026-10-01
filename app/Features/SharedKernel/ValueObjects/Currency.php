<?php

declare(strict_types=1);

namespace App\Features\SharedKernel\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Validator;
use JsonSerializable;

/**
 * @implements Arrayable<string, string|int>
 */
final class Currency implements Arrayable, JsonSerializable
{
    public function __construct(
        private readonly string $code,
        private readonly int $precision,
    ) {
        Validator::validate([
            'code' => $this->code,
            'precision' => $this->precision,
        ], [
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'precision' => ['required', 'integer', 'min:0'],
        ]);
    }

    public static function from(string $code, int $precision): self
    {
        return new self(strtoupper(trim($code)), $precision);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function precision(): int
    {
        return $this->precision;
    }

    /** @return array{code: string, precision: int} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'precision' => $this->precision,
        ];
    }

    /** @return array{code: string, precision: int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
