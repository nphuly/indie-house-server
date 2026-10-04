package ffmpeg

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
)

type Rendition struct {
	QualityLabel string
	Format       string
	BitrateKbps  int
	FileKey      string
	LocalPath    string
	SizeBytes    int64
}

// Processor runs FFmpeg on uploaded files. Every ffmpeg and ffprobe call on
// an input passes -protocol_whitelist file, so an upload that is really a
// playlist cannot make the worker fetch URLs.
type Processor struct {
	TmpDir string
}

func New(tmpDir string) *Processor {
	return &Processor{TmpDir: tmpDir}
}

// Process encodes the preview and both MP3s, plus the lossless FLAC when the
// source is lossless. New tracks no longer get HLS (MPEG-TS cannot carry FLAC,
// and no client plays it); objects of older tracks are left as they are.
func (p *Processor) Process(inputPath, trackID string) ([]Rendition, error) {
	outputDir := filepath.Join(p.TmpDir, trackID)
	if err := os.MkdirAll(outputDir, 0755); err != nil {
		return nil, err
	}

	renditions := make([]Rendition, 0, 4)

	preview, err := p.EncodePreview(inputPath, trackID)
	if err != nil {
		return nil, fmt.Errorf("preview_30: %w", err)
	}
	renditions = append(renditions, preview)

	// 1. MP3 128kbps
	mp3_128 := filepath.Join(outputDir, "mp3_128.mp3")
	if err := p.encodeMP3(inputPath, mp3_128, 128); err != nil {
		return nil, fmt.Errorf("mp3_128: %w", err)
	}
	renditions = append(renditions, Rendition{
		QualityLabel: "mp3_128",
		Format:       "mp3",
		BitrateKbps:  128,
		FileKey:      fmt.Sprintf("processed/%s/mp3_128.mp3", trackID),
		LocalPath:    mp3_128,
	})

	// 2. MP3 320kbps
	mp3_320 := filepath.Join(outputDir, "mp3_320.mp3")
	if err := p.encodeMP3(inputPath, mp3_320, 320); err != nil {
		return nil, fmt.Errorf("mp3_320: %w", err)
	}
	renditions = append(renditions, Rendition{
		QualityLabel: "mp3_320",
		Format:       "mp3",
		BitrateKbps:  320,
		FileKey:      fmt.Sprintf("processed/%s/mp3_320.mp3", trackID),
		LocalPath:    mp3_320,
	})

	// 3. FLAC, only from a lossless source
	lossless, err := p.IsLossless(inputPath)
	if err != nil {
		return nil, fmt.Errorf("probe source: %w", err)
	}
	if lossless {
		flac, err := p.EncodeLossless(inputPath, trackID, trackID)
		if err != nil {
			return nil, fmt.Errorf("lossless: %w", err)
		}
		renditions = append(renditions, flac)
	}

	// Get file sizes
	for i := range renditions {
		if info, err := os.Stat(renditions[i].LocalPath); err == nil {
			renditions[i].SizeBytes = info.Size()
		}
	}

	return renditions, nil
}

// losslessCodecs are the source codecs a FLAC rendition may be made from.
// Anything else (MP3, AAC) would only be a larger copy of a lossy file.
var losslessCodecs = map[string]bool{"flac": true, "alac": true, "wavpack": true, "ape": true, "tta": true}

func isLosslessCodec(codec string) bool {
	return losslessCodecs[codec] || strings.HasPrefix(codec, "pcm_")
}

// IsLossless reports whether the first audio stream of input is lossless.
func (p *Processor) IsLossless(input string) (bool, error) {
	out, err := exec.Command("ffprobe", "-v", "error", "-protocol_whitelist", "file", "-select_streams", "a:0",
		"-show_entries", "stream=codec_name", "-of", "default=noprint_wrappers=1:nokey=1", input).Output()
	if err != nil {
		return false, err
	}
	return isLosslessCodec(strings.TrimSpace(string(out))), nil
}

