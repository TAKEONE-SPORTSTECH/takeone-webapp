<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

/**
 * Deletes a model's uploaded files when the record is deleted.
 *
 * PROJECT RULE (see CLAUDE.md → "Delete Files Before Records"): whenever a
 * record owns uploaded files, the files MUST be removed FIRST, then the row.
 * This trait enforces that automatically by hooking the model's `deleting`
 * event — so the files are purged while we still have their stored paths,
 * and the record is only removed afterwards.
 *
 * Declare the file-bearing attributes on the model via `$fileUploads`,
 * mapping each attribute to the disk it lives on. Bare (int-keyed) entries
 * default to the `public` disk:
 *
 *   protected array $fileUploads = [
 *       'proof_of_payment' => 'local',   // attribute => disk
 *       'refund_proof'     => 'local',
 *       'cover_image',                    // shorthand → 'public' disk
 *   ];
 *
 * NOTE: Eloquent model events do NOT fire on mass/bulk deletes
 * (`Model::query()->delete()`), so this trait can't run there — delete the
 * files explicitly (or iterate the records with `each(fn ($m) => $m->delete())`)
 * when doing bulk cleanups.
 */
trait DeletesUploadedFiles
{
    public static function bootDeletesUploadedFiles(): void
    {
        static::deleting(function ($model) {
            // On soft-delete models, only purge files on a real (force) delete —
            // a soft-deleted record may still be restored.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->deleteUploadedFiles();
        });
    }

    /**
     * Delete every uploaded file this record points to. Best-effort: a
     * missing file or storage error is reported but never blocks the
     * record's own deletion.
     */
    public function deleteUploadedFiles(): void
    {
        foreach ($this->uploadedFileMap() as $attribute => $disk) {
            $path = $this->getAttribute($attribute);

            if (! is_string($path) || $path === '') {
                continue;
            }

            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Normalise the model's `$fileUploads` declaration into
     * [attribute => disk], defaulting bare entries to the `public` disk.
     */
    private function uploadedFileMap(): array
    {
        $declared = property_exists($this, 'fileUploads') ? $this->fileUploads : [];
        $map = [];

        foreach ($declared as $key => $value) {
            if (is_int($key)) {
                $map[$value] = 'public';
            } else {
                $map[$key] = $value;
            }
        }

        return $map;
    }
}
