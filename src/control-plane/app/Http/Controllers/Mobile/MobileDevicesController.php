<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Auth\MobileSession;
use App\Services\Auth\MobileTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDevicesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = $request->user()->mobileSessions()->whereNull('revoked_at')
            ->where('expires_at', '>', now())->latest('last_used_at')->get();

        return response()->json(['data' => $sessions->map(fn (MobileSession $session): array => [
            'id' => $session->id,
            'device_name' => $session->device_name,
            'platform' => $session->platform,
            'last_used_at' => $session->last_used_at?->toIso8601String(),
            'expires_at' => $session->expires_at->toIso8601String(),
        ])]);
    }

    public function revoke(Request $request, MobileSession $session, MobileTokenService $tokens): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->getKey(), 404);
        $tokens->revokeSession($session, 'user_revoke', $request->user());

        return response()->json(status: 204);
    }
}
