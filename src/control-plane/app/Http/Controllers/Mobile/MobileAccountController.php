<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $version = $user->avatarVersion();

        return response()->json(['data' => [
            'name' => $user->name,
            'email' => $user->email,
            // Loaded like artwork, with the Bearer token. The version changes
            // with the picture, so apps do not keep showing a replaced one.
            'avatar_url' => $version ? url("/api/v1/mobile/account/avatar/{$version}") : null,
        ]]);
    }
}
