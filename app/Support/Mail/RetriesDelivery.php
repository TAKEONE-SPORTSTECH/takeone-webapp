<?php

namespace App\Support\Mail;

/**
 * How hard the platform tries to deliver an email before giving up — ONE rule
 * for every queued mailable and notification.
 *
 * Before this, a send was tried three times within a few seconds (the worker's
 * --tries=3, no wait between), so a Gmail hiccup, a rate limit or a password
 * being changed lost the email for good. Now it waits and tries again: 1 min,
 * 5 min, 15 min, 30 min, 1 h, 2 h, 4 h — about eight hours in all. Anything
 * still failing after that is picked up again by `mail:retry-failed` once
 * `mail:health` says sending works (App\Support\Mail\MailHealth).
 *
 * A verification link is built when the email is RENDERED, not when it is
 * queued, so a late retry still carries a fresh link.
 */
trait RetriesDelivery
{
    /** @var int attempts in all, the first included */
    public $tries = 8;

    /** @var int[] seconds to wait before each next attempt */
    public $backoff = [60, 300, 900, 1800, 3600, 7200, 14400];
}
