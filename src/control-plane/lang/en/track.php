<?php

return [
    'general' => [
        'model_label' => 'Track',
        'title' => 'Track title',
        'slug' => 'Slug',
        'public_id' => 'Public ID',
        'genre' => 'Genre',
        'duration' => 'Duration',
        'lyrics' => 'Lyrics',
        'status' => 'Status',
        'last_error_message' => 'Latest error',
        'processing_error_help' => 'The audio could not be processed. Check the source file and try processing it again.',
        'is_published' => 'Published?',
        'play_count' => 'Plays',
        'artist' => 'Artist',
        'raw_file_key' => 'Original file',
        'renditions_count' => 'Audio renditions',
        'updated_at' => 'Updated at',
    ],
    'renditions' => [
        'quality_label' => 'Quality',
        'format' => 'Format',
        'bitrate' => 'Bitrate',
        'size' => 'Size',
        'file_key' => 'File path',
    ],
    'statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'unknown' => 'Unknown',
    ],
    'actions' => [
        'preview' => 'Preview',
        'reprocess' => 'Reprocess',
        'reprocess_selected' => 'Reprocess selected',
        'reprocess_description' => 'Existing audio renditions will be removed and the track will be sent back to the media worker queue.',
        'close' => 'Close',
    ],
    'notifications' => [
        'processing_queued' => 'Track queued for processing.',
    ],
    'preview' => [
        'unavailable' => 'No preview file or temporary URL is available yet.',
        'play' => 'Play preview',
        'pause' => 'Pause preview',
        'position' => 'Preview position',
    ],
    'sections' => [
        'basic' => 'Basic information',
        'file_processing' => 'File processing',
        'renditions' => 'Audio renditions',
    ],
];
