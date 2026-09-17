<?php

declare(strict_types=1);

use App\Features\Modules\Catalog\Http\V1\Controllers\ActivateModuleController;
use App\Features\Modules\Catalog\Http\V1\Controllers\DeactivateModuleController;
use App\Features\Modules\Catalog\Http\V1\Controllers\ListModuleOperationalHistoryController;
use App\Features\Modules\Catalog\Http\V1\Controllers\ListModulesController;
use App\Features\Modules\Catalog\Http\V1\Controllers\PauseModuleProcessingController;
use App\Features\Modules\Catalog\Http\V1\Controllers\ResumeModuleProcessingController;
use App\Features\Modules\Catalog\Http\V1\Controllers\ShowModuleController;
use App\Features\Modules\Catalog\Http\V1\Controllers\UpdateModuleController;
use App\Features\Plans\Catalog\Http\V1\Controllers\ActivatePlanController;
use App\Features\Plans\Catalog\Http\V1\Controllers\ArchivePlanController;
use App\Features\Plans\Catalog\Http\V1\Controllers\DeactivatePlanController;
use App\Features\Plans\Catalog\Http\V1\Controllers\ListPlansController;
use App\Features\Plans\Catalog\Http\V1\Controllers\ShowPlanController;
use App\Features\Plans\Catalog\Http\V1\Controllers\StorePlanController;
use App\Features\Plans\Catalog\Http\V1\Controllers\UpdatePlanController;
use App\Features\Programs\Catalog\Http\V1\Controllers\ListProgramsController;
use App\Features\Programs\Catalog\Http\V1\Controllers\ReorderProgramsController;
use App\Features\Programs\Catalog\Http\V1\Controllers\ShowProgramController;
use App\Features\Programs\Catalog\Http\V1\Controllers\StoreProgramController;
use App\Features\Programs\Catalog\Http\V1\Controllers\UpdateProgramController;
use App\Features\Rules\Assignments\Http\V1\Controllers\ListRuleAssignmentsController;
use App\Features\Rules\Assignments\Http\V1\Controllers\ReplaceRuleAssignmentController;
use App\Features\Rules\Assignments\Http\V1\Controllers\ShowRuleAssignmentController;
use App\Features\Rules\Assignments\Http\V1\Controllers\StoreRuleAssignmentController;
use App\Features\Rules\Assignments\Http\V1\Controllers\WithdrawRuleAssignmentController;
use App\Features\Rules\Catalog\Http\V1\Controllers\ListRulesController;
use App\Features\Rules\Catalog\Http\V1\Controllers\ListRuleVersionsController;
use App\Features\Rules\Catalog\Http\V1\Controllers\PublishRuleVersionController;
use App\Features\Rules\Catalog\Http\V1\Controllers\ShowRuleController;
use App\Features\Rules\Catalog\Http\V1\Controllers\ShowRuleVersionController;
use App\Features\Rules\Catalog\Http\V1\Controllers\StoreRuleController;
use App\Features\Rules\Catalog\Http\V1\Controllers\StoreRuleVersionController;
use App\Features\Rules\Catalog\Http\V1\Controllers\UpdateRuleController;
use App\Features\Rules\Catalog\Http\V1\Controllers\UpdateRuleVersionController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ApplyForSubscriptionController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ApproveSubscriptionController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\CancelSubscriptionController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ChangeSubscriptionPlanController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ChangeSubscriptionProgramController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\FixSubscriptionPlacementController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ListSubscriptionsController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\RejectSubscriptionController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ReleaseSubscriptionPlacementController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ShowCurrentSubscriptionController;
use App\Features\Subscriptions\Catalog\Http\V1\Controllers\ShowSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('ib/v1')
    ->middleware([
        'rbac.auth.user',
        'rbac.bind.gateway.user',
    ])
    ->group(function (): void {
        Route::prefix('admin')
            ->group(function (): void {
                Route::get('modules', ListModulesController::class)->name('ib.v1.admin.modules.index');
                Route::get('modules/{module}', ShowModuleController::class)->name('ib.v1.admin.modules.show');
                Route::patch('modules/{module}', UpdateModuleController::class)->name('ib.v1.admin.modules.update');
                Route::post('modules/{module}/activate', ActivateModuleController::class)->name('ib.v1.admin.modules.activate');
                Route::post('modules/{module}/deactivate', DeactivateModuleController::class)->name('ib.v1.admin.modules.deactivate');
                Route::post('modules/{module}/pause', PauseModuleProcessingController::class)->name('ib.v1.admin.modules.pause');
                Route::post('modules/{module}/resume', ResumeModuleProcessingController::class)->name('ib.v1.admin.modules.resume');
                Route::get('modules/{module}/operational-history', ListModuleOperationalHistoryController::class)
                    ->name('ib.v1.admin.modules.operational-history.index');
                Route::get('plans', ListPlansController::class)->name('ib.v1.admin.plans.index');
                Route::post('plans', StorePlanController::class)->name('ib.v1.admin.plans.store');
                Route::get('plans/{plan}', ShowPlanController::class)->name('ib.v1.admin.plans.show');
                Route::patch('plans/{plan}', UpdatePlanController::class)->name('ib.v1.admin.plans.update');
                Route::post('plans/{plan}/activate', ActivatePlanController::class)->name('ib.v1.admin.plans.activate');
                Route::post('plans/{plan}/deactivate', DeactivatePlanController::class)->name('ib.v1.admin.plans.deactivate');
                Route::delete('plans/{plan}', ArchivePlanController::class)->name('ib.v1.admin.plans.destroy');
                Route::get('plans/{plan}/programs', ListProgramsController::class)->name('ib.v1.admin.plans.programs.index');
                Route::post('plans/{plan}/programs', StoreProgramController::class)->name('ib.v1.admin.plans.programs.store');
                Route::post('plans/{plan}/programs/reorder', ReorderProgramsController::class)->name('ib.v1.admin.plans.programs.reorder');
                Route::get('plans/{plan}/programs/{program}', ShowProgramController::class)->name('ib.v1.admin.plans.programs.show');
                Route::patch('plans/{plan}/programs/{program}', UpdateProgramController::class)->name('ib.v1.admin.plans.programs.update');
                Route::get('plans/{plan}/rules', ListRulesController::class)->name('ib.v1.admin.plans.rules.index');
                Route::post('plans/{plan}/rules', StoreRuleController::class)->name('ib.v1.admin.plans.rules.store');
                Route::get('plans/{plan}/rules/{rule}', ShowRuleController::class)->name('ib.v1.admin.plans.rules.show');
                Route::patch('plans/{plan}/rules/{rule}', UpdateRuleController::class)->name('ib.v1.admin.plans.rules.update');
                Route::get('plans/{plan}/rules/{rule}/versions', ListRuleVersionsController::class)->name('ib.v1.admin.plans.rules.versions.index');
                Route::post('plans/{plan}/rules/{rule}/versions', StoreRuleVersionController::class)->name('ib.v1.admin.plans.rules.versions.store');
                Route::get('plans/{plan}/rules/{rule}/versions/{version}', ShowRuleVersionController::class)->name('ib.v1.admin.plans.rules.versions.show');
                Route::patch('plans/{plan}/rules/{rule}/versions/{version}', UpdateRuleVersionController::class)->name('ib.v1.admin.plans.rules.versions.update');
                Route::post('plans/{plan}/rules/{rule}/versions/{version}/publish', PublishRuleVersionController::class)
                    ->name('ib.v1.admin.plans.rules.versions.publish');
                Route::get('plans/{plan}/rules/{rule}/assignments', ListRuleAssignmentsController::class)
                    ->name('ib.v1.admin.plans.rules.assignments.index');
                Route::post('plans/{plan}/rules/{rule}/assignments', StoreRuleAssignmentController::class)
                    ->name('ib.v1.admin.plans.rules.assignments.store');
                Route::get('plans/{plan}/rules/{rule}/assignments/{assignment}', ShowRuleAssignmentController::class)
                    ->name('ib.v1.admin.plans.rules.assignments.show');
                Route::post('plans/{plan}/rules/{rule}/assignments/{assignment}/replace', ReplaceRuleAssignmentController::class)
                    ->name('ib.v1.admin.plans.rules.assignments.replace');
                Route::post('plans/{plan}/rules/{rule}/assignments/{assignment}/withdraw', WithdrawRuleAssignmentController::class)
                    ->name('ib.v1.admin.plans.rules.assignments.withdraw');
                Route::get('subscriptions', ListSubscriptionsController::class)->name('ib.v1.admin.subscriptions.index');
                Route::get('subscriptions/{subscription}', ShowSubscriptionController::class)->name('ib.v1.admin.subscriptions.show');
                Route::post('subscriptions/{subscription}/approve', ApproveSubscriptionController::class)
                    ->name('ib.v1.admin.subscriptions.approve');
                Route::post('subscriptions/{subscription}/reject', RejectSubscriptionController::class)
                    ->name('ib.v1.admin.subscriptions.reject');
                Route::post('subscriptions/{subscription}/cancel', CancelSubscriptionController::class)
                    ->name('ib.v1.admin.subscriptions.cancel');
                Route::post('subscriptions/{subscription}/change-plan', ChangeSubscriptionPlanController::class)
                    ->name('ib.v1.admin.subscriptions.change-plan');
                Route::post('subscriptions/{subscription}/placement/change', ChangeSubscriptionProgramController::class)
                    ->name('ib.v1.admin.subscriptions.placement.change');
                Route::post('subscriptions/{subscription}/placement/fix', FixSubscriptionPlacementController::class)
                    ->name('ib.v1.admin.subscriptions.placement.fix');
                Route::post('subscriptions/{subscription}/placement/release', ReleaseSubscriptionPlacementController::class)
                    ->name('ib.v1.admin.subscriptions.placement.release');
            });

        Route::prefix('customer')
            ->group(function (): void {
                Route::post('subscriptions', ApplyForSubscriptionController::class)->name('ib.v1.customer.subscriptions.store');
                Route::get('subscriptions/current', ShowCurrentSubscriptionController::class)
                    ->name('ib.v1.customer.subscriptions.current');
            });
    });
