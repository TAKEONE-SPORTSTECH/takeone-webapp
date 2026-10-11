<?php

namespace App\Certificates\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A context whose printed pages are not certificate records — an event's officials'
 * badges, drawn from the officials as they stand. Support\CertificateBundles builds its
 * one-PDF file through these instead of reading issued certificates, so every kind of
 * printed page shares one background builder, one store and one download.
 */
interface PrintsPages
{
    /** How many pages of $type there are to print now. */
    public function printCount(Model $context, string $type): int;

    /** A short signature of everything the pages depend on: change it and the old file no longer answers. */
    public function printSignature(Model $context, string $type): string;

    /** All of them as one print-ready PDF. $progress(done, total) is told as it goes. */
    public function printAll(Model $context, string $type, ?callable $progress = null): string;
}
