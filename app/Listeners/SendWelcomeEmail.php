<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Support\Facades\Log;

/**
 * Sends a welcome email to a newly registered user.
 *
 * In a real application this would dispatch a Mailable via Mail::to(...)->send().
 * Here we write to the log so the behaviour is observable without a mail server,
 * which keeps the focus on the Event/Listener wiring rather than email templates.
 */
class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        // Production: Mail::to($event->user)->send(new WelcomeMail($event->user));
        Log::info('Welcome email sent.', [
            'user_id' => $event->user->id,
            'email'   => $event->user->email,
        ]);
    }
}
