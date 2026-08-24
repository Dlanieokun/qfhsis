<?php

namespace App\Observers;

use App\Http\Controllers\DashboardController;

/**
 * Observer to automatically clear dashboard cache when household profiles change.
 * 
 * Usage: Register in AppServiceProvider boot():
 *   HouseholdProfile::observe(HouseholdProfileObserver::class);
 */
class HouseholdProfileObserver
{
    /**
     * Handle the created event.
     */
    public function created(): void
    {
        DashboardController::clearCache();
    }

    /**
     * Handle the updated event.
     */
    public function updated(): void
    {
        DashboardController::clearCache();
    }

    /**
     * Handle the deleted event.
     */
    public function deleted(): void
    {
        DashboardController::clearCache();
    }

    /**
     * Handle the force deleted event.
     */
    public function forceDeleted(): void
    {
        DashboardController::clearCache();
    }
}