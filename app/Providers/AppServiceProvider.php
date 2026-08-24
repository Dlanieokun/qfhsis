<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HouseholdProfile;
use App\Models\MaternalCareRecord;
use App\Models\Prenatal8AncRecord;
use App\Models\ChildImmunizationRecord;
use App\Models\FamilyPlanningRecord;
use App\Observers\HouseholdProfileObserver;
use App\Observers\MaternalCareRecordObserver;
use App\Observers\Prenatal8AncRecordObserver;
use App\Observers\ChildImmunizationRecordObserver;
use App\Observers\FamilyPlanningRecordObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * 
     * Register Model Observers that automatically clear the dashboard cache
     * when critical FHSIS records are created, updated, or deleted.
     */
    public function boot(): void
    {
        // Register observers for dashboard cache invalidation
        HouseholdProfile::observe(HouseholdProfileObserver::class);
        MaternalCareRecord::observe(MaternalCareRecordObserver::class);
        Prenatal8AncRecord::observe(Prenatal8AncRecordObserver::class);
        ChildImmunizationRecord::observe(ChildImmunizationRecordObserver::class);
        FamilyPlanningRecord::observe(FamilyPlanningRecordObserver::class);
        
        // Additional observers can be registered as needed for other critical models:
        // - PrenatalImmunizationRecord
        // - PrenatalSupplementationRecord
        // - PrenatalLabScreeningRecord
        // - IntrapartumRecord
        // - PostpartumRecord
        // - ChildNutritionRecord
        // - ChildSickRecord
        // - FilariasisRegistry
        // - And others as desired
    }
}