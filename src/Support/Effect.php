<?php

declare(strict_types=1);

namespace RomegaSoftware\Availability\Support;

enum Effect: string
{
    case Allow = 'allow';
    case Deny = 'deny';

    public function allows(): bool
    {
        return $this === self::Allow;
    }
}
