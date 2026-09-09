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

    public function index(Request $request)
    {
        // API keys are $hidden on the model, so this never ships secrets.
        $providers = AiProvider::query()->orderBy('modality')->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn ($p) => $this->providerPayload($p));

        $mobile = $request->attributes->get('is_mobile') && view()->exists('admin.ai.mobile');

        return view($mobile ? 'admin.ai.mobile' : 'admin.ai.index', [
            'providers' => $providers,
            'modalities' => AiProvider::MODALITIES,
            'drivers' => self::DRIVERS,
            // Which model writes the event translations, and the order the
            // rest are tried in if it cannot — see App\Translation.
            'translationPrimary' => \App\Translation\Translations::translatorId(),
            'translationChain' => \App\Translation\Translations::chain(),
        ]);
    }

    /** Browser-safe projection of a provider — never includes the encrypted key. */
    private function providerPayload(AiProvider $p): array
    {
        return [
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
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);
        $provider = new AiProvider($this->fill($data, creating: true));
        $provider->save();
        $this->syncDefault($provider);

        return response()->json(['success' => true, 'message' => 'Provider added.', 'provider' => $this->providerPayload($provider->fresh())]);
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

        return response()->json(['success' => true, 'message' => 'Provider updated.', 'provider' => $this->providerPayload($provider->fresh())]);
    }

    public function destroy(AiProvider $provider)
    {
        $provider->delete();

        return response()->json(['success' => true, 'message' => 'Provider removed.']);
    }

    /**
     * Choose which model writes the event translations.
     *
     * Separate from `is_default` on purpose: the default is what the Copilot
     * uses, and an operator may perfectly well want Claude answering the coach
     * while a local model does the translating — or the reverse when the bill
     * arrives. One click, effective on the next translation, reversible with
     * the same click.
     *
     * Sending no id (or 0) returns to automatic: the default provider first,
     * then everything else enabled, then the built-in local model.
     */
    public function translationPrimary(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'integer'],
        ]);

        $id = (int) ($data['provider_id'] ?? 0);

        if ($id > 0) {
            // Must be a real, enabled TEXT provider. Anything else would
            // silently do nothing, which is worse than refusing.
            $provider = AiProvider::query()->enabled()->modality('text')->find($id);

            if (! $provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'That provider is not an enabled text provider.',
                ], 422);
            }
        }

        \App\Translation\Translations::useTranslator($id ?: null);

        $chain = \App\Translation\Translations::chain();

        return response()->json([
            'success' => true,
            'message' => $id > 0
                ? 'Translations will use '.($chain[0]['label'] ?? 'that model').'.'
                : 'Translations will follow the default provider.',
            'primary' => $id ?: null,
            'chain' => $chain,
        ]);
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
