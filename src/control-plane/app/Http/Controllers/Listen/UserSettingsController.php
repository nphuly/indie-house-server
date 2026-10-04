<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Auth\MobileSession;
use App\Services\Auth\MobileTokenService;
use App\Services\Media\PlaybackRenditions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserSettingsController extends Controller
{
    public function show(Request $request, PlaybackRenditions $renditions): View
    {
        $sessions = $request->user()->mobileSessions()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('last_used_at')
            ->get();

        $preferences = $request->user()->preference;
        $losslessAvailable = in_array('lossless', $renditions->choicesFor($request->user()), true);

        return view('listen.settings', compact('sessions', 'preferences', 'losslessAvailable'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->getKey())],
            'current_password' => [Rule::requiredIf(fn (): bool => $request->input('email') !== $user->email), 'nullable', 'current_password'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $oldAvatar = $user->avatar_path;
        if ($request->hasFile('avatar')) {
            // Profile pictures live in the private bucket, like artwork, so they
            // survive deploys and reach the apps through the mobile API.
            $path = $request->file('avatar')->store("avatars/{$user->getKey()}", 's3');
            if ($path === false) {
                return back()->withInput()->withErrors(['avatar' => __('settings.avatar_not_saved')]);
            }
            $user->avatar_path = $path;
        }
        $user->name = $attributes['name'];
        if ($user->email !== $attributes['email']) {
            $user->email = $attributes['email'];
            $user->email_verified_at = null;
        }
        $user->save();

        if ($oldAvatar && $oldAvatar !== $user->avatar_path) {
            Storage::disk('s3')->delete($oldAvatar);
        }

        return back()->with('status', __('settings.profile_saved'));
    }

    public function updatePlayback(Request $request, PlaybackRenditions $renditions): RedirectResponse|JsonResponse
    {
        $attributes = $request->validate([
            'audio_quality' => ['required', Rule::in($renditions->choicesFor($request->user()))],
            'autoplay' => ['required', 'boolean'],
        ]);

        $request->user()->preference()->updateOrCreate([], $attributes);

        if ($request->expectsJson()) {
            return response()->json(['data' => $attributes]);
        }

        return back()->with('status', __('settings.playback_saved'));
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'record_listening_history' => ['required', 'boolean'],
        ]);

        $request->user()->preference()->updateOrCreate([], $attributes);

        return back()->with('status', __('settings.privacy_saved'));
    }

    public function updatePassword(Request $request, MobileTokenService $tokens): RedirectResponse
    {
        $attributes = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($attributes['password'])])->save();
        $tokens->revokeAllForUser($user, 'password_changed', $user);

        return back()->with('status', __('settings.password_changed'));
    }

    public function revokeDevice(Request $request, MobileSession $session, MobileTokenService $tokens): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->getKey(), 404);

        $tokens->revokeSession($session, 'user_revoke', $request->user());

        return back()->with('status', __('settings.device_revoked'));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        return response()->streamDownload(function () use ($user): void {
            echo '{"account":'.json_encode([
                'name' => $user->name,
                'email' => $user->email,
                // A signed link that expires after 30 minutes.
                'avatar_url' => $user->avatarUrl(),
                'created_at' => $user->created_at?->toIso8601String(),
            ], JSON_THROW_ON_ERROR).',"preferences":'.json_encode([
                'audio_quality' => $user->preference?->audio_quality ?? 'auto',
                'autoplay' => $user->preference?->autoplay ?? true,
                'record_listening_history' => $user->preference?->record_listening_history ?? true,
            ], JSON_THROW_ON_ERROR).',"playlists":[';

            $first = true;
            foreach ($user->playlists()->with('tracks')->lazyById(100) as $playlist) {
                if (! $first) {
                    echo ',';
                }
                echo json_encode([
                    'id' => $playlist->public_id,
                    'title' => $playlist->title,
                    'description' => $playlist->description,
                    'is_public' => $playlist->is_public,
                    'tracks' => $playlist->tracks->pluck('public_id')->all(),
                ], JSON_THROW_ON_ERROR);
                $first = false;
            }
            echo '],"listening_history":[';

            $first = true;
            foreach ($user->playLogs()->with('track')->lazyById(100) as $log) {
                if (! $first) {
                    echo ',';
                }
                echo json_encode([
                    'track_id' => $log->track?->public_id,
                    'played_duration_seconds' => $log->played_duration_seconds,
                    'completed' => $log->completed,
                    'created_at' => $log->created_at?->toIso8601String(),
                ], JSON_THROW_ON_ERROR);
                $first = false;
            }
            echo ']}';
        }, 'indie-house-account-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ]);
    }
}
