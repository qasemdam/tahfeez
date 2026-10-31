<?php

namespace App\Providers;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Infrastructure\Quran\FixtureQuranDataProvider;
use App\Policies\AttendancePolicy;
use App\Policies\GroupPolicy;
use App\Policies\MemorizationPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The only binding business code sees. The implementation is a
        // development fixture until the dataset gate (OD-029) is resolved.
        $this->app->singleton(QuranDataProvider::class, FixtureQuranDataProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Domain models live under App\Domain\{Module}\Models, so the default
        // factory resolver would look in the wrong namespace. Map every model
        // to Database\Factories\{Model}Factory instead.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory',
        );

        // Student is not under App\Models, so its policy cannot be discovered
        // by convention. Register it explicitly.
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(Memorization::class, MemorizationPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
    }
}
