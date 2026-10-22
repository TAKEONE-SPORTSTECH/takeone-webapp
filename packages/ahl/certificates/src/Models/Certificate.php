<?php

namespace Ahl\Certificates\Models;

use Ahl\Certificates\Certificates;
use Ahl\Certificates\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A certificate goes draft → approved (→ revoked). Only approved certificates carry a valid verification
 * QR, count prints and keep a stored PDF. The recipient and the optional context are polymorphic.
 *
 * @property string $certificate_no
 * @property string $type
 * @property CertificateStatus $status
 * @property string|null $grade
 * @property string $source
 */
class Certificate extends Model
{
    protected $table = 'certificates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CertificateStatus::class,
            'details' => 'array',
            'issued_on' => 'date',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'print_count' => 'integer',
            'source_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $c) {
            $c->uuid ??= (string) Str::uuid();
            // The same thing certified again takes back the QR and number it was issued with.
            if ($c->verify_token === null && $c->certificate_no === null && ($was = $c->rememberedIdentity())) {
                $c->verify_token = $was->verify_token;
                $c->certificate_no = $was->certificate_no;
            }
            $c->certificate_no ??= static::nextNo();
            $c->verify_token ??= Str::random(32);
            $c->status ??= CertificateStatus::Draft;
            $c->source ??= 'manual';
        });

        // Once issued, the QR is out in the world: remember it, and remember it again on the
        // way out, so a certificate issued before this table existed is kept as well.
        static::saved(function (Certificate $c) {
            if (! $c->isDraft() && ($c->wasRecentlyCreated || $c->wasChanged('status'))) {
                $c->rememberIdentity();
            }
        });
        static::deleting(function (Certificate $c) {
            if (! $c->isDraft()) {
                $c->rememberIdentity();
            }
        });
    }

    /**
     * What this certificate certifies — holder, context, kind and source — as one key.
     * Two certificates with the same identity are the same certificate issued twice.
     */
    public function identityKey(): string
    {
        return hash('sha256', implode('|', [
            $this->recipient_type, $this->recipient_id, $this->context_type ?? '', $this->context_id ?? '',
            $this->type, $this->source ?? 'manual', $this->source_id ?? '',
        ]));
    }

    private function rememberIdentity(): void
    {
        if (! static::identitiesReady() || ! $this->verify_token || ! $this->certificate_no) {
            return;
        }
        $this->getConnection()->table('certificate_identities')->updateOrInsert(
            ['identity' => $this->identityKey()],
            ['verify_token' => $this->verify_token, 'certificate_no' => $this->certificate_no, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /** The token and number this identity was issued with — unless a live certificate already holds either. */
    private function rememberedIdentity(): ?object
    {
        if (! static::identitiesReady()) {
            return null;
        }
        $was = $this->getConnection()->table('certificate_identities')->where('identity', $this->identityKey())->first();
        if (! $was) {
            return null;
        }
        $taken = static::query()->where('verify_token', $was->verify_token)->orWhere('certificate_no', $was->certificate_no)->exists();

        return $taken ? null : $was;
    }

    private static ?bool $identitiesReady = null;

    private static function identitiesReady(): bool
    {
        return static::$identitiesReady ??= \Illuminate\Support\Facades\Schema::hasTable('certificate_identities');
    }

    /** Next serial: prefix + two-digit year + zero-padded sequence. */
    public static function nextNo(): string
    {
        $prefix = config('certificates.number.prefix', 'C').now()->format('y');
        $digits = (int) config('certificates.number.digits', 5);
        $last = static::query()->where('certificate_no', 'like', "{$prefix}%")->orderByDesc('certificate_no')->value('certificate_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, $digits, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === CertificateStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === CertificateStatus::Approved;
    }

    public function isRevoked(): bool
    {
        return $this->status === CertificateStatus::Revoked;
    }

    /** Draft or approved: blocks a duplicate for the same achievement. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', '!=', CertificateStatus::Revoked->value);
    }

    public function scopeForContext(Builder $q, Model $context): Builder
    {
        return $q->where('context_type', $context->getMorphClass())->where('context_id', $context->getKey());
    }

    public function scopeForRecipient(Builder $q, Model $recipient): Builder
    {
        return $q->where('recipient_type', $recipient->getMorphClass())->where('recipient_id', $recipient->getKey());
    }

    public function scopeFromSource(Builder $q, string $source, int|string|null $sourceId = null): Builder
    {
        return $q->where('source', $source)->when($sourceId !== null, fn ($w) => $w->where('source_id', $sourceId));
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Certificates::templateModel(), 'template_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(self::userModel(), 'issued_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::userModel(), 'approved_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(self::userModel(), 'revoked_by');
    }

    public function typeLabel(?string $locale = null): ?string
    {
        return Certificates::label('types', $this->type, $locale);
    }

    public function gradeLabel(?string $locale = null): ?string
    {
        return Certificates::label('grades', $this->grade, $locale);
    }

    public function sourceLabel(?string $locale = null): ?string
    {
        return Certificates::label('sources', $this->source, $locale);
    }

    /** @return class-string<Model> */
    protected static function userModel(): string
    {
        return config('certificates.user_model') ?? config('auth.providers.users.model');
    }
}
