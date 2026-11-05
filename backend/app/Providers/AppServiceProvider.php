<?php

namespace App\Providers;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Reports\DTOs\DashboardSummary;
use App\Domain\Reports\DTOs\MonthlyReport;
use App\Domain\Reports\DTOs\WeeklyReport;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Infrastructure\Quran\DatabaseQuranDataProvider;
use App\Infrastructure\Quran\FixtureQuranDataProvider;
use App\Policies\AttendancePolicy;
use App\Policies\GroupPolicy;
use App\Policies\MemorizationPolicy;
use App\Policies\RecitationPolicy;
use App\Policies\ReportPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\StudentPolicy;
use App\Policies\WeeklyEvaluationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The only binding business code sees. `auto` resolves to the database
        // provider in production and to the fixture everywhere else, so a hosted
        // deployment never serves fixture data as if it were canonical, and a
        // developer machine keeps working before any dataset is imported
        // (OD-029). Scoped, not singleton: activating a new dataset must take
        // effect on the next request.
        $this->app->scoped(QuranDataProvider::class, static function ($app): QuranDataProvider {
            $configured = (string) config('quran.provider', 'auto');

            $useDatabase = $configured === 'database'
                || ($configured === 'auto' && $app->environment('production'));

            return $useDatabase
                ? new DatabaseQuranDataProvider
                : new FixtureQuranDataProvider;
        });
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
        Gate::policy(Recitation::class, RecitationPolicy::class);
        Gate::policy(WeeklyEvaluation::class, WeeklyEvaluationPolicy::class);
        Gate::policy(WeeklyReport::class, ReportPolicy::class);
        Gate::policy(MonthlyReport::class, ReportPolicy::class);
        Gate::policy(DashboardSummary::class, ReportPolicy::class);

        // Login is the only unauthenticated write path, so it is the only
        // brute-force surface: limit per credential pair, then per address so
        // one host cannot spray many accounts. A shared NAT keeps working
        // because the tighter limit is keyed by email + IP, not by IP alone.
        RateLimiter::for('login', static function (Request $request): array {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by((string) $request->ip()),
            ];
        });
    }
}