// EncodeLossless writes the CD-quality FLAC (16-bit/44.1 kHz stereo) that
// listeners with lossless access hear. A 24-bit or 48/96 kHz master is
// resampled with soxr and dithered to 16 bits. Seek points every 10 s let
// players jump in a stream without scanning the file. workID names the
// temporary directory; the object always lives at processed/{trackID}/.
func (p *Processor) EncodeLossless(input, workID, trackID string) (Rendition, error) {
	outputDir := filepath.Join(p.TmpDir, workID)
	if err := os.MkdirAll(outputDir, 0755); err != nil {
		return Rendition{}, err
	}
	output := filepath.Join(outputDir, "lossless.flac")
	cmd := exec.Command("ffmpeg", "-y", "-protocol_whitelist", "file", "-i", input,
		"-map", "0:a:0", "-map_metadata", "-1", "-ac", "2",
		"-af", "aresample=44100:resampler=soxr:precision=28:dither_method=triangular",
		"-sample_fmt", "s16", "-c:a", "flac", "-compression_level", "8",
		output,
	)
	if out, err := cmd.CombinedOutput(); err != nil {
		return Rendition{}, fmt.Errorf("ffmpeg flac: %s: %w", string(out), err)
	}
	if out, err := exec.Command("metaflac", "--add-seekpoint=10s", output).CombinedOutput(); err != nil {
		return Rendition{}, fmt.Errorf("metaflac seek points: %s: %w", string(out), err)
	}
	info, err := os.Stat(output)
	if err != nil {
		return Rendition{}, err
	}
	rendition := Rendition{
		QualityLabel: "lossless", Format: "flac",
		FileKey:   fmt.Sprintf("processed/%s/lossless.flac", trackID),
		LocalPath: output, SizeBytes: info.Size(),
	}
	if seconds, err := p.GetDuration(output); err == nil && seconds > 0 {
		rendition.BitrateKbps = int(info.Size() * 8 / int64(seconds) / 1000)
	}
	return rendition, nil
}

// encodeMP3 writes a constant-bitrate MP3, so "320 kbps" in the apps is what
// the file is. -compression_level 0 is LAME's slowest, most careful encoder
// (-q 0). Do not add -q:a: it switches LAME to VBR and overrides -b:a, which
// once made "mp3_320" a V0 file averaging well under 320 kbps.
func (p *Processor) encodeMP3(input, output string, bitrate int) error {
	cmd := exec.Command("ffmpeg", "-y", "-protocol_whitelist", "file", "-i", input,
		"-map", "0:a:0",
		"-c:a", "libmp3lame",
		"-b:a", fmt.Sprintf("%dk", bitrate),
		"-compression_level", "0",
		"-ac", "2",
		"-ar", "44100",
		output,
	)

	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("ffmpeg: %s: %w", string(out), err)
	}
	return nil
}

func (p *Processor) GetDuration(input string) (int, error) {
	cmd := exec.Command("ffprobe", "-v", "error", "-protocol_whitelist", "file", "-show_entries", "format=duration",
		"-of", "default=noprint_wrappers=1:nokey=1", input)
	out, err := cmd.Output()
	if err != nil {
		return 0, err
	}

	var duration float64
	fmt.Sscanf(strings.TrimSpace(string(out)), "%f", &duration)
	return int(duration + 0.5), nil
}

func (p *Processor) Cleanup(trackID string) {
	dir := filepath.Join(p.TmpDir, trackID)
	os.RemoveAll(dir)
}

// EncodePreview creates a distinct, seekable 30-second MP3 for guest playback.
func (p *Processor) EncodePreview(inputPath, trackID string) (Rendition, error) {
	outputDir := filepath.Join(p.TmpDir, trackID)
	if err := os.MkdirAll(outputDir, 0755); err != nil {
		return Rendition{}, err
	}
	output := filepath.Join(outputDir, "preview_30.mp3")
	cmd := exec.Command("ffmpeg", "-y", "-protocol_whitelist", "file", "-i", inputPath,
		"-map", "0:a:0", "-t", "30", "-c:a", "libmp3lame",
		"-b:a", "96k", "-ac", "2", "-ar", "44100", output)
	if out, err := cmd.CombinedOutput(); err != nil {
		return Rendition{}, fmt.Errorf("ffmpeg preview: %s: %w", string(out), err)
	}
	info, err := os.Stat(output)
	if err != nil {
		return Rendition{}, err
	}
	return Rendition{
		QualityLabel: "preview_30", Format: "mp3", BitrateKbps: 96,
		FileKey:   fmt.Sprintf("processed/%s/preview_30.mp3", trackID),
		LocalPath: output, SizeBytes: info.Size(),
	}, nil
}
