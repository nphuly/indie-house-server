<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class InstanceManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $instanceID = (string) config('instance.id', '');
        $baseURL = (string) config('instance.base_url', '');
        $issuer = rtrim((string) config('stream.jwt.issuer', ''), '/');
        $registrationMode = (string) config('instance.registration_mode', 'closed');

        if (! $this->isValid($instanceID, $baseURL, $issuer, $registrationMode)) {
            return response()->json([
                'message' => __('mobile_api.manifest_not_configured'),
            ], 503, ['Cache-Control' => 'no-store']);
        }

        return response()->json([
            'schema_version' => 1,
            'instance_id' => $instanceID,
            'display_name' => (string) config('instance.name'),
            'api_base' => "{$baseURL}/api/v1/mobile",
            'stream_base' => "{$baseURL}/api/v1/stream",
            'jwt_issuer' => $issuer,
            'registration_mode' => $registrationMode,
            'minimum_mobile_version' => (string) config('instance.minimum_mobile_version'),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    private function isValid(string $instanceID, string $baseURL, string $issuer, string $registrationMode): bool
    {
        $url = parse_url($baseURL);

        return Str::isUuid($instanceID)
            && is_array($url)
            && ($url['scheme'] ?? null) === 'https'
            && isset($url['host'])
            && ! isset($url['query'], $url['fragment'], $url['user'], $url['pass'])
            && $issuer === $baseURL
            && in_array($registrationMode, ['closed', 'invite_only', 'open'], true);
    }
}
