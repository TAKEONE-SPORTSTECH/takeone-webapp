<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiManager;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;

/**
 * Generic "write with AI" endpoint for rich-text fields. Builds a system prompt
 * from the field's label + purpose and returns sanitized HTML. Runs through the
 * configured AI provider (AiManager) — no data access, no writes.
 */
class AiComposeController extends Controller
{
    public function compose(Request $request, AiManager $ai)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:200',
            'purpose' => 'nullable|string|max:600',
            'instructions' => 'nullable|string|max:2000',
            'current' => 'nullable|string|max:40000',
            'dir' => 'nullable|in:ltr,rtl',
            'both' => 'boolean',
            'format' => 'nullable|in:html,text',
        ]);

        $label = trim($data['label'] ?? '') ?: 'this field';
        $purpose = trim($data['purpose'] ?? '');
        $instructions = trim($data['instructions'] ?? '');
        $current = trim($data['current'] ?? '');
        $language = ($data['dir'] ?? 'ltr') === 'rtl' ? 'Arabic' : 'English';
        $isText = ($data['format'] ?? 'html') === 'text';

        $formatRule = $isText
            ? 'Output plain text only — a single short line or two, with no HTML, no markdown, no surrounding quotes.'
            : 'Output clean HTML using ONLY these tags: <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>. No markdown, no code fences, no <html>/<body> wrapper.';

        $system = "You write professional, ready-to-use content for a single form field in a sports-club management app.\n"
            ."Field label: \"{$label}\".\n"
            .($purpose !== '' ? "What this field is for: {$purpose}\n" : '')
            ."Write in {$language}. {$formatRule} "
            .'No commentary before or after — return only the field content. Keep it concise and professional.';

        if ($instructions !== '' && $current !== '') {
            $user = "Revise the existing content according to this instruction: {$instructions}\n\nExisting content:\n{$current}";
        } elseif ($instructions !== '') {
            $user = $instructions;
        } elseif ($current !== '') {
            $user = "Improve and professionalise this existing content, keeping its intent:\n{$current}";
        } else {
            $user = "Write suitable, professional content for the \"{$label}\" field.";
        }

        try {
            $reply = $ai->text()->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'The AI service is unavailable right now.']);
        }

        $content = $this->clean((string) ($reply['content'] ?? ''), $isText);
        $key = $isText ? 'text' : 'html';

        if ($content === '') {
            return response()->json(['success' => false, 'message' => 'The AI returned nothing usable — try again.']);
        }

        $result = ['success' => true, $key => $content];

        // Paired field: also produce the OTHER language by translating what we
        // just wrote, so EN/AR stay in sync from a single click.
        if (($data['both'] ?? false) && $content !== '') {
            $otherLang = $language === 'Arabic' ? 'English' : 'Arabic';
            $what = $isText ? 'text' : 'HTML (preserving the tags and structure EXACTLY — translate only the visible text)';
            try {
                $t = $ai->text()->chat([
                    ['role' => 'system', 'content' => "You are a professional translator. Translate the {$what} the user sends into {$otherLang}. Output only the translation, no commentary or code fences."],
                    ['role' => 'user', 'content' => $content],
                ]);
                $other = $this->clean((string) ($t['content'] ?? ''), $isText);
                if ($other !== '') {
                    $result['other_lang'] = $otherLang === 'Arabic' ? 'ar' : 'en';
                    $result['other_'.$key] = $other;
                }
            } catch (\Throwable $e) {
                report($e); // best-effort — the primary language still returns
            }
        }

        return response()->json($result);
    }

    /** Strip stray ``` fences; sanitize HTML, or flatten to plain text. */
    private function clean(string $raw, bool $isText): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $raw);
        $raw = trim((string) $raw);

        if ($isText) {
            return trim(preg_replace('/\s+/', ' ', strip_tags($raw)));
        }

        return HtmlSanitizer::clean($raw);
    }
}
