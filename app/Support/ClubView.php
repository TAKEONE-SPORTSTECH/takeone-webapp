<?php

namespace App\Support;

use Illuminate\Support\Facades\View;

/**
 * Picks the correct club-admin section view for the current device.
 *
 * Returns the dedicated mobile file (`admin.club.<section>.mobile`) when the
 * request is from a phone AND that file exists; otherwise falls back to the
 * existing desktop view (`admin.club.<section>.index`). This lets mobile
 * section views be rolled out incrementally with zero regression — a section
 * without a mobile file simply keeps serving its desktop view on all devices.
 *
 * A section owned by a MODULE passes its module namespace, so the same
 * device-picking rule applies to views that live inside the module's own folder
 * (`app/<Module>/resources/views/<section>/…`) rather than in the shared
 * resources/views tree. Everything else is unchanged: a section with no module
 * keeps resolving exactly as before.
 *
 * @see \App\Http\Middleware\DetectDevice  (sets the is_mobile request flag)
 */
class ClubView
{
    public static function pick(string $section, ?string $module = null): string
    {
        $prefix = $module ? "{$module}::{$section}" : "admin.club.{$section}";

        $isMobile = (bool) request()->attributes->get('is_mobile', false);
        $mobile = "{$prefix}.mobile";

        if ($isMobile && View::exists($mobile)) {
            return $mobile;
        }

        return "{$prefix}.index";
    }
}
