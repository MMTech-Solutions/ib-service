<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Controllers;

use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Http\V1\Commands\ListRuleAssignmentsCommand;
use App\Features\Rules\Assignments\Http\V1\Requests\ListRuleAssignmentsRequest;
use App\Features\Rules\Assignments\UseCases\ListRuleAssignmentsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListRuleAssignmentsController
{
    use ApiResponse;

    public function __invoke(ListRuleAssignmentsRequest $request, ListRuleAssignmentsUseCase $useCase): JsonResponse
    {
        $command = ListRuleAssignmentsCommand::fromRequest($request);
        $page = $useCase->execute($command);
        $assignments = array_map(
            static fn (RuleAssignmentData $assignment): array => $assignment->toArray(),
            $page->assignments,
        );
        $paginator = new LengthAwarePaginator(
            items: $assignments,
            total: $page->total,
            perPage: $page->perPage,
            currentPage: $page->currentPage,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );
        $filters = array_filter(
            [
                'program_id' => $command->programId,
                'module_id' => $command->moduleId,
                'active' => $command->active,
            ],
            static fn (mixed $value): bool => $value !== null,
        );

        return $this->success(
            data: $assignments,
            message: 'Rule assignments retrieved successfully.',
            meta: ['filters' => (object) $filters],
            paginator: $paginator,
        );
    }
}
