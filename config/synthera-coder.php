<?php

return [
    'chat_models' => [
        'qwen3:8b-8k',
        'qwen3:14b-16k',
    ],
    
    'default_chat_model' => 'qwen3:8b-8k',

    'chat_types' => [
        'ask' => 'question-mark-circle',
        'plan' => 'list-bullet',
        'agent' => 'rocket-launch',
    ],

    'default_chat_type' => 'agent',
];
