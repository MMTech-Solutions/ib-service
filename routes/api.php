<?php

declare(strict_types=1);

use App\Features\Modules\Catalog\Http\V1\Controllers\ListModulesController;
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
            });
    });
