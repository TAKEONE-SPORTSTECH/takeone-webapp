<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FormSubmission extends Model
{
    protected $fillable = ['form_id', 'user_id', 'data', 'files', 'ip'];

    protected $casts = [
        'data' => 'array',
        'files' => 'array',
    ];

    protected static function booted(): void
    {
        // Purge uploaded files before the submission row is removed.
        static::deleting(function (self $submission) {
            foreach (($submission->files ?? []) as $f) {
                try {
                    if (! empty($f['path']) && Storage::disk($f['disk'] ?? 'public')->exists($f['path'])) {
                        Storage::disk($f['disk'] ?? 'public')->delete($f['path']);
                    }
                } catch (\Throwable $e) {
                    // best-effort
                }
            }
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
