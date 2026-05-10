<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class SupabaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Validates that all required Supabase environment variables are present
     * and non-empty at application boot time.
     *
     * @throws \RuntimeException if any required variable is absent or empty.
     */
    public function boot(): void
    {
        $required = [
            'SUPABASE_URL',
            'SUPABASE_ANON_KEY',
            'SUPABASE_SERVICE_ROLE_KEY',
            'SUPABASE_BUCKET',
        ];

        foreach ($required as $var) {
            if (empty(env($var))) {
                throw new \RuntimeException("Missing required environment variable: {$var}");
            }
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
