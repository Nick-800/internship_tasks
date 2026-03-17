<?php

namespace App\Providers;

use App\Events\FundsTransferred;
use App\Events\UserRegistered;
use App\Listeners\SendTransferNotification;
use App\Listeners\SendWelcomeEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
     *
     * Events & Listeners are wired here manually so the mapping is explicit
     * and easy to locate — no magic auto-discovery needed for a focused project.
     *
     * WHY decouple via Events instead of calling listeners directly?
     *   - The controller / service does not need to know what side effects exist.
     *   - Adding a new listener (e.g., Slack notification) requires zero changes
     *     to the code that fires the event.
     *   - Listeners can be queued independently without touching business logic.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // UserRegistered → welcome email
        Event::listen(UserRegistered::class, SendWelcomeEmail::class);

        // FundsTransferred → notify both wallet owners
        Event::listen(FundsTransferred::class, SendTransferNotification::class);
    }
}
