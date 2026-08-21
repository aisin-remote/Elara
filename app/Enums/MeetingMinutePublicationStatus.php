<?php

namespace App\Enums;

enum MeetingMinutePublicationStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case LOCKED = 'locked';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
