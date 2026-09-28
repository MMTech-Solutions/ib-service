<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Commands;

use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use Spatie\LaravelData\Data;

final class ManagePaymentTemplateCommand extends Data
{
    /**
     * @param  list<PaymentTemplateLevelCommandData>|null  $levels
     */
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?int $lockVersion,
        public readonly ?array $levels,
    ) {}

    public static function fromRequest(ManagePaymentTemplateRequest $request): self
    {
        $data = $request->validated();

        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            description: $data['description'] ?? null,
            lockVersion: isset($data['lock_version']) ? (int) $data['lock_version'] : null,
            levels: isset($data['levels'])
                ? array_map(
                    static fn (array $level): PaymentTemplateLevelCommandData => new PaymentTemplateLevelCommandData(
                        distributionLevel: (int) $level['distribution_level'],
                        rate: (string) $level['rate'],
                    ),
                    $data['levels'],
                )
                : null,
        );
    }
}
