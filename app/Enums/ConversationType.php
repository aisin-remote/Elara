<?php

namespace App\Enums;

enum ConversationType: string
{
    case DIRECT = 'direct';
    case GROUP = 'group';
    case PROJECT = 'project';
}
