<?php
namespace App\Ai;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/** AI feature gate + task helpers. Every caller must check available() and handle ReplicateException. */
class Ai
{
    public const FEATURES = ['search', 'ask', 'summaries', 'ocr', 'audio', 'assistant'];

    public function __construct(private Replicate $replicate) {}

    public function configured(): bool { return config('ai.enabled') && $this->replicate->configured(); }

    /** Token present + global env switch + feature env switch + HQ switch. */
    public function available(string $feature): bool
    {
        return $this->configured() && (config("ai.features.$feature") ?? false) && Setting::get("ai.$feature", true);
    }

    public function flags(): array
    {
        return collect(self::FEATURES)->mapWithKeys(fn ($f) => [$f => $this->available($f)])->all();
    }

    public function autoPublish(): bool { return (bool) Setting::get('ai.auto_publish', config('ai.auto_publish')); }

    /** Instruction LLM (Llama 3 instruct schema: prompt, system_prompt, max_tokens, temperature → array of strings). */
    public function complete(string $prompt, string $system = '', int $maxTokens = 600, ?string $model = null, float $temperature = 0.2): string
    {
        $out = $this->replicate->run($model ?? config('ai.models.llm'), [
            'prompt' => $prompt, 'system_prompt' => $system, 'max_tokens' => $maxTokens, 'temperature' => $temperature,
        ]);
        return trim(is_array($out) ? implode('', $out) : (string) $out);
    }

    /** First JSON object in an LLM reply (models sometimes wrap it in prose/code fences). */
    public function completeJson(string $prompt, string $system = '', int $maxTokens = 700): array
    {
        $text = $this->complete($prompt, $system."\nReply with a single JSON object only.", $maxTokens);
        if (preg_match('/\{.*\}/s', $text, $m) && is_array($j = json_decode($m[0], true))) return $j;
        throw new ReplicateException('Model did not return valid JSON.');
    }

    /** @param string[] $texts @return float[][] (embedding model: {texts: [...]} → [[...], ...]) */
    public function embed(array $texts): array
    {
        $out = $this->replicate->run(config('ai.models.embed'), ['texts' => array_values($texts)]);
        $vectors = array_map(fn ($v) => is_array($v) && isset($v['embedding']) ? $v['embedding'] : $v, (array) $out);
        if (count($vectors) !== count($texts)) throw new ReplicateException('Embedding count mismatch.');
        return array_map(fn ($v) => array_map('floatval', $v), $vectors);
    }

    /** Text-to-speech → URL of the generated audio file (expires; caller must download it). */
    public function speak(string $text): string
    {
        $out = $this->replicate->run(config('ai.models.tts'), [
            'text' => mb_substr($text, 0, 9500), 'voice_id' => config('ai.tts_voice'), 'english_normalization' => true, 'audio_format' => 'mp3',
        ], 300);
        $url = is_array($out) ? ($out[0] ?? null) : $out;
        if (! is_string($url) || ! str_starts_with($url, 'http')) throw new ReplicateException('TTS returned no audio URL.');
        return $url;
    }

    public static function pgvector(): bool
    {
        static $has;
        return (bool) config('ai.pgvector') && ($has ??= \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' && Schema::hasColumn('edition_chunks', 'embedding'));
    }
}
