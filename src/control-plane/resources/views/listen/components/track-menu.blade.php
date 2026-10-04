@use('App\Support\Icon')
@php
    $editablePlaylist ??= null;
@endphp

<details class="track-menu" data-track-menu>
    <summary class="icon-button icon-button--sm track-menu__toggle" aria-label="{{ __('listen.track.options_for', ['title' => $track->title]) }}" title="{{ __('listen.track.options') }}">{{ Icon::svg('more') }}</summary>
    <div class="menu-panel track-menu__panel">
        @auth
            <p class="menu-label">{{ __('listen.track.add_to_playlist') }}</p>
            @if ($userPlaylists->isNotEmpty())
                <form class="track-menu__playlists" action="{{ route('playlists.tracks.store', $userPlaylists->first()) }}" method="post">
                    @csrf
                    <input type="hidden" name="track_id" value="{{ $track->public_id }}">
                    @foreach ($userPlaylists as $playlist)
                        <button class="menu-item" type="submit" formaction="{{ route('playlists.tracks.store', $playlist) }}">{{ Icon::svg('note') }}<span>{{ $playlist->title }}</span></button>
                    @endforeach
                </form>
            @endif
            <a class="menu-item" href="{{ route('library.index') }}#create-playlist">{{ Icon::svg('plus') }}<span>{{ __('listen.track.new_playlist') }}</span></a>
        @else
            <a class="menu-item" href="{{ route('login', ['intended' => url()->full()]) }}">{{ Icon::svg('plus') }}<span>{{ __('listen.track.login_to_save') }}</span></a>
        @endauth
        <hr class="menu-divider">
        @if ($payload['albumUrl'])
            <a class="menu-item" href="{{ $payload['albumUrl'] }}">{{ Icon::svg('disc') }}<span>{{ __('listen.track.go_to_album') }}</span></a>
        @endif
        <a class="menu-item" href="{{ $payload['artistUrl'] }}">{{ Icon::svg('mic') }}<span>{{ __('listen.track.go_to_artist') }}</span></a>
        @if ($editablePlaylist)
            <hr class="menu-divider">
            <form action="{{ route('playlists.tracks.destroy', [$editablePlaylist, $track]) }}" method="post">
                @csrf
                @method('DELETE')
                <button class="menu-item menu-item--danger" type="submit">{{ Icon::svg('trash') }}<span>{{ __('listen.track.remove_from_playlist') }}</span></button>
            </form>
        @endif
    </div>
</details>
