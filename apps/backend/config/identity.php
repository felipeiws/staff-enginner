<?php

declare(strict_types=1);

return [
    'machine_token_ttl_minutes' => (int) env('IDENTITY_MACHINE_TOKEN_TTL_MINUTES', 15),
];
