<?php

namespace App\Certificates\Controllers;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\Models\Certificate;
use App\Certificates\Contracts\CertificateContext;
use App\Certificates\Support\Contexts;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Lookups every certificates controller makes — and makes the SAME way, so a
 * missing thing and a thing that is not yours give one answer (404), never two
 * (CLAUDE.md → Anti-Enumeration).
 */
trait Concerns
{
    protected function certificate(string $uuid, Request $request, string $ability = 'view'): Certificate
    {
        $c = Engine::model()::query()->where('uuid', $uuid)->first();
        abort_unless($c && $request->user() && Gate::forUser($request->user())->allows($ability, $c), 404);

        return $c;
    }

    /** @return array{0: CertificateContext, 1: Model} */
    protected function managedContext(string $alias, string $key, Request $request): array
    {
        $found = app(Contexts::class)->resolve($alias, $key);
        $user = $request->user();
        abort_unless($found && $user instanceof User && $found[0]->canManage($found[1], $user), 404);

        return $found;
    }

    protected function locale(): string
    {
        return in_array(app()->getLocale(), ['en', 'ar'], true) ? app()->getLocale() : 'en';
    }
}
