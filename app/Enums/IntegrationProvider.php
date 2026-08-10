<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case SLACK = 'slack';
    case GOOGLE_DRIVE = 'google_drive';
    case GITHUB = 'github';
    case ZOOM = 'zoom';

    public function label(): string
    {
        return match ($this) {
            self::SLACK => 'Slack',
            self::GOOGLE_DRIVE => 'Google Drive',
            self::GITHUB => 'GitHub',
            self::ZOOM => 'Zoom',
        };
    }
}
