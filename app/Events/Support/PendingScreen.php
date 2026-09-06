<?php

namespace App\Events\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A screen waiting to be told what it is. See the migration for why it exists.
 *
 * It can display exactly one thing — its own pairing code — and it grants
 * nothing else. Claiming it is the authenticated act that creates a real device
 * in a real package's fleet; this row then holds only the address to send the
 * screen to, and is spent.
 */
class PendingScreen extends Model
{
    protected $table = 'pending_screens';

    protected $fillable = ['token_hash', 'token_hint', 'pairing_code', 'destination', 'claimed_at'];

    protected $hidden = ['token_hash', 'token_hint'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    /**
     * A brand-new waiting screen, and the one plaintext token it will ever have.
     *
     * @return array{screen: self, token: string}
     */
    public static function begin(): array
    {
        $token = Str::random(40);

        $screen = static::create([
            'token_hash' => static::hash($token),
            'token_hint' => substr($token, 0, 6),
            'pairing_code' => static::freshCode(),
        ]);

        return ['screen' => $screen, 'token' => $token];
    }

    public static function resolve(?string $token): ?self
    {
        if (! is_string($token) || strlen($token) !== 40 || ! ctype_alnum($token)) {
            return null;
        }

        return static::where('token_hash', static::hash($token))->first();
    }

    /** The screen behind a code that is still waiting. */
    public static function pairable(?string $code): ?self
    {
        if (! is_string($code) || ! preg_match('/^[A-Z0-9]{6}$/', $code)) {
            return null;
        }

        return static::where('pairing_code', $code)->whereNull('claimed_at')->first();
    }

    /** Told what it is: here is where to go. The code is spent. */
    public function settle(string $destination): void
    {
        $this->forceFill([
            'destination' => $destination,
            'claimed_at' => now(),
            'pairing_code' => null,
        ])->save();
    }

    /**
     * Spend the code, atomically, and say whether THIS caller is the one who
     * spent it.
     *
     * Read-then-write was not safe: two pairing requests carrying the same code
     * — a double-tapped button, two consoles, one duplicated event — both saw an
     * unclaimed row, and both went on to create a screen. That left a live board
     * plus an orphan device nobody could see or unpair. The UPDATE carries the
     * condition, so the database picks a winner and the loser is told no.
     *
     * The destination is not known yet (the screen has to be adopted first), so
     * it is written afterwards by land().
     */
    public function spend(): bool
    {
        return static::whereKey($this->getKey())
            ->whereNull('claimed_at')
            ->update(['claimed_at' => now(), 'pairing_code' => null]) === 1;
    }

    /** Where the screen goes, once it has actually been adopted. */
    public function land(string $destination): void
    {
        $this->forceFill(['destination' => $destination])->save();
    }

    /**
     * Hashed with a plain SHA-256, deliberately — this is a 40-character random
     * token, not a password. There is nothing to guess and nothing to stretch.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * An unambiguous code: no vowels (so no accidental words) and no 0/O/1/I.
     * Read aloud across a hall, and typed by hand when a camera will not focus.
     */
    private static function freshCode(): string
    {
        $alphabet = 'BCDFGHJKLMNPQRSTVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('pairing_code', $code)->exists());

        return $code;
    }
}
