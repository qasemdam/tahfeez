<?php

namespace App\Providers;

use App\Domain\Students\Models\Student;
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
        //
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
    }
}
