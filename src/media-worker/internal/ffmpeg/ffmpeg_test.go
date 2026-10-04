package ffmpeg

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
)

func TestOnlyLosslessCodecsGetAFLACRendition(t *testing.T) {
	for codec, want := range map[string]bool{
		"flac": true, "pcm_s16le": true, "pcm_s24le": true, "pcm_f32le": true, "alac": true,
		"mp3": false, "aac": false, "vorbis": false, "opus": false, "": false,
	} {
		if got := isLosslessCodec(codec); got != want {
			t.Errorf("isLosslessCodec(%q) = %v, want %v", codec, got, want)
		}
	}
}

// Needs ffmpeg, ffprobe and metaflac, as in the worker image.
func TestEncodeLosslessWritesCDQualityFLACWithSeekPoints(t *testing.T) {
	for _, tool := range []string{"ffmpeg", "ffprobe", "metaflac"} {
		if _, err := exec.LookPath(tool); err != nil {
			t.Skipf("%s is not installed", tool)
		}
	}
	dir := t.TempDir()
	source := filepath.Join(dir, "master.wav")
	if out, err := exec.Command("ffmpeg", "-loglevel", "error", "-f", "lavfi", "-i", "sine=frequency=440:duration=25:sample_rate=96000",
		"-ac", "2", "-c:a", "pcm_s24le", source).CombinedOutput(); err != nil {
		t.Fatalf("make source: %s", out)
	}
	processor := New(dir)

	lossless, err := processor.IsLossless(source)
	if err != nil || !lossless {
		t.Fatalf("a 24-bit WAV is lossless, got %v %v", lossless, err)
	}
	rendition, err := processor.EncodeLossless(source, "work", "track-1")
	if err != nil {
		t.Fatal(err)
	}
	if rendition.FileKey != "processed/track-1/lossless.flac" || rendition.Format != "flac" || rendition.BitrateKbps <= 0 {
		t.Fatalf("unexpected rendition %#v", rendition)
	}

	out, err := exec.Command("ffprobe", "-v", "error", "-show_entries", "stream=codec_name,sample_rate,sample_fmt,channels",
		"-of", "default=noprint_wrappers=1", rendition.LocalPath).Output()
	if err != nil {
		t.Fatal(err)
	}
	if got := string(out); got != "codec_name=flac\nsample_fmt=s16\nsample_rate=44100\nchannels=2\n" {
		t.Fatalf("expected 16-bit/44.1 kHz stereo FLAC, got %q", got)
	}
	seekTable, err := exec.Command("metaflac", "--list", "--block-type=SEEKTABLE", rendition.LocalPath).Output()
	if err != nil || len(seekTable) == 0 {
		t.Fatalf("expected a seek table, got %q %v", seekTable, err)
	}
	if _, err := os.Stat(filepath.Join(dir, "work", "lossless.flac")); err != nil {
		t.Fatal(err)
	}
}

// Needs ffmpeg and ffprobe, as in the worker image.
func TestMP3RenditionsAreConstantBitrate(t *testing.T) {
	for _, tool := range []string{"ffmpeg", "ffprobe"} {
		if _, err := exec.LookPath(tool); err != nil {
			t.Skipf("%s is not installed", tool)
		}
	}
	dir := t.TempDir()
	source := filepath.Join(dir, "master.wav")
	// Quiet noise: VBR would spend far fewer bits on it than 320 kbps.
	if out, err := exec.Command("ffmpeg", "-loglevel", "error", "-f", "lavfi", "-i", "anoisesrc=d=20:c=pink:r=44100:a=0.3",
		"-ac", "2", "-c:a", "pcm_s16le", source).CombinedOutput(); err != nil {
		t.Fatalf("make source: %s", out)
	}
	processor := New(dir)

	for _, bitrate := range []int{128, 320} {
		output := filepath.Join(dir, "out.mp3")
		if err := processor.encodeMP3(source, output, bitrate); err != nil {
			t.Fatal(err)
		}
		header := make([]byte, 4096)
		file, err := os.Open(output)
		if err != nil {
			t.Fatal(err)
		}
		n, _ := file.Read(header)
		file.Close()
		// LAME tags a CBR file "Info" and a VBR file "Xing".
		if !bytes.Contains(header[:n], []byte("Info")) || bytes.Contains(header[:n], []byte("Xing")) {
			t.Fatalf("%d kbps must be constant bitrate", bitrate)
		}
		out, err := exec.Command("ffprobe", "-v", "error", "-show_entries", "format=bit_rate",
			"-of", "default=noprint_wrappers=1:nokey=1", output).Output()
		if err != nil {
			t.Fatal(err)
		}
		measured, _ := strconv.Atoi(strings.TrimSpace(string(out)))
		if measured < bitrate*980 || measured > bitrate*1020 {
			t.Fatalf("%d kbps rendition measured %d bit/s", bitrate, measured)
		}
	}
}
