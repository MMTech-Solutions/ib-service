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
            });
    });
