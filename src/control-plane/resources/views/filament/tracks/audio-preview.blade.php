@php
    $record->loadMissing(['renditions', 'artist']);

    try {
        $previewUrl = $record->preview_url;
    } catch (Throwable) {
        $previewUrl = null;
    }

    $previewKey = $record->preview_file_key;
    $artistName = $record->artist?->name;
    $artistAvatarUrl = $record->artist?->avatar_url;
    $previewMimeType = match (strtolower(pathinfo((string) $previewKey, PATHINFO_EXTENSION))) {
        'flac' => 'audio/flac',
        'wav' => 'audio/wav',
        default => 'audio/mpeg',
    };
@endphp

<div class="audio-preview">
    @if ($previewUrl)
        <div
            x-data="{
                playing: false,
                currentTime: 0,
                duration: 0,
                formatTime(seconds) {
                    if (! Number.isFinite(seconds)) return '0:00'

                    const minutes = Math.floor(seconds / 60)
                    const remainingSeconds = Math.floor(seconds % 60).toString().padStart(2, '0')

                    return `${minutes}:${remainingSeconds}`
                },
                togglePlayback() {
                    const audio = this.$refs.audio

                    if (audio.paused) {
                        audio.play()
                    } else {
                        audio.pause()
                    }
                },
                updateProgress() {
                    this.currentTime = this.$refs.audio.currentTime
                },
                seek(event) {
                    const time = Number(event.target.value)
                    this.$refs.audio.currentTime = time
                    this.currentTime = time
                },
            }"
        >
            <audio
                x-ref="audio"
                preload="metadata"
                @loadedmetadata="duration = $refs.audio.duration"
                @timeupdate="updateProgress()"
                @play="playing = true"
                @pause="playing = false"
                @ended="playing = false; currentTime = 0"
            >
                <source src="{{ $previewUrl }}" type="{{ $previewMimeType }}">
            </audio>

            <div class="audio-preview__main">
                <div class="audio-preview__artwork" aria-hidden="true">
                    @if ($artistAvatarUrl)
                        <img src="{{ $artistAvatarUrl }}" alt="">
                    @else
                        <x-filament::icon icon="heroicon-m-musical-note" />
                    @endif
                </div>

                <div class="audio-preview__copy">
                    <div class="audio-preview__title">{{ $record->title }}</div>
                    <div class="audio-preview__artist">{{ $artistName ?: __('track.general.artist') }}</div>
                </div>
            </div>

            <div class="audio-preview__controls">
                <button
                    type="button"
                    class="audio-preview__play"
                    x-on:click="togglePlayback()"
                    x-bind:aria-label="playing ? @js(__('track.preview.pause')) : @js(__('track.preview.play'))"
                >
                    <x-filament::icon x-show="! playing" icon="heroicon-m-play" />
                    <x-filament::icon x-show="playing" icon="heroicon-m-pause" x-cloak />
                </button>

                <input
                    type="range"
                    class="audio-preview__range"
                    min="0"
                    x-bind:max="duration || 0"
                    x-bind:value="currentTime"
                    x-on:input="seek($event)"
                    aria-label="{{ __('track.preview.position') }}"
                >

                <span class="audio-preview__time" x-text="`${formatTime(currentTime)} / ${formatTime(duration)}`"></span>
            </div>
        </div>

        <div class="audio-preview__footer">
            <div class="audio-preview__key" title="{{ $previewKey }}">{{ $previewKey }}</div>
        </div>
    @else
        <div class="audio-preview__footer">
            {{ __('track.preview.unavailable') }}
        </div>
    @endif
</div>
