@extends('listen.layouts.app', ['title' => __("legal.{$document}.title")])

{{-- /terms and /privacy. The text lives in lang/{vi,en}/legal.php; the apps get the same through the mobile API. --}}
@section('content')
    @php
        $legal = \App\Support\LegalDocument::build($document);
        $other = $document === 'terms' ? 'privacy' : 'terms';
    @endphp

    <header class="page-head page-head--tinted">
        <h1 class="page-head__title">{{ $legal['title'] }}</h1>
        <p class="page-head__lede">{{ $legal['updated'] }}</p>
    </header>

    <article class="legal">
        <p class="legal__intro">{{ $legal['intro'] }}</p>

        @foreach ($legal['sections'] as $section)
            <section class="legal__section">
                <h2>{{ $section['title'] }}</h2>
                @foreach ($section['paragraphs'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if ($section['items'] !== [])
                    <ul>
                        @foreach ($section['items'] as $item)
                            @if ($item['label'])
                                <li><strong>{{ $item['label'] }}:</strong> {{ $item['text'] }}</li>
                            @else
                                <li>{{ $item['text'] }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        <section class="legal__section" id="contact">
            <h2>{{ $legal['contact']['title'] }}</h2>
            @if ($legal['contact']['email'])
                <p>{{ $legal['contact']['text'] }} <a href="mailto:{{ $legal['contact']['email'] }}">{{ $legal['contact']['email'] }}</a>.</p>
            @else
                <p>{{ $legal['contact']['text'] }}</p>
            @endif
        </section>

        <p class="legal__related"><a href="{{ route("legal.{$other}") }}">{{ __("legal.{$other}.title") }}</a></p>
    </article>
@endsection
