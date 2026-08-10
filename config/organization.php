<?php

return [
    'connection' => 'organization',
    'required' => env('ORG_DB_REQUIRED', true),
    'jit_auth' => env('ORG_JIT_AUTH', false),
    'workspace_public_id' => env('ORG_WORKSPACE_PUBLIC_ID'),
    'it_department_code' => env('ORG_IT_DEPARTMENT_CODE', 'ITD'),
];
