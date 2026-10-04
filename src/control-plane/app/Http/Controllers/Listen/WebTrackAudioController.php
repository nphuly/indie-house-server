<?php

namespace App\Http\Controllers\Listen;

use App\Http\Controllers\Controller;
use App\Models\Media\Track;
use App\Services\Media\PlaybackRenditions;
use App\Services\Media\PlaybackTicketService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The web player's same-origin audio URL. It checks access and picks the
 * quality, then redirects to the stream gateway with a playback ticket, so
 * the bytes never pass through PHP. The <audio> element keeps using this URL,
 * which a tap can assign without waiting for a request.
 */
class WebTrackAudioController extends Controller
{
    public function __invoke(Request $request, Track $track, PlaybackRenditions $renditions, PlaybackTicketService $tickets): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        abort_unless($track->is_published && $track->status === 'completed', 404);

        $demo = $track->renditions()->where('file_key', 'like', 'catalogue-demo/%')->first();
        if ($demo && Storage::disk('public')->exists($demo->file_key)) {
            return response()->file(Storage::disk('public')->path($demo->file_key), [
                'Content-Type' => str_ends_with(strtolower($demo->file_key), '.wav') ? 'audio/wav' : 'audio/mpeg',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $quality = $request->user()
            ? $renditions->allowedQuality($request->user(), $request->validate(['quality' => ['nullable', 'in:auto,standard,high,lossless']])['quality']
                ?? $request->user()->preference?->audio_quality ?? 'auto')
            : 'preview';
        $rendition = $renditions->audio($track, $quality);

        abort_unless($rendition, 404);

        if (config('stream.web_audio_via_gateway')) {
            // A cached redirect would replay an expired ticket.
            return redirect()->away($tickets->url($track, $quality, PlaybackTicketService::subjectFor($request)))
                ->header('Cache-Control', 'no-store, private');
        }

        $client = Storage::disk('s3')->getClient();
        $bucket = (string) config('filesystems.disks.s3.bucket');
        try {
            $head = $client->headObject(['Bucket' => $bucket, 'Key' => $rendition->file_key]);
        } catch (S3Exception) {
            abort(404);
        }
        $size = (int) $head['ContentLength'];
        abort_if($size < 1, 404);
        [$start, $end, $partial] = $this->range((string) $request->header('Range', ''), $size);

        try {
            $object = $client->getObject([
                'Bucket' => $bucket,
                'Key' => $rendition->file_key,
                ...($partial ? ['Range' => "bytes={$start}-{$end}"] : []),
            ]);
        } catch (S3Exception) {
            abort(502);
        }

        $body = $object['Body'];
        $length = $end - $start + 1;
        $headers = [
            'Content-Type' => PlaybackRenditions::contentType($rendition),
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
        if (preg_match('/\Abytes=-(\d+)\z/', $header, $suffix)) {
            $start = max(0, $size - (int) $suffix[1]);
            $end = $size - 1;
            abort_if((int) $suffix[1] < 1, 416);

            return [$start, $end, true];
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
