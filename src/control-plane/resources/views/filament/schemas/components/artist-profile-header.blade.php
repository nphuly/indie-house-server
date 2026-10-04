@php
    $coverUrl = $getCoverImageUrl();
    $avatarUrl = $getAvatarUrl();
    $record = $getRecord();
@endphp

<div class="artist-profile relative rounded-xl">
    <div
        class="artist-profile__cover h-48 bg-cover bg-center rounded-t-xl"
        @if ($coverUrl) style="background-image: url('{{ $coverUrl }}')" @endif
    ></div>

    <div class="flex items-end gap-4 px-6 -mt-28 pb-6">
        <img
            src="{{ $avatarUrl }}"
            alt="{{ $record->name }}"
            class="artist-profile-avatar"
        />

        <h2 class="artist-profile__name text-xl font-bold pb-1">
            {{ $record->name }}
        </h2>
    </div>
</div>
