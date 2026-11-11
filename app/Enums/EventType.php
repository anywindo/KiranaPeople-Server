<?php

namespace App\Enums;

enum EventType: string
{
    case START_SHIFT = 'START_SHIFT';
    case START_BREAK = 'START_BREAK';
    case END_BREAK = 'END_BREAK';
    case END_SHIFT = 'END_SHIFT';

    public function label(): string
    {
        return match ($this) {
            self::START_SHIFT => 'Start Shift',
            self::START_BREAK => 'Start Break',
            self::END_BREAK => 'End Break',
            self::END_SHIFT => 'End Shift',
        };
    }
}
