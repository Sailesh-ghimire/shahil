<?php

namespace App\Integrations\Meta;

use RuntimeException;

/**
 * A Graph API error. `isAuthError()` means the token is expired or revoked: the account needs reconnecting.
 */
class MetaApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $metaCode = null, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }

    public function isAuthError(): bool
    {
        return $this->metaCode === 190 || $this->httpStatus === 401;
    }

    /**
     * WhatsApp 131047 / Messenger 10: outside the 24-hour reply window.
     */
    public function isOutsideReplyWindow(): bool
    {
        return in_array($this->metaCode, [131047, 10, 2018278], true);
    }
}
