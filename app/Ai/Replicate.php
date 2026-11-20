<?php
namespace App\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Replicate HTTP client (https://replicate.com/docs/reference/http).
 * Official models: POST /models/{owner}/{name}/predictions; versioned community models: POST /predictions {version}.
 * Uses `Prefer: wait=N` (sync) and falls back to polling `urls.get` until the deadline.
 */
class Replicate
{
    public function configured(): bool { return filled(config('ai.token')); }

    /** @return mixed prediction output */
    public function run(string $model, array $input, ?int $timeout = null): mixed
    {
        if (! $this->configured()) throw new ReplicateException('Replicate is not configured (REPLICATE_API_TOKEN missing).');
        $this->guardBudget();
        $timeout ??= config('ai.timeout');
        $wait = max(1, min(60, $timeout));
        $base = rtrim(config('ai.base_url'), '/');

        [$url, $body] = str_contains($model, ':')
            ? ["$base/predictions", ['version' => substr($model, strpos($model, ':') + 1), 'input' => $input]]
            : ["$base/models/$model/predictions", ['input' => $input]];

        $res = $this->http()->withHeaders(['Prefer' => "wait=$wait"])->timeout($wait + 10)->post($url, $body);
        if ($res->failed()) throw new ReplicateException("Replicate error {$res->status()}: ".mb_substr($res->body(), 0, 300));
        $p = $res->json();

        $deadline = microtime(true) + $timeout;
        while (in_array($p['status'] ?? null, ['starting', 'processing'], true)) {
            if (microtime(true) > $deadline || empty($p['urls']['get'])) {
                throw new ReplicateException('Replicate prediction timed out.');
            }
            usleep(1_500_000);
            $poll = $this->http()->timeout(15)->get($p['urls']['get']);
            if ($poll->failed()) throw new ReplicateException("Replicate poll error {$poll->status()}");
            $p = $poll->json();
        }
        if (($p['status'] ?? null) !== 'succeeded') {
            throw new ReplicateException('Replicate prediction '.($p['status'] ?? 'failed').': '.mb_substr((string) ($p['error'] ?? ''), 0, 300));
        }
        return $p['output'] ?? null;
    }

    private function http()
    {
        return Http::withToken(config('ai.token'))->acceptJson()->asJson()->retry(2, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false);
    }

    /** Hard daily ceiling on predictions to cap spend. */
    private function guardBudget(): void
    {
        $key = 'ai:calls:'.now()->toDateString();
        Cache::add($key, 0, now()->endOfDay());
        if (Cache::increment($key) > config('ai.daily_cap')) {
            throw new ReplicateException('Daily AI budget reached (REDSTAR_AI_DAILY_CAP).');
        }
    }

    public static function callsToday(): int { return (int) Cache::get('ai:calls:'.now()->toDateString(), 0); }
}
