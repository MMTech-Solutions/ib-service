<?php

declare(strict_types=1);

namespace App\Features\Rewards\Http\V1\Commands;

use App\Features\Rewards\DTOs\CpaProgressReadAccessData;
use App\Features\Rewards\DTOs\CpaVerificationProgressListQueryData;
use App\Features\Rewards\Http\V1\Requests\ListCpaVerificationProgressRequest;
use Spatie\LaravelData\Data;

final class ListCpaVerificationProgressCommand extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $per_page,
        public readonly ?string $ib_user_id,
        public readonly ?string $referred_user_id,
        public readonly ?string $program_id,
        public readonly ?string $module_id,
        public readonly ?string $status,
    ) {}

    public static function fromRequest(ListCpaVerificationProgressRequest $request, CpaProgressReadAccessData $access): self
    {
        $validated = $request->validated();

        return new self(
            page: (int) ($validated['page'] ?? 1),
            per_page: (int) ($validated['per_page'] ?? 100),
            ib_user_id: $access->forced_ib_user_id ?? ($validated['ib_user_id'] ?? null),
            referred_user_id: $access->is_administrative ? ($validated['referred_user_id'] ?? null) : null,
            program_id: $access->is_administrative ? ($validated['program_id'] ?? null) : null,
            module_id: $access->is_administrative ? ($validated['module_id'] ?? null) : null,
            status: $validated['status'] ?? null,
        );
    }

    public function toQueryData(): CpaVerificationProgressListQueryData
    {
        return new CpaVerificationProgressListQueryData(
            $this->page,
            $this->per_page,
            $this->ib_user_id,
            $this->referred_user_id,
            $this->program_id,
            $this->module_id,
            $this->status,
        );
    }
}
