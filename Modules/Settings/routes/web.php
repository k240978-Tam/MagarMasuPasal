<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingsController;
use Modules\Settings\Http\Controllers\TaxRuleController;

Route::middleware(['auth', 'can:settings.manage'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::put('receipt-template', [SettingsController::class, 'updateReceiptTemplate'])->name('receipt-template.update');
    Route::put('feature-toggles', [SettingsController::class, 'updateFeatureToggles'])->name('feature-toggles.update');

    Route::post('tax-rules', [TaxRuleController::class, 'store'])->name('tax-rules.store');
    Route::patch('tax-rules/{taxRule}/toggle', [TaxRuleController::class, 'toggle'])->name('tax-rules.toggle');
    Route::delete('tax-rules/{taxRule}', [TaxRuleController::class, 'destroy'])->name('tax-rules.destroy');
});
