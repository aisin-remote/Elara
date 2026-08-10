<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'slack' => [
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'redirect' => env('SLACK_REDIRECT_URI', '/api/internal/integrations/slack/callback'),
        'scopes' => array_values(array_filter(explode(',', env('SLACK_SCOPES', 'chat:write,channels:read')))),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/api/internal/integrations/google_drive/callback'),
        'scopes' => array_values(array_filter(explode(',', env('GOOGLE_SCOPES', 'openid,profile,email,https://www.googleapis.com/auth/drive.file')))),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/api/internal/integrations/github/callback'),
        'scopes' => array_values(array_filter(explode(',', env('GITHUB_SCOPES', 'read:user,public_repo')))),
    ],

    'zoom' => [
        'client_id' => env('ZOOM_CLIENT_ID'),
        'client_secret' => env('ZOOM_CLIENT_SECRET'),
        'redirect' => env('ZOOM_REDIRECT_URI', '/api/internal/integrations/zoom/callback'),
        'scopes' => array_values(array_filter(explode(',', env('ZOOM_SCOPES', 'user:read:user,meeting:write:meeting')))),
        'authorize_url' => 'https://zoom.us/oauth/authorize',
        'token_url' => 'https://zoom.us/oauth/token',
        'api_url' => 'https://api.zoom.us/v2',
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        // Confirmed working against the Responses API on 2026-08-03. Overridable per workspace
        // in Master data (PRD-08) so output quality can be compared without a deploy.
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
