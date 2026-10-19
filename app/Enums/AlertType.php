<?php

namespace App\Enums;

enum AlertType: string
{
    case VolumeDrop = 'volume_drop';
    case ProcessingFailures = 'processing_failures';
    case ProcessingStalled = 'processing_stalled';

    /**
     * A short human-readable name for the alert type.
     */
    public function label(): string
    {
        return match ($this) {
            self::VolumeDrop => 'Traffic drop',
            self::ProcessingFailures => 'Processing failures',
            self::ProcessingStalled => 'Processing stalled',
        };
    }
}
