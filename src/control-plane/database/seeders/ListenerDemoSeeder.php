<?php

namespace Database\Seeders;

use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use App\Models\Media\TrackRendition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * A small, self-contained catalogue for a new local installation.
 *
 * The previews are generated tones, not third-party music. They make the
 * listener UI, playback and listening-history flow usable before an editor
 * uploads the first real release through Filament.
 */
class ListenerDemoSeeder extends Seeder
{
    /** @var array<int, array{name: string, slug: string, bio: string, album: array{title: string, slug: string, description: string}, tracks: array<int, array{title: string, slug: string, genre: string, frequency: int}>}> */
    private const CATALOGUE = [
        [
            'name' => 'Mây Đen',
            'slug' => 'may-den',
            'bio' => 'Những lớp guitar chậm và đèn neon còn sáng sau chuyến xe cuối.',
            'album' => [
                'title' => 'Đèn cuối phố',
                'slug' => 'den-cuoi-pho',
                'description' => 'Ba đoạn băng nhỏ dành cho những con đường chưa ngủ.',
            ],
            'tracks' => [
                ['title' => 'Cửa sổ mờ sương', 'slug' => 'cua-so-mo-suong', 'genre' => 'Indie', 'frequency' => 220],
                ['title' => 'Vệt đèn vàng', 'slug' => 'vet-den-vang', 'genre' => 'Dream pop', 'frequency' => 247],
                ['title' => 'Băng qua cầu', 'slug' => 'bang-qua-cau', 'genre' => 'Indie', 'frequency' => 262],
            ],
        ],
        [
            'name' => 'Nox An',
            'slug' => 'nox-an',
            'bio' => 'Synth tối giản, nhịp máy mềm và vài tín hiệu từ tầng thượng.',
            'album' => [
                'title' => 'Tín hiệu 02:17',
                'slug' => 'tin-hieu-0217',
                'description' => 'Một bản đồ âm thanh cho giờ thành phố chuyển màu.',
            ],
            'tracks' => [
                ['title' => 'Night signal', 'slug' => 'night-signal', 'genre' => 'Electronic', 'frequency' => 294],
                ['title' => 'Mạch điện mềm', 'slug' => 'mach-dien-mem', 'genre' => 'Ambient', 'frequency' => 330],
                ['title' => 'Không lời lúc 3 giờ', 'slug' => 'khong-loi-luc-3-gio', 'genre' => 'Electronic', 'frequency' => 349],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOGUE as $artistData) {
            $artist = Artist::query()->updateOrCreate(
                ['slug' => $artistData['slug']],
                [
                    'name' => $artistData['name'],
                    'bio' => $artistData['bio'],
                ],
            );

            $album = Album::query()->updateOrCreate(
                ['artist_id' => $artist->id, 'slug' => $artistData['album']['slug']],
                [
                    'title' => $artistData['album']['title'],
                    'description' => $artistData['album']['description'],
                ],
            );

            foreach ($artistData['tracks'] as $position => $trackData) {
                $track = Track::query()->updateOrCreate(
                    ['artist_id' => $artist->id, 'slug' => $trackData['slug']],
                    [
                        'title' => $trackData['title'],
                        'duration_seconds' => 14,
                        'genre' => $trackData['genre'],
                        'status' => 'completed',
                        'is_published' => true,
                        'play_count' => 0,
                    ],
                );

                $album->tracks()->syncWithoutDetaching([$track->id => ['sort_order' => $position + 1]]);

                $fileKey = "catalogue-demo/{$trackData['slug']}.wav";

                if (! Storage::disk('public')->exists($fileKey)) {
                    Storage::disk('public')->put($fileKey, $this->makePreviewTone($trackData['frequency']));
                }

                TrackRendition::query()->updateOrCreate(
                    ['track_id' => $track->id, 'file_key' => $fileKey],
                    [
                        'quality_label' => 'demo_wav',
                        'format' => 'wav',
                        'bitrate_kbps' => 352,
                        'file_size_bytes' => Storage::disk('public')->size($fileKey) ?? 0,
                    ],
                );
            }
        }
    }

    private function makePreviewTone(int $frequency): string
    {
        $sampleRate = 22_050;
        $seconds = 14;
        $sampleCount = $sampleRate * $seconds;
        $samples = '';

        for ($sample = 0; $sample < $sampleCount; $sample++) {
            $time = $sample / $sampleRate;
            $envelope = min(1, $time * 3, ($seconds - $time) * 2);
            $value = (int) (9_000 * $envelope * (sin(2 * M_PI * $frequency * $time) + (0.25 * sin(2 * M_PI * ($frequency / 2) * $time))));
            $samples .= pack('v', $value & 0xFFFF);
        }

        $dataLength = strlen($samples);

        return 'RIFF'.pack('V', 36 + $dataLength).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16).'data'.pack('V', $dataLength).$samples;
    }
}
