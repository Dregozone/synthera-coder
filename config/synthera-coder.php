<?php

$defaultModel = env('LM_STUDIO_MODEL', 'qwen/qwen3.5-9b');

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Models
    |--------------------------------------------------------------------------
    |
    | Each entry maps a UI-visible model label (stored per session as
    | `current_model`) to the AI provider, the concrete model identifier sent
    | to that provider, and generation limits. Adding a model is config-only:
    | no agent class is required. The label is used verbatim in the UI picker.
    |
    */
    'models' => [
        $defaultModel => [
            'provider' => 'lmstudio',
            'model' => $defaultModel,
            'context_window' => (int) env('LM_STUDIO_CONTEXT_WINDOW', 8192),
            'max_steps' => (int) env('LM_STUDIO_MAX_STEPS', 12),
            'timeout' => (int) env('LM_STUDIO_TIMEOUT', 300),
        ],
    ],

    // Derived flat list of labels for the UI model picker.
    'chat_models' => [$defaultModel],

    'default_chat_model' => $defaultModel,

    'chat_types' => [
        'ask' => 'question-mark-circle',
        'plan' => 'list-bullet',
        'agent' => 'rocket-launch',
    ],

    'default_chat_type' => 'agent',
];
