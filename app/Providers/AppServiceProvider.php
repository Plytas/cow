<?php

namespace App\Providers;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel Zero sets the default command with isSingleCommand=true, which routes
        // ALL argv to CowCommand and breaks subcommand routing. Override to false so
        // subcommands (cow:list, cow:projects etc.) are routable while `cow` with no
        // args still falls back to the TUI.
        Artisan::starting(fn($artisan) => $artisan->setDefaultCommand('cow', false));
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
