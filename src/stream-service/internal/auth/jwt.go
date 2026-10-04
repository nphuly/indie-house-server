package auth

import (
	"crypto"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"
)

var ErrInvalidToken = errors.New("invalid bearer token")

// ErrExpiredToken is a genuine access token past its exp. It wraps
// ErrInvalidToken, so callers that only need to reject still match it; the
// access log tells the two apart.
var ErrExpiredToken = fmt.Errorf("%w: expired", ErrInvalidToken)

type Claims struct {
	Subject   string          `json:"sub"`
	Issuer    string          `json:"iss"`
	Audience  json.RawMessage `json:"aud"`
	ExpiresAt int64           `json:"exp"`
	NotBefore int64           `json:"nbf"`
	TokenUse  string          `json:"token_use"`
	High      bool            `json:"hq"`
	// Access tokens: an admin granted this listener the FLAC rendition.
	Lossless bool `json:"lossless"`
	// Playback tickets only: the one track and the quality the control plane allowed.
	Track   string `json:"trk"`
	Quality string `json:"q"`
}

var (
	ticketTrack   = regexp.MustCompile(`^[A-Za-z0-9]{12}$`)
	ticketQuality = map[string]bool{"auto": true, "standard": true, "high": true, "lossless": true, "preview": true}
)

type Validator struct {
	publicKeys map[string]*rsa.PublicKey
	legacyKid  string
	issuer     string
	audience   string
}

func NewValidator(publicKeyPath, issuer, audience string) (*Validator, error) {
	return NewValidatorWithKeys(publicKeyPath, "", "stream-v1", issuer, audience)
}

// NewValidatorWithKeys loads the original public key and any staged keys named
// <kid>.public.pem. Both old and new keys remain valid during rotation.
func NewValidatorWithKeys(publicKeyPath, keysDirectory, legacyKid, issuer, audience string) (*Validator, error) {
	if !validKid(legacyKid) {
		return nil, errors.New("invalid legacy JWT key id")
	}
	legacyKey, err := readPublicKey(publicKeyPath)
	if err != nil {
		return nil, err
	}
	keys := map[string]*rsa.PublicKey{legacyKid: legacyKey}
	if keysDirectory != "" {
		info, err := os.Stat(keysDirectory)
		if err != nil || !info.IsDir() {
			return nil, fmt.Errorf("JWT public keys directory is unavailable: %s", keysDirectory)
		}
		files, err := filepath.Glob(filepath.Join(keysDirectory, "*.public.pem"))
		if err != nil {
			return nil, fmt.Errorf("list JWT public keys: %w", err)
		}
		for _, path := range files {
			kid := strings.TrimSuffix(filepath.Base(path), ".public.pem")
			if !validKid(kid) || kid == legacyKid {
				return nil, fmt.Errorf("invalid or duplicate JWT key id %q", kid)
			}
			key, err := readPublicKey(path)
			if err != nil {
				return nil, err
			}
			keys[kid] = key
		}
	}
	return &Validator{publicKeys: keys, legacyKid: legacyKid, issuer: issuer, audience: audience}, nil
}

var kidPattern = regexp.MustCompile(`^[a-zA-Z0-9._-]{1,120}$`)

func validKid(kid string) bool { return kidPattern.MatchString(kid) }

func readPublicKey(publicKeyPath string) (*rsa.PublicKey, error) {
	pemBytes, err := os.ReadFile(publicKeyPath)
	if err != nil {
		return nil, fmt.Errorf("read JWT public key: %w", err)
	}
	block, _ := pem.Decode(pemBytes)
	if block == nil {
		return nil, errors.New("decode JWT public key PEM")
	}

	key, err := x509.ParsePKIXPublicKey(block.Bytes)
	if err != nil {
		key, err = x509.ParsePKCS1PublicKey(block.Bytes)
	}
	if err != nil {
		return nil, fmt.Errorf("parse JWT public key: %w", err)
	}
	publicKey, ok := key.(*rsa.PublicKey)
	if !ok {
		return nil, errors.New("JWT public key must be RSA")
	}

	return publicKey, nil
}

func (v *Validator) ValidateAuthorization(header string, now time.Time) (Claims, error) {
	if !strings.HasPrefix(header, "Bearer ") {
		return Claims{}, ErrInvalidToken
	}
	return v.Validate(strings.TrimSpace(strings.TrimPrefix(header, "Bearer ")), now)
}

// Validate accepts a mobile access token.
func (v *Validator) Validate(token string, now time.Time) (Claims, error) {
	return v.validate(token, "access", now)
}

// ValidatePlayback accepts a playback ticket: a short-lived token the control
// plane signs for one track at one quality, carried in the audio URL so that
// browsers and players that cannot send a bearer header can stream.
func (v *Validator) ValidatePlayback(token string, now time.Time) (Claims, error) {
	claims, err := v.validate(token, "playback", now)
	if err != nil {
		return Claims{}, err
	}
	if !ticketTrack.MatchString(claims.Track) || !ticketQuality[claims.Quality] {
		return Claims{}, ErrInvalidToken
	}
	return claims, nil
}

// validate accepts only compact, RSA-SHA256 signed JWTs of the given use. The
// service has the public key only; token issuing and private-key custody
// remain in the control plane/identity provider.
func (v *Validator) validate(token, use string, now time.Time) (Claims, error) {
	parts := strings.Split(token, ".")
	if len(parts) != 3 {
		return Claims{}, ErrInvalidToken
	}

	headerBytes, err := base64.RawURLEncoding.DecodeString(parts[0])
	if err != nil {
		return Claims{}, ErrInvalidToken
	}
	var header struct {
		Algorithm string `json:"alg"`
		KeyID     string `json:"kid"`
	}
	if json.Unmarshal(headerBytes, &header) != nil || header.Algorithm != "RS256" {
		return Claims{}, ErrInvalidToken
	}
	// Tokens issued before kid support are accepted only with the legacy key.
	if header.KeyID == "" {
		header.KeyID = v.legacyKid
	}
	publicKey := v.publicKeys[header.KeyID]
	if publicKey == nil {
		return Claims{}, ErrInvalidToken
	}

	payload, err := base64.RawURLEncoding.DecodeString(parts[1])
	if err != nil {
		return Claims{}, ErrInvalidToken
	}
	var claims Claims
	if json.Unmarshal(payload, &claims) != nil {
		return Claims{}, ErrInvalidToken
	}

	signature, err := base64.RawURLEncoding.DecodeString(parts[2])
	if err != nil {
		return Claims{}, ErrInvalidToken
	}
	digest := sha256.Sum256([]byte(parts[0] + "." + parts[1]))
	if rsa.VerifyPKCS1v15(publicKey, crypto.SHA256, digest[:], signature) != nil {
		return Claims{}, ErrInvalidToken
	}

	if claims.Subject == "" || claims.TokenUse != use || claims.Issuer != v.issuer || !claims.hasAudience(v.audience) || (claims.NotBefore > 0 && claims.NotBefore > now.Unix()) {
		return Claims{}, ErrInvalidToken
	}
	if claims.ExpiresAt <= now.Unix() {
		return Claims{}, ErrExpiredToken
	}

	return claims, nil
}

func (c Claims) hasAudience(expected string) bool {
	var single string
	if json.Unmarshal(c.Audience, &single) == nil {
		return single == expected
	}
	var multiple []string
	if json.Unmarshal(c.Audience, &multiple) != nil {
		return false
	}
	for _, audience := range multiple {
		if audience == expected {
			return true
		}
	}
	return false
}
