<?php

return [

    /*
     * Where operational alerts go: failed background jobs, long queue waits (Horizon).
     */
    'ops_alert_email' => env('OPS_ALERT_EMAIL'),

    /*
     * Emails allowed to open the Horizon dashboard at /horizon (comma-separated).
     */
    'horizon_admins' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('HORIZON_ADMINS', '')),
    ))),

];
