package httpapi

import (
	"bytes"
	"context"
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"errors"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/nphuly/indie-house-server/src/stream-service/internal/auth"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/config"
)

func TestAccessLogNamesAnExpiredTokenWithoutWritingIt(t *testing.T) {
	privateKey, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	der, err := x509.MarshalPKIXPublicKey(&privateKey.PublicKey)
	if err != nil {
		t.Fatal(err)
	}
	keyPath := filepath.Join(t.TempDir(), "public.pem")
	if err := os.WriteFile(keyPath, pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: der}), 0600); err != nil {
		t.Fatal(err)
	}
	validator, err := auth.NewValidator(keyPath, "https://indie-house.test", "indie-house-mobile")
	if err != nil {
		t.Fatal(err)
	}
	token := signToken(t, privateKey, map[string]any{
		"sub": "42", "iss": "https://indie-house.test", "aud": "indie-house-mobile",
		"exp": time.Now().Add(-time.Minute).Unix(), "token_use": "access",
	})

	var logs bytes.Buffer
	// The request is rejected before the rate limiter, catalogue or storage are used.
	server := New(&config.Config{}, validator, nil, nil, nil, nil, slog.New(slog.NewJSONHandler(&logs, nil)))
	request := httptest.NewRequest(http.MethodGet, "/v1/tracks/AbcDef123456/stream?quality=high", nil)
	request.Header.Set("Authorization", "Bearer "+token)
	request.Header.Set("User-Agent", "stagefright/1.2 (Linux;Android 4.3)")
	response := httptest.NewRecorder()
	server.ServeHTTP(response, request)

	if response.Code != http.StatusUnauthorized {
		t.Fatalf("expected 401, got %d", response.Code)
	}
	var line map[string]any
	if err := json.Unmarshal(logs.Bytes(), &line); err != nil {
		t.Fatalf("expected one JSON log line, got %q: %v", logs.String(), err)
	}
	for key, want := range map[string]any{
		"level": "WARN", "status": float64(401), "op": "stream", "track": "AbcDef123456",
		"failure": "expired_token", "client": "stagefright/1.2 (Linux;Android 4.3)",
	} {
		if line[key] != want {
			t.Errorf("log %s = %v, want %v", key, line[key], want)
		}
	}
	if strings.Contains(logs.String(), token) {
		t.Fatal("the bearer token must never reach the log")
	}
}

func TestAccessLogTreatsAHangUpAsNormalTraffic(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	entry := &accessEntry{}
	entry.fail(httptest.NewRequest(http.MethodGet, "/", nil).WithContext(ctx), "copy", errors.New("write: broken pipe"))
	if entry.failure != "client_closed" {
		t.Fatalf("a listener who hung up must not look like a server fault, got %q", entry.failure)
	}

	entry.fail(httptest.NewRequest(http.MethodGet, "/", nil), "storage_stat", errors.New("connection refused"))
	if entry.failure != "storage_stat: connection refused" {
		t.Fatalf("unexpected failure %q", entry.failure)
	}
}

func signToken(t *testing.T, privateKey *rsa.PrivateKey, claims map[string]any) string {
	t.Helper()
	header, _ := json.Marshal(map[string]string{"alg": "RS256", "typ": "JWT"})
	payload, _ := json.Marshal(claims)
	input := base64.RawURLEncoding.EncodeToString(header) + "." + base64.RawURLEncoding.EncodeToString(payload)
	digest := sha256.Sum256([]byte(input))
	signature, err := rsa.SignPKCS1v15(rand.Reader, privateKey, crypto.SHA256, digest[:])
	if err != nil {
		t.Fatal(err)
	}
	return input + "." + base64.RawURLEncoding.EncodeToString(signature)
}

func TestParseRange(t *testing.T) {
	tests := []struct {
		name                    string
		header                  string
		wantStart, wantEnd      int64
		wantPartial, shouldFail bool
	}{
		{name: "whole object", wantStart: 0, wantEnd: 99},
		{name: "bounded range", header: "bytes=10-49", wantStart: 10, wantEnd: 49, wantPartial: true},
		{name: "open range", header: "bytes=90-", wantStart: 90, wantEnd: 99, wantPartial: true},
		{name: "suffix range", header: "bytes=-20", wantStart: 80, wantEnd: 99, wantPartial: true},
		{name: "multiple ranges rejected", header: "bytes=0-1,3-4", shouldFail: true},
		{name: "past end rejected", header: "bytes=100-", shouldFail: true},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			start, end, partial, err := parseRange(test.header, 100)
			if test.shouldFail {
				if err == nil {
					t.Fatal("expected range parser to fail")
				}
				return
			}
			if err != nil || start != test.wantStart || end != test.wantEnd || partial != test.wantPartial {
				t.Fatalf("got start=%d end=%d partial=%t err=%v", start, end, partial, err)
			}
		})
	}
}

func TestParseRouteRejectsTraversalAndAcceptsSupportedHLSAsset(t *testing.T) {
	trackID, operation, asset, ok := parseRoute("/v1/tracks/AbcDef123456/hls/128k_001.ts")
	if !ok || trackID != "AbcDef123456" || operation != "hls" || asset != "128k_001.ts" {
		t.Fatalf("unexpected route parsing result: %q %q %q %t", trackID, operation, asset, ok)
	}
	if _, _, _, ok := parseRoute("/v1/tracks/AbcDef123456/hls/../secrets.ts"); ok {
		t.Fatal("path traversal must not be routable")
	}
}

func TestLosslessNeedsTheGrantAndOtherwisePlaysTheBestMP3(t *testing.T) {
	server := &Server{cfg: &config.Config{}}
	for _, test := range []struct {
		asked string
		claim auth.Claims
		want  string
	}{
		{"lossless", auth.Claims{Lossless: true}, "lossless"},
		{"lossless", auth.Claims{}, "high"},
		{"", auth.Claims{}, "auto"},
		{"standard", auth.Claims{Lossless: true}, "standard"},
	} {
		entry := &accessEntry{}
		request := httptest.NewRequest(http.MethodGet, "/v1/tracks/AbcDef123456/stream?quality="+test.asked, nil)
		got, ok := server.parseQuality(httptest.NewRecorder(), request, test.claim, entry)
		if !ok || got != test.want {
			t.Errorf("asked %q with %+v: served %q (ok=%v), want %q", test.asked, test.claim, got, ok, test.want)
		}
	}

	server.cfg.RequireHQClaim = true
	if _, ok := server.parseQuality(httptest.NewRecorder(), httptest.NewRequest(http.MethodGet, "/?quality=lossless", nil), auth.Claims{}, &accessEntry{}); ok {
		t.Fatal("without the lossless grant, lossless falls back to high, which still needs the hq claim")
	}
}
