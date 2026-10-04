<?php

return [
    'model_label' => 'Artist',
    'name' => 'Name',
    'slug' => 'Slug',
    'public_id' => 'Public ID',
    'bio' => 'Biography',
    'avatar' => 'Avatar',
    'avatar_hint' => 'Square, ideally 1000×1000 px or larger. Bigger images are scaled down to 1200 px.',
    'cover_image' => 'Cover image',
    'tabs' => [
        'popular_tracks' => 'Popular tracks',
        'tracks' => 'All tracks',
        'albums' => 'Albums',
        'playlists' => 'Playlists',
        'bio' => 'Biography',
    ],
    'empty' => [
        'tracks' => 'This artist has no tracks yet.',
        'albums' => 'This artist has no albums yet.',
        'playlists' => 'No public playlist has a track by this artist yet.',
    ],
    'playlists' => [
        'title' => 'Public playlist',
        'artist_tracks' => 'Tracks by this artist',
        'tracks' => 'Total tracks',
    ],
];
