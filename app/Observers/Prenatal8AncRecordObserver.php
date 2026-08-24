<?php

namespace App\Observers;

use App\Http\Controllers\DashboardController;

/**
 * Observer to automatically clear dashboard cache when prenatal 8-ANC records change.
 * 
 * Usage: Register in AppServiceProvider boot():
 *   Prenatal8AncRecord::observe(Prenatal8AncRecordObserver::class);
 */
class Prenatal8AncRecordObserver
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