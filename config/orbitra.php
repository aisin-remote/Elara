<?php

return [
    // Temporary rollout guard for the operational console. Keeping this in config makes the
    // menu and every server-side route use the same rule, and lets deployment override it.
    'system_health_email' => strtolower((string) env('ELARA_SYSTEM_HEALTH_EMAIL', 'fabian@aiia.co.id')),

    'email_verification' => (bool) env('ORBITRA_EMAIL_VERIFICATION', false),
    'max_file_upload_kb' => (int) env('ORBITRA_MAX_FILE_UPLOAD_KB', 10240),
    'bottleneck_days' => max(1, (int) env('ORBITRA_BOTTLENECK_DAYS', 7)),
    'message_edit_window_minutes' => max(1, (int) env('ORBITRA_MESSAGE_EDIT_WINDOW_MINUTES', 15)),
    // Defaults for the request pipeline. A workspace may override each of these from
    // Settings → Master data; these are what a fresh workspace starts with.
    'requests' => [
        'validation_window_days' => max(1, (int) env('ORBITRA_VALIDATION_WINDOW_DAYS', 7)),
        'pic_grace_days' => max(0, (int) env('ORBITRA_PIC_GRACE_DAYS', 10)),
        'horizon_days' => max(7, (int) env('ORBITRA_SCHEDULING_HORIZON_DAYS', 90)),
    ],

    'ai' => [
        'history_messages' => max(2, min(50, (int) env('ORBITRA_AI_HISTORY_MESSAGES', 20))),
        'max_output_tokens' => max(256, min(4000, (int) env('ORBITRA_AI_MAX_OUTPUT_TOKENS', 1500))),
        'max_tool_rounds' => max(1, min(5, (int) env('ORBITRA_AI_MAX_TOOL_ROUNDS', 3))),
    ],

    'social_links' => [
        'linkedin' => env('ORBITRA_LINKEDIN_URL'),
        'x' => env('ORBITRA_X_URL'),
        'github' => env('ORBITRA_GITHUB_URL'),
    ],
];
