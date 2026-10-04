<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Indie House</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <style>
        * { box-sizing: border-box; }
        html { background: #07090e; }
        body { margin: 0; background: #07090e; color: #eef1f6; font: 16px/1.45 Arial, sans-serif; }
        a { color: #f7e23b; }
        button, input { font-family: Arial, sans-serif; }
        .shell { max-width: 640px; min-height: 100vh; margin: 0 auto; padding: 20px 18px 36px; }
        .masthead { min-height: 46px; margin-bottom: 28px; }
        .brand { display: inline-block; color: #eef1f6; font-size: 21px; font-weight: bold; letter-spacing: -1px; line-height: 40px; text-transform: lowercase; }
        .brand img { width: 40px; height: 40px; margin-right: 7px; vertical-align: middle; }
        .eyebrow { margin: 0 0 9px; color: #f7e23b; font-size: 11px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        h1 { margin: 0 0 10px; font-size: 34px; line-height: 1.08; letter-spacing: -1px; }
        .intro { max-width: 35em; margin: 0 0 27px; color: #aab3c2; }
        .muted { color: #8d96a8; }
        label { display: block; margin: 17px 0 7px; font-size: 13px; font-weight: bold; color: #c8d0dc; }
        input { display: block; width: 100%; min-height: 48px; padding: 12px 15px; border: 1px solid #344054; border-radius: 2px; background: #0e1118; color: #eef1f6; font-size: 16px; }
        input:focus { outline: 2px solid #f7e23b; outline-offset: 1px; }
        button, .button { display: inline-block; min-height: 46px; padding: 11px 19px; border: 0; border-radius: 2px; background: #f7e23b; color: #07090e; font-size: 15px; font-weight: bold; line-height: 24px; text-align: center; text-decoration: none; }
        button:active, .button:active { background: #fff07a; }
        button:focus, .button:focus, a:focus { outline: 2px solid #eef1f6; outline-offset: 2px; }
        .action { width: 100%; margin-top: 24px; }
        .error { margin: 18px 0; padding: 13px 15px; border: 1px solid #a95561; border-radius: 2px; background: #2a2029; color: #ffd5d5; }
        .logout { float: right; margin-top: 3px; }
        .logout button { min-height: 36px; padding: 6px 12px; border: 1px solid #344054; background: #151a25; color: #eef1f6; font-size: 12px; line-height: 22px; }
        .search { margin: 24px 0 29px; }
        .search label { margin-bottom: 9px; }
        .search-row { position: relative; }
        .search-row input { padding-right: 98px; }
        .search-row button { position: absolute; top: 4px; right: 4px; min-height: 40px; padding: 7px 14px; border-radius: 2px; font-size: 13px; }
        .section-label { margin: 0 0 13px; color: #8d96a8; font-size: 11px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .track { overflow: hidden; margin: 0 0 12px; padding: 13px; border: 1px solid #293445; border-radius: 2px; background: #0e1118; }
        .track-art { float: left; width: 74px; height: 74px; margin-right: 14px; border-radius: 2px; background: #23200a; background: linear-gradient(135deg, #3a3510, #151a25 65%, #0e1118); text-align: center; }
        .track-art img { width: 43px; height: 43px; margin-top: 15px; }
        .track-info { min-height: 74px; padding-top: 10px; overflow: hidden; }
        .track h2 { overflow: hidden; margin: 0 0 3px; color: #eef1f6; font-size: 18px; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
        .track p { overflow: hidden; margin: 0; color: #aab3c2; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
        .track-actions { clear: both; padding-top: 13px; }
        .track-actions .button { display: block; width: 100%; }
        .browser-player { margin-top: 10px; padding: 0 2px; }
        .browser-player span { display: block; margin-bottom: 6px; color: #8d96a8; font-size: 11px; }
        audio { display: block; width: 100%; height: 32px; }
        .empty { padding: 25px 18px; border: 1px solid #293445; border-radius: 2px; background: #0e1118; color: #aab3c2; }
        .pagination { margin-top: 24px; text-align: center; color: #8d96a8; font-size: 13px; }
        .pagination a { display: inline-block; min-height: 42px; padding: 11px 13px; text-decoration: none; }
        .login-art { width: 164px; height: 164px; margin: 26px auto 31px; border: 1px solid #5c5418; border-radius: 2px; background: #23200a; background: radial-gradient(circle, #2c2710, #0e1118 70%); text-align: center; }
        .login-art img { width: 103px; height: 103px; margin-top: 30px; }
        .legal-links { margin: 36px 0 0; font-size: 13px; text-align: center; }
        .legal-links a { color: #aab3c2; }
        @media (max-width: 370px) { .shell { padding-left: 13px; padding-right: 13px; } h1 { font-size: 30px; } }
    </style>
</head>
<body>
<main class="shell">
    <header class="masthead"><div class="brand"><img src="{{ asset('images/indie-house-mark.png') }}" alt="" aria-hidden="true">indie house</div></header>
    @yield('content')
    <p class="legal-links muted"><a href="{{ route('legal.terms') }}">{{ __('legal.terms_link') }}</a> · <a href="{{ route('legal.privacy') }}">{{ __('legal.privacy_link') }}</a></p>
</main>
</body>
</html>
