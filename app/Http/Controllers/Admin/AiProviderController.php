<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Services\Ai\AiManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Super-admin settings for AI providers (text / voice / image), local or cloud.
 * API keys are write-only: stored encrypted, never returned to the browser.
 * The route group already enforces auth + role:super-admin.
 */
class AiProviderController extends Controller
{
    private const DRIVERS = ['ollama', 'openai', 'anthropic', 'gemini', 'elevenlabs', 'automatic1111', 'whisper'];

    public function index()
    {
        // API keys are $hidden on the model, so this never ships secrets.
        $providers = AiProvider::query()->orderBy('modality')->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'modality' => $p->modality,
                'driver' => $p->driver,
                'base_url' => $p->base_url,
                'model' => $p->model,
                'options' => $p->options,
                'is_default' => $p->is_default,
                'enabled' => $p->enabled,
                'has_key' => filled($p->getAttributes()['api_key'] ?? null),
            ]);

        return view('admin.ai.index', [
            'providers' => $providers,
            'modalities' => AiProvider::MODALITIES,
            'drivers' => self::DRIVERS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);
        $provider = new AiProvider($this->fill($data, creating: true));
        $provider->save();
        $this->syncDefault($provider);

        return response()->json(['success' => true, 'message' => 'Provider added.']);
    }

    public function update(Request $request, AiProvider $provider)
    {
        $data = $this->validated($request, creating: false);
        $fill = $this->fill($data, creating: false);

        // Only overwrite the key when a new one is actually supplied.
        if (! filled($data['api_key'] ?? null)) {
            unset($fill['api_key']);
        }

        $provider->fill($fill)->save();
        $this->syncDefault($provider);

        return response()->json(['success' => true, 'message' => 'Provider updated.']);
    }

    public function destroy(AiProvider $provider)
    {
        $provider->delete();

        return response()->json(['success' => true, 'message' => 'Provider removed.']);
    }

    /** Live connectivity check (text drivers only for now). */
    public function test(AiProvider $provider, AiManager $ai)
    {
        if ($provider->modality !== 'text') {
            return response()->json(['success' => false, 'message' => 'Testing is available for text providers for now.']);
        }

        try {
            $reply = $ai->text($provider->id)->chat([
                ['role' => 'user', 'content' => 'Reply with the single word: OK'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connected. Model replied: '.mb_substr(trim((string) ($reply['content'] ?? '')), 0, 60),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed: '.mb_substr($e->getMessage(), 0, 160)]);
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'modality' => ['required', Rule::in(AiProvider::MODALITIES)],
            'driver' => ['required', Rule::in(self::DRIVERS)],
            'base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:400',
            'model' => 'nullable|string|max:120',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:200000',
            'timeout' => 'nullable|integer|min:1|max:600',
            'is_default' => 'boolean',
            'enabled' => 'boolean',
        ]);
    }

    private function fill(array $data, bool $creating): array
    {
        return [
            'name' => $data['name'],
            'modality' => $data['modality'],
            'driver' => $data['driver'],
            'base_url' => $data['base_url'] ?? null,
            'api_key' => $data['api_key'] ?? null,
            'model' => $data['model'] ?? null,
            'options' => array_filter([
                'temperature' => $data['temperature'] ?? null,
                'max_tokens' => $data['max_tokens'] ?? null,
                'timeout' => $data['timeout'] ?? null,
            ], fn ($v) => $v !== null),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'enabled' => (bool) ($data['enabled'] ?? true),
        ];
    }

    /** Exactly one default per modality. */
    private function syncDefault(AiProvider $provider): void
    {
        if (! $provider->is_default) {
            return;
        }

        DB::transaction(function () use ($provider) {
            AiProvider::query()
                ->where('modality', $provider->modality)
                ->where('id', '!=', $provider->id)
                ->update(['is_default' => false]);
        });
    }
}
