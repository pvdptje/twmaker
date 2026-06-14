<?php

use App\Services\Assets\ProjectAssetLibrary;

return [
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:'.ProjectAssetLibrary::MAX_UPLOAD_KILOBYTES],
        'max_upload_time' => 15,
    ],
];
