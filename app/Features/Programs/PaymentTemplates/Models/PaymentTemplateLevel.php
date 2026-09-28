<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Models;

final class PaymentTemplateLevel
{
    public function __construct(public string $id, public int $distributionLevel, public string $rate) {}
}
