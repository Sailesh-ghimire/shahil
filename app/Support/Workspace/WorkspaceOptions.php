<?php

namespace App\Support\Workspace;

use DateTimeZone;

class WorkspaceOptions
{
    /**
     * @return array<string, string>
     */
    public static function currencies(): array
    {
        return [
            'NPR' => 'NPR — Nepalese rupee',
            'INR' => 'INR — Indian rupee',
            'USD' => 'USD — US dollar',
            'EUR' => 'EUR — Euro',
            'GBP' => 'GBP — British pound',
            'AUD' => 'AUD — Australian dollar',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function timezones(): array
    {
        $zones = DateTimeZone::listIdentifiers();

        return array_combine($zones, $zones);
    }
}
