<?php

namespace App\Integrations\Meta\Data;

use Illuminate\Support\Carbon;

final readonly class TokenStatus
{
    public function __construct(
        public bool $isValid,
        public ?Carbon $expiresAt = null,
        public ?string $message = null,
    ) {}
}
