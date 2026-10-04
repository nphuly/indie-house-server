package processor

import (
	"testing"

	redisClient "github.com/nphuly/indie-house-server/src/media-worker/internal/redis"
)

func TestJobsMustNameATrackUUIDAndARawFile(t *testing.T) {
	valid := "0199a3c4-5b6e-7f80-9a1b-2c3d4e5f6a7b"
	for _, job := range []redisClient.AudioJob{
		{TrackID: "../../app", RawFileKey: "tracks/raw/a.flac"},
		{TrackID: valid + "/..", RawFileKey: "tracks/raw/a.flac"},
		{TrackID: "", RawFileKey: "tracks/raw/a.flac"},
		{TrackID: valid, RawFileKey: ""},
	} {
		if err := validateJob(&job); err == nil {
			t.Errorf("validateJob(%+v) accepted an unsafe job", job)
		}
	}
	if err := validateJob(&redisClient.AudioJob{TrackID: valid, RawFileKey: "tracks/raw/a.flac"}); err != nil {
		t.Errorf("a track UUID with a raw key is valid: %v", err)
	}
}

func TestInputKeepsOnlyAudioExtensions(t *testing.T) {
	for key, want := range map[string]string{
		"tracks/raw/id/song.FLAC":       ".flac",
		"tracks/_pending/abc.mp3":       ".mp3",
		"tracks/_pending/abc.wav":       ".wav",
		"tracks/_pending/list.m3u8":     "",
		"tracks/_pending/a.b/../../etc": "",
		"tracks/_pending/no-extension":  "",
	} {
		if got := inputExtension(key); got != want {
			t.Errorf("inputExtension(%q) = %q, want %q", key, got, want)
		}
	}
}
