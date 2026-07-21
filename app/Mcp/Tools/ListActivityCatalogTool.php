<?php

namespace App\Mcp\Tools;

use App\Models\ActivityCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List activity directory')]
#[Description('List the global activity directory — the shared, platform-wide catalog of activities (Taekwondo, Boxing, Yoga, …) that any club can reuse instead of re-typing the same activity per club. Read-only, non-sensitive. Supports name search (matches English and Arabic).')]
class ListActivityCatalogTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Filter by activity name, English or Arabic (case-insensitive, partial match).'),
            'page' => $schema->integer()->min(1)
                ->description('1-based page number. Defaults to 1.'),
            'per_page' => $schema->integer()->min(1)
                ->description('Results per page. Capped by the server (default 25).'),
        ];
    }

    public function handle(Request $request): Response
    {
        // Any authenticated platform user may read the shared directory.
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $perPage = $this->pageSize($request->get('per_page'));
        $page = max(1, (int) ($request->get('page') ?: 1));
        $search = trim((string) $request->get('search', ''));

        $query = ActivityCatalog::query()
            ->where('is_active', true)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('translations', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('usage_count')
            ->orderBy('name');

        $total = (clone $query)->count();

        $items = $query->forPage($page, $perPage)->get()
            ->map(fn (ActivityCatalog $a) => [
                'uuid' => $a->uuid,
                'name' => $a->name,
                'name_ar' => $a->tr('name', 'ar') === $a->name ? null : $a->tr('name', 'ar'),
                'slug' => $a->slug,
                'description' => $a->description,
                'styles' => collect($a->variants ?: [])->pluck('name')->values()->all(),
                'icon' => $a->icon,
                'has_image' => (bool) $a->picture_url,
                // Curated video clips (YouTube). Sanitized/validated by the model.
                'videos' => collect($a->sanitizedVideos())
                    ->map(fn ($v) => ['id' => $v['id'], 'title' => $v['title'], 'source' => $v['source']])
                    ->values()->all(),
                'usage_count' => $a->usage_count,
            ])->values();

        return Response::json([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'activities' => $items,
        ]);
    }
}
