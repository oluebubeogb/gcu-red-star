<?php
namespace App\Jobs;

use App\Ai\Ai;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Editor's assistant: spam/abuse score, suggested headline, proofreading suggestions. Suggestions only — never applied. */
class ReviewSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $submissionId) {}

    public function handle(Ai $ai): void
    {
        $s = Submission::find($this->submissionId);
        if (! $s || ! $ai->available('assistant')) return;
        $j = $ai->completeJson(
            "Submission to The Red Star (school magazine of Government College Umuahia).\nType: {$s->kind}\nTitle: {$s->title}\nText:\n".mb_substr((string) $s->body, 0, 6000)
            ."\n\nReturn JSON: {\"spam_score\": 0.0-1.0 (spam, advertising, abuse, hate or harassment), \"flags\": [\"short reasons\"], \"headline\": \"a better headline\", \"suggestions\": [\"up to 6 concrete proofreading/clarity suggestions, quoting the original phrase\"]}",
            'You assist the student editorial team. Be kind, specific and brief.', 700);
        $s->forceFill(['ai_review' => [
            'spam_score' => max(0, min(1, (float) ($j['spam_score'] ?? 0))),
            'flags' => array_slice(array_map('strval', (array) ($j['flags'] ?? [])), 0, 5),
            'headline' => mb_substr((string) ($j['headline'] ?? ''), 0, 180),
            'suggestions' => array_slice(array_map('strval', (array) ($j['suggestions'] ?? [])), 0, 6),
        ], 'ai_reviewed_at' => now()])->save();
    }
}
