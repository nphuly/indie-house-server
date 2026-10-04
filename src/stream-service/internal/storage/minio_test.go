package storage

import (
	"context"
	"fmt"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"sync"
	"testing"
	"time"
)

// fakeS3 answers bucket-location lookups and HEAD requests, and remembers
// which object paths it was asked for.
type fakeS3 struct {
	*httptest.Server
	mu      sync.Mutex
	objects []string
}

func newFakeS3(t *testing.T) *fakeS3 {
	fake := &fakeS3{}
	fake.Server = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Query().Has("location") {
			fmt.Fprint(w, `<?xml version="1.0" encoding="UTF-8"?><LocationConstraint xmlns="http://s3.amazonaws.com/doc/2006-03-01/"></LocationConstraint>`)
			return
		}
		fake.mu.Lock()
		fake.objects = append(fake.objects, r.Method+" "+r.URL.Path)
		fake.mu.Unlock()
		w.Header().Set("Content-Length", "1234")
		w.Header().Set("ETag", `"etag"`)
		w.Header().Set("Last-Modified", time.Now().UTC().Format(http.TimeFormat))
	}))
	t.Cleanup(fake.Close)
	return fake
}

func (f *fakeS3) host() string { return strings.TrimPrefix(f.URL, "http://") }

func (f *fakeS3) requests() []string {
	f.mu.Lock()
	defer f.mu.Unlock()
	return append([]string(nil), f.objects...)
}

func TestReadsUseTheNearEndpointAndURLsAreSignedForThePublicOne(t *testing.T) {
	lan, public := newFakeS3(t), newFakeS3(t)
	client, err := New(Endpoint{Host: lan.host()}, Endpoint{Host: public.host()}, "key", "secret", "indie-house")
	if err != nil {
		t.Fatal(err)
	}

	size, err := client.Size(context.Background(), "processed/abc/mp3_320.mp3")
	if err != nil || size != 1234 {
		t.Fatalf("size=%d err=%v", size, err)
	}
	signed, err := client.Presign(context.Background(), "processed/abc/mp3_320.mp3", time.Minute)
	if err != nil {
		t.Fatal(err)
	}

	parsed, err := url.Parse(signed)
	if err != nil || parsed.Host != public.host() {
		t.Fatalf("presigned URL must point at the public endpoint, got %q", signed)
	}
	if got := lan.requests(); len(got) != 1 || got[0] != "HEAD /indie-house/processed/abc/mp3_320.mp3" {
		t.Fatalf("reads must go to the near endpoint, got %v", got)
	}
	if got := public.requests(); len(got) != 0 {
		t.Fatalf("no object request may take the public route, got %v", got)
	}
}

func TestWithoutAPublicEndpointURLsAreSignedForTheReadEndpoint(t *testing.T) {
	only := newFakeS3(t)
	client, err := New(Endpoint{Host: only.host()}, Endpoint{}, "key", "secret", "indie-house")
	if err != nil {
		t.Fatal(err)
	}
	signed, err := client.Presign(context.Background(), "processed/abc/mp3_128.mp3", time.Minute)
	if err != nil {
		t.Fatal(err)
	}
	if parsed, _ := url.Parse(signed); parsed.Host != only.host() {
		t.Fatalf("got %q", signed)
	}
}
