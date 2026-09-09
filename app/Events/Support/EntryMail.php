<?php

namespace App\Events\Support;

use App\Mail\EventEntryEmail;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the entrant's confirmation — and decides when there is nobody to send
 * it to.
 *
 * One place, because three call sites want it (a public request received, that
 * request accepted or declined, and an athlete entered by their coach) and
 * because the ONLY interesting logic is the refusals. A Mailable queued from
 * three controllers would have had this reasoning copied three times or, more
 * likely, not at all.
 *
 * ⚠️ It can never break the write it hangs off. Every call is wrapped in
 * `rescue()`, exactly as the in-app notifications beside it are: an entry is a
 * competitor's place in a competition, and a mail server having a bad afternoon
 * must not cost them it.
 */
class EntryMail
{
    /**
     * Addresses the platform generated for somebody who never gave one.
     *
     * People entered off a paper list or an import get a synthetic address so
     * the row has a unique key — they are not inboxes, and mailing them is at
     * best a bounce and at worst a stranger receiving somebody else's entry.
     * A person who genuinely gave no email has NULL (see EntryClaim), which the
     * check below catches on its own; this list is for the ones that look real.
     */
    private const NOT_INBOXES = ['imported.takeone.local', 'entries.takeone.bh'];

    public function send(
        ClubEvent $event,
        User $athlete,
        string $state,
        ?string $division = null,
        ?ClubEventRegistration $registration = null,
    ): void {
        rescue(function () use ($event, $athlete, $state, $division, $registration) {
            $to = $this->deliverableAddress($athlete);

            if ($to === null) {
                return;
            }

            /*
             * Where the button goes.
             *
             * An accepted entrant gets THEIR OWN ENTRY — the thing they will
             * come back for, and the page that answers what they owe and when
             * they fight. Anyone else gets the event, because a pending or
             * declined request has no panel to open (`/my-entry` redirects
             * somebody with no registration back to the entry form, which
             * would read as "enter again").
             *
             * Both are ordinary signed-in addresses, not magic links: there is
             * no token in this email, so a forwarded copy grants nothing.
             */
            $url = $state === EventEntryEmail::ENTERED
                ? route('events.public.my-entry', ['event' => $event->uuid])
                : route('events.public', ['event' => $event->uuid]);

            /*
             * WHAT IS OWED — the figure, and deliberately not the bank details.
             *
             * The amount belongs here: it is the one fact that decides whether
             * this person does something today, and leaving it out means an
             * email that says "you're in" and nothing about the money. The
             * ACCOUNT NUMBERS do not. Email is forwarded, archived and read on
             * borrowed screens, and the club's transfer details already live on
             * the entry panel behind a sign-in — so the email names the sum and
             * the link carries the way to pay it.
             *
             * `charged()` rather than the event's list price: what this
             * registration was actually billed is the honest number once
             * options, late fees or a coach's arrangement have been applied.
             */
            $owed = null;
            $payHow = null;

            if ($registration !== null && $state === EventEntryEmail::ENTERED) {
                $amount = EventFee::charged($registration, $event);

                if ($amount > 0) {
                    $owed = EventFee::display($amount, EventFee::currency($event));
                    $payHow = __('events.entry_mail_pay_how');
                }
            }

            /*
             * ⚠️ Addressed to the MODEL, not to `$to`.
             *
             * `deliverableAddress()` above is still the gate — it is what
             * refuses a placeholder domain — but Laravel reads a recipient's
             * language from the Illuminate\Contracts\Translation\
             * HasLocalePreference contract on the MODEL, and a plain string
             * has no preference to read. Passing the address meant every
             * queued entry confirmation rendered in English however the athlete
             * had set their language, because a queue worker has no request and
             * so no locale of its own.
             */
            Mail::to($athlete)->queue(new EventEntryEmail(
                event: $event,
                athlete: $athlete,
                state: $state,
                division: $division,
                entryUrl: $url,
                owed: $owed,
                payHow: $payHow,
            ));
        }, null, false);
    }

    /** Their address, or null when there is not one worth sending to. */
    private function deliverableAddress(User $athlete): ?string
    {
        $email = trim((string) ($athlete->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $domain = strtolower((string) substr($email, strrpos($email, '@') + 1));

        return in_array($domain, self::NOT_INBOXES, true) ? null : $email;
    }
}
