<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use RuntimeException;

final class RewardSettlementException extends RuntimeException
{
    public function __construct(public readonly string $error_code)
    {
        parent::__construct($error_code);
    }
}
