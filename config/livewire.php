<?php

use App\Services\ImageProcessor;

/*
 * Only the temporary upload settings are overridden; everything else comes from Livewire's own config.
 */
return [

    'temporary_file_upload' => [
        // Not S3: Livewire would hand the browser a presigned PUT signed for the internal Docker host
        // (`s3:9000`), which the browser cannot reach. Uploads go through PHP to the local disk and
        // Filament moves them to S3 when the form is saved.
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'local'),
        'rules' => ['required', 'file', 'max:'.(ImageProcessor::MAX_BYTES / 1024)],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
