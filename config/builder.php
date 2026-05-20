<?php

return [
    'schema_version' => 1,
    'page_version_retention' => env('BUILDER_PAGE_VERSION_RETENTION', 25),
    'generation_history_limit' => env('BUILDER_GENERATION_HISTORY_LIMIT', 500),
    'max_repair_attempts' => env('BUILDER_MAX_REPAIR_ATTEMPTS', 2),
];
