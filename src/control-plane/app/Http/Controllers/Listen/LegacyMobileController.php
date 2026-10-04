<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Track;
use App\Services\Media\PlaybackRenditions;
use App\Services\Media\PlaybackTicketService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyMobileController extends Controller
{
    public function index(Request $request): View
    {
        if (! Auth::check()) {
            return view('legacy.login');
        }

        $query = trim((string) $request->query('q', ''));
        $tracks = Track::query()->publicCatalogue()->with('artist')
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $match) use ($query): void {
                    $match->whereLike('title', '%'.$query.'%')
                        ->orWhereHas('artist', fn (Builder $artist) => $artist->whereLike('name', '%'.$query.'%'));
                });
            })
            ->latest()->paginate(20)->withQueryString();

        return view('legacy.index', compact('tracks', 'query'));
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('legacy.invalid_credentials'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('legacy.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('legacy.index');
    }

    /** Like the web player, this redirects to the stream gateway with a playback ticket. */
    public function audio(Request $request, Track $track, PlaybackRenditions $renditions, PlaybackTicketService $tickets): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        abort_unless(Auth::check(), 401);
        abort_unless($track->is_published && $track->status === 'completed', 404);

        $demo = $track->renditions()->where('file_key', 'like', 'catalogue-demo/%')->first();
        if ($demo && Storage::disk('public')->exists($demo->file_key)) {
            return response()->file(Storage::disk('public')->path($demo->file_key), [
                'Content-Type' => str_ends_with(strtolower($demo->file_key), '.wav') ? 'audio/wav' : 'audio/mpeg',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $key = $renditions->audio($track, 'auto')?->file_key;
        abort_unless($key, 404);

        if (config('stream.web_audio_via_gateway')) {
            return redirect()->away($tickets->url($track, 'auto', PlaybackTicketService::subjectFor($request)))
                ->header('Cache-Control', 'no-store, private');
        }

        $disk = Storage::disk('s3');
        $client = $disk->getClient();
        $bucket = (string) config('filesystems.disks.s3.bucket');

        try {
            $head = $client->headObject(['Bucket' => $bucket, 'Key' => $key]);
        } catch (S3Exception) {
            abort(404);
        }
        $size = (int) $head['ContentLength'];
        abort_if($size < 1, 404);
        [$start, $end, $partial] = $this->range((string) $request->header('Range', ''), $size);

        try {
            $object = $client->getObject([
                'Bucket' => $bucket,
                'Key' => $key,
                ...($partial ? ['Range' => "bytes={$start}-{$end}"] : []),
            ]);
        } catch (S3Exception) {
            abort(502);
        }

        $body = $object['Body'];
        $length = $end - $start + 1;
        $headers = [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($body, $length): void {
            $remaining = $length;
            try {
                while ($remaining > 0 && ! $body->eof()) {
                    $chunk = $body->read(min(65536, $remaining));
                    if ($chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    flush();
                }
            } finally {
                $body->close();
            }
        }, $partial ? 206 : 200, $headers);
    }

    /** @return array{int, int, bool} */
    private function range(string $header, int $size): array
    {
        if ($header === '') {
            return [0, $size - 1, false];
        }
        if (! preg_match('/\Abytes=(\d+)-(\d*)\z/', $header, $matches)) {
            abort(416);
        }
        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        abort_if($start >= $size || $end < $start, 416);

        return [$start, $end, true];
    }
}
