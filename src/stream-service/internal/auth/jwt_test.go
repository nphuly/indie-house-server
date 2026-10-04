package auth

import (
	"crypto"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"errors"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestValidatorAcceptsOnlyAValidRS256Token(t *testing.T) {
	privateKey, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	publicKeyPath := filepath.Join(t.TempDir(), "public.pem")
	publicDER, err := x509.MarshalPKIXPublicKey(&privateKey.PublicKey)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(publicKeyPath, pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: publicDER}), 0600); err != nil {
		t.Fatal(err)
	}

	validator, err := NewValidator(publicKeyPath, "https://identity.indie-house.test", "indie-house-mobile")
	if err != nil {
		t.Fatal(err)
	}
	now := time.Unix(1_700_000_000, 0)
	token := signedToken(t, privateKey, map[string]any{
		"sub":       "user-42",
		"iss":       "https://identity.indie-house.test",
		"aud":       "indie-house-mobile",
		"exp":       now.Add(time.Minute).Unix(),
		"nbf":       now.Add(-time.Minute).Unix(),
		"token_use": "access",
		"hq":        true,
	})

	claims, err := validator.ValidateAuthorization("Bearer "+token, now)
	if err != nil {
		t.Fatalf("expected valid token, got %v", err)
	}
	if claims.Subject != "user-42" || !claims.High {
		t.Fatalf("unexpected claims: %#v", claims)
	}

	if _, err := validator.Validate(token+"x", now); err == nil {
		t.Fatal("a tampered signature must be rejected")
	}

	refreshToken := signedToken(t, privateKey, map[string]any{
		"sub":       "user-42",
		"iss":       "https://identity.indie-house.test",
		"aud":       "indie-house-mobile",
		"exp":       now.Add(time.Minute).Unix(),
		"token_use": "refresh",
	})
	if _, err := validator.Validate(refreshToken, now); err == nil {
		t.Fatal("a non-access token must be rejected")
	}

	_, err = validator.Validate(token, now.Add(2*time.Minute))
	if !errors.Is(err, ErrExpiredToken) || !errors.Is(err, ErrInvalidToken) {
		t.Fatalf("an expired token must be rejected as expired, got %v", err)
	}
	if _, err := validator.Validate(token+"x", now.Add(2*time.Minute)); errors.Is(err, ErrExpiredToken) {
		t.Fatal("a tampered token must not be reported as merely expired")
	}

	if _, err := validator.ValidatePlayback(token, now); err == nil {
		t.Fatal("an access token must not work as a playback ticket")
	}
}

func TestPlaybackTicketNamesOneTrackAndQuality(t *testing.T) {
	privateKey, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	publicKeyPath := filepath.Join(t.TempDir(), "public.pem")
	publicDER, err := x509.MarshalPKIXPublicKey(&privateKey.PublicKey)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(publicKeyPath, pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: publicDER}), 0600); err != nil {
		t.Fatal(err)
	}
	validator, err := NewValidator(publicKeyPath, "https://identity.indie-house.test", "indie-house-mobile")
	if err != nil {
		t.Fatal(err)
	}
	now := time.Unix(1_700_000_000, 0)
	ticket := func(track, quality string) string {
		return signedToken(t, privateKey, map[string]any{
			"sub": "guest:abc", "iss": "https://identity.indie-house.test", "aud": "indie-house-mobile",
			"exp": now.Add(time.Hour).Unix(), "token_use": "playback", "trk": track, "q": quality,
		})
	}

	claims, err := validator.ValidatePlayback(ticket("AbcDef123456", "preview"), now)
	if err != nil || claims.Track != "AbcDef123456" || claims.Quality != "preview" || claims.Subject != "guest:abc" {
		t.Fatalf("expected a valid ticket, got %#v, %v", claims, err)
	}
	if _, err := validator.Validate(ticket("AbcDef123456", "preview"), now); err == nil {
		t.Fatal("a playback ticket must not work as a bearer access token")
	}
	for _, bad := range [][2]string{{"../secret", "high"}, {"AbcDef123456", "lossless-please"}, {"", "auto"}} {
		if _, err := validator.ValidatePlayback(ticket(bad[0], bad[1]), now); err == nil {
			t.Fatalf("ticket for %q at %q must be rejected", bad[0], bad[1])
		}
	}
}

func TestValidatorAcceptsStagedKeyByKidAndRejectsUnknownKey(t *testing.T) {
	legacy, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	staged, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		t.Fatal(err)
	}
	dir := t.TempDir()
	writePublicKey := func(name string, key *rsa.PrivateKey) {
		t.Helper()
		der, err := x509.MarshalPKIXPublicKey(&key.PublicKey)
		if err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(dir, name), pem.EncodeToMemory(&pem.Block{Type: "PUBLIC KEY", Bytes: der}), 0600); err != nil {
			t.Fatal(err)
		}
	}
	writePublicKey("public.key", legacy)
	writePublicKey("stream-v2.public.pem", staged)
	validator, err := NewValidatorWithKeys(filepath.Join(dir, "public.key"), dir, "stream-v1", "https://identity.indie-house.test", "indie-house-mobile")
	if err != nil {
		t.Fatal(err)
	}
	now := time.Unix(1_700_000_000, 0)
	claims := map[string]any{"sub": "42", "iss": "https://identity.indie-house.test", "aud": "indie-house-mobile", "exp": now.Add(time.Minute).Unix(), "token_use": "access"}
	for _, tc := range []struct {
		kid      string
		key      *rsa.PrivateKey
		accepted bool
	}{
		{"stream-v1", legacy, true},
		{"stream-v2", staged, true},
		{"missing", staged, false},
		{"stream-v1", staged, false},
	} {
		token := signedTokenWithKid(t, tc.key, tc.kid, claims)
		_, err := validator.Validate(token, now)
		if (err == nil) != tc.accepted {
			t.Fatalf("kid %q accepted=%v, error=%v", tc.kid, tc.accepted, err)
		}
	}
}

func signedToken(t *testing.T, privateKey *rsa.PrivateKey, claims map[string]any) string {
	return signedTokenWithKid(t, privateKey, "", claims)
}

func signedTokenWithKid(t *testing.T, privateKey *rsa.PrivateKey, kid string, claims map[string]any) string {
	t.Helper()
	header, err := json.Marshal(map[string]string{"alg": "RS256", "typ": "JWT", "kid": kid})
	if err != nil {
		t.Fatal(err)
	}
	payload, err := json.Marshal(claims)
	if err != nil {
		t.Fatal(err)
	}
	signingInput := base64.RawURLEncoding.EncodeToString(header) + "." + base64.RawURLEncoding.EncodeToString(payload)
	digest := sha256.Sum256([]byte(signingInput))
	signature, err := rsa.SignPKCS1v15(rand.Reader, privateKey, crypto.SHA256, digest[:])
	if err != nil {
		t.Fatal(err)
	}
	return signingInput + "." + base64.RawURLEncoding.EncodeToString(signature)
}
