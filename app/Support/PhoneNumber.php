<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Phone numbers are stored in E.164 (+9779801011111) so WhatsApp senders match contacts.
 */
class PhoneNumber
{
    public const DEFAULT_REGION = 'NP';

    public static function toE164(?string $number, string $region = self::DEFAULT_REGION): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($number, $region);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isPossibleNumber($parsed)) {
            return null;
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * E.164 when the number parses, otherwise the digits (with a leading + kept) so matching still works.
     */
    public static function normalize(string $number, string $region = self::DEFAULT_REGION): string
    {
        return static::toE164($number, $region)
            ?? preg_replace('/(?!^\+)[^\d]/', '', trim($number));
    }
}
