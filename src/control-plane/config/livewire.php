<?php

return [
    'temporary_file_upload' => [
        // Upload audio directly from the browser to object storage. Each
        // repeater item has one file, which is supported by Livewire S3 uploads.
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 's3'),
        'directory' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DIRECTORY', 'livewire-tmp'),
        'rules' => ['required', 'file', 'max:524288'],

        // Large lossless audio files and batches can take longer than Livewire's
        // five-minute default signed URL lifetime.
        'max_upload_time' => (int) env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_MAX_UPLOAD_TIME', 30),
        'middleware' => 'throttle:240,1',
        'cleanup' => true,
    ],
];
