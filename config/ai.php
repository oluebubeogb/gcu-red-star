<?php
// All AI runs on Replicate (https://replicate.com). Without REPLICATE_API_TOKEN every AI feature is hidden/disabled.
return [
    'token' => env('REPLICATE_API_TOKEN'),
    'base_url' => env('REPLICATE_BASE_URL', 'https://api.replicate.com/v1'),
    'enabled' => (bool) env('REDSTAR_AI_ENABLED', true),

    // Per-feature env switches (HQ → AI can additionally switch each one off).
    'features' => [
        'search' => (bool) env('REDSTAR_AI_SEARCH', true),       // semantic (embedding) search
        'ask' => (bool) env('REDSTAR_AI_ASK', true),             // Ask the archive
        'summaries' => (bool) env('REDSTAR_AI_SUMMARIES', true), // summaries + entities
        'ocr' => (bool) env('REDSTAR_AI_OCR', true),             // OCR cleanup
        'audio' => (bool) env('REDSTAR_AI_AUDIO', true),         // text-to-speech (on demand only)
        'assistant' => (bool) env('REDSTAR_AI_ASSISTANT', true), // submission review assistant
    ],
    'auto_publish' => (bool) env('REDSTAR_AI_AUTO_PUBLISH', false), // summaries/entities public without review

    // Model slugs: "owner/name" (official models) or "owner/name:version" (community models).
    'models' => [
        'llm' => env('REDSTAR_AI_LLM_MODEL', 'meta/meta-llama-3-8b-instruct'),
        'ask' => env('REDSTAR_AI_ASK_MODEL', 'meta/meta-llama-3-70b-instruct'),
        'embed' => env('REDSTAR_AI_EMBED_MODEL', 'ibm-granite/granite-embedding-278m-multilingual'),
        'tts' => env('REDSTAR_AI_TTS_MODEL', 'minimax/speech-02-turbo'),
    ],
    // Use pgvector when the extension is available on Postgres (set false to force the JSON + PHP cosine fallback).
    'pgvector' => (bool) env('REDSTAR_AI_PGVECTOR', true),
    'embed_dims' => (int) env('REDSTAR_AI_EMBED_DIMS', 768),
    'tts_voice' => env('REDSTAR_AI_TTS_VOICE', 'English_Deep-VoicedGentleman'),

    // Cost controls
    'daily_cap' => (int) env('REDSTAR_AI_DAILY_CAP', 2000),          // max Replicate predictions per day (all features)
    'ask_guest_per_hour' => (int) env('REDSTAR_AI_ASK_GUEST_PER_HOUR', 10),
    'ask_user_per_hour' => (int) env('REDSTAR_AI_ASK_USER_PER_HOUR', 30),
    'cache_hours' => (int) env('REDSTAR_AI_CACHE_HOURS', 72),         // identical questions / query embeddings
    // Semantic hits below this cosine similarity are ignored (stops unrelated pages being cited). Tune 0.2–0.5.
    'semantic_min' => (float) env('REDSTAR_AI_SEMANTIC_MIN', 0.35),
    'chunk_chars' => (int) env('REDSTAR_AI_CHUNK_CHARS', 1200),
    'timeout' => (int) env('REDSTAR_AI_TIMEOUT', 60),                // seconds per prediction (sync wait + polling)
];
