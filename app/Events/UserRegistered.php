<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a new user successfully registers.
 *
 * WHY an Event instead of calling the mailer directly in the controller?
 *   - The controller's job is to handle the HTTP request and return a response.
 *     Sending a welcome email is a *side effect* — decoupling it via an event means
 *     the controller stays focused and we can add more listeners later (e.g., Slack
 *     notification, analytics ping) without touching the controller at all.
 */
class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly User $user) {}
}
