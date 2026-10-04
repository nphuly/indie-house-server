package httpapi

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"path"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/nphuly/indie-house-server/src/stream-service/internal/assetcache"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/auth"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/catalogue"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/config"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/ratelimit"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/storage"
)

var publicID = regexp.MustCompile(`^[A-Za-z0-9]{12}$`)

type Server struct {
	cfg       *config.Config
	auth      *auth.Validator
	limiter   *ratelimit.Limiter
	catalogue *catalogue.Client
	storage   *storage.Client
	cache     *assetcache.Cache
	log       *slog.Logger
}

func New(cfg *config.Config, validator *auth.Validator, limiter *ratelimit.Limiter, resolver *catalogue.Client, objectStorage *storage.Client, cache *assetcache.Cache, logger *slog.Logger) *Server {
	return &Server{cfg: cfg, auth: validator, limiter: limiter, catalogue: resolver, storage: objectStorage, cache: cache, log: logger}
}

func (s *Server) ServeHTTP(writer http.ResponseWriter, request *http.Request) {
	if request.URL.Path == "/healthz" {
		writeJSON(writer, http.StatusOK, map[string]string{"status": "ok"})
		return
	}

	entry := &accessEntry{started: time.Now(), client: clientName(request.UserAgent())}
	writer = &recorder{ResponseWriter: writer, entry: entry}
	defer s.logAccess(entry)

	if request.Method != http.MethodGet {
		writeJSON(writer, http.StatusMethodNotAllowed, map[string]string{"error": "method not allowed"})
		return
	}

	if ticket, ok := strings.CutPrefix(request.URL.Path, "/v1/play/"); ok {
		entry.op = "play"
		s.handlePlay(writer, request, ticket, entry)
		return
	}

	trackID, operation, hlsAsset, ok := parseRoute(request.URL.Path)
	if !ok {
		writeJSON(writer, http.StatusNotFound, map[string]string{"error": "not found"})
		return
	}
	entry.op, entry.track = operation, trackID

	claims, ok := s.authenticateAndLimit(writer, request, operation, entry)
	if !ok {
		return
	}
	switch operation {
	case "stream":
		s.handleStream(writer, request, trackID, claims, entry)
	case "presign":
		s.handlePresign(writer, request, trackID, claims, entry)
	case "hls":
		s.handleHLS(writer, request, trackID, hlsAsset, entry)
	}
}

func (s *Server) authenticateAndLimit(writer http.ResponseWriter, request *http.Request, operation string, entry *accessEntry) (auth.Claims, bool) {
	claims, err := s.auth.ValidateAuthorization(request.Header.Get("Authorization"), time.Now())
	if err != nil {
		entry.failure = "invalid_token"
		if errors.Is(err, auth.ErrExpiredToken) {
			entry.failure = "expired_token"
		}
		writeJSON(writer, http.StatusUnauthorized, map[string]string{"error": "invalid or expired bearer token"})
		return auth.Claims{}, false
	}
	entry.user = claims.Subject

	limit := s.cfg.StreamRequestsPerMinute
	if operation == "presign" {
		limit = s.cfg.PresignRequestsPerMinute
	} else if operation == "hls" {
		limit = s.cfg.HLSRequestsPerMinute
	}
	if !s.allow(writer, request, operation, claims.Subject, limit, entry) {
		return auth.Claims{}, false
	}
	return claims, true
}

func (s *Server) allow(writer http.ResponseWriter, request *http.Request, operation, subject string, limit int, entry *accessEntry) bool {
	started := time.Now()
	allowed, err := s.limiter.Allow(request.Context(), operation, subject, limit)
	entry.limit = time.Since(started)
	if err != nil {
		entry.fail(request, "rate_limiter", err)
		writeJSON(writer, http.StatusServiceUnavailable, map[string]string{"error": "rate limiter temporarily unavailable"})
		return false
	}
	if !allowed {
		entry.failure = "rate_limited"
		writer.Header().Set("Retry-After", "60")
		writeJSON(writer, http.StatusTooManyRequests, map[string]string{"error": "rate limit exceeded"})
		return false
	}
	return true
}

// handlePlay streams the track a playback ticket names, at the quality the
// control plane chose when it signed the ticket. Browsers use it: the ticket
// rides in the URL because an <audio> element cannot send a bearer header.
func (s *Server) handlePlay(writer http.ResponseWriter, request *http.Request, ticket string, entry *accessEntry) {
	if ticket == "" || len(ticket) > 4096 || strings.Contains(ticket, "/") {
		writeJSON(writer, http.StatusNotFound, map[string]string{"error": "not found"})
		return
	}
	claims, err := s.auth.ValidatePlayback(ticket, time.Now())
	if err != nil {
		entry.failure = "invalid_ticket"
		if errors.Is(err, auth.ErrExpiredToken) {
			entry.failure = "expired_ticket"
		}
		writeJSON(writer, http.StatusUnauthorized, map[string]string{"error": "invalid or expired playback ticket"})
		return
	}
	entry.user, entry.track, entry.quality = claims.Subject, claims.Track, claims.Quality
	if !s.allow(writer, request, "play", claims.Subject, s.cfg.StreamRequestsPerMinute, entry) {
		return
	}
	s.serveAsset(writer, request, entry, assetcache.Key{Track: claims.Track, Kind: "audio", Quality: claims.Quality})
}

func (s *Server) handleStream(writer http.ResponseWriter, request *http.Request, trackID string, claims auth.Claims, entry *accessEntry) {
	quality, ok := s.parseQuality(writer, request, claims, entry)
	if !ok {
		return
	}
	key := assetcache.Key{Track: trackID, Kind: "audio", Quality: quality}

	if request.URL.Query().Get("delivery") != "redirect" {
		s.serveAsset(writer, request, entry, key)
		return
	}
	cached, _, ok := s.resolve(writer, request, entry, key)
	if !ok {
		return
	}
	url, err := s.storage.Presign(request.Context(), cached.Asset.Key, s.cfg.PresignTTL)
	if err != nil {
		entry.fail(request, "presign", err)
		writeJSON(writer, http.StatusBadGateway, map[string]string{"error": "audio storage unavailable"})
		return
	}
	http.Redirect(writer, request, url, http.StatusFound)
}

func (s *Server) handlePresign(writer http.ResponseWriter, request *http.Request, trackID string, claims auth.Claims, entry *accessEntry) {
	quality, ok := s.parseQuality(writer, request, claims, entry)
	if !ok {
		return
	}
	cached, _, ok := s.resolve(writer, request, entry, assetcache.Key{Track: trackID, Kind: "audio", Quality: quality})
	if !ok {
		return
	}
	asset := cached.Asset
	url, err := s.storage.Presign(request.Context(), asset.Key, s.cfg.PresignTTL)
	if err != nil {
		entry.fail(request, "presign", err)
		writeJSON(writer, http.StatusBadGateway, map[string]string{"error": "audio storage unavailable"})
		return
	}
	writer.Header().Set("Cache-Control", "no-store")
	writeJSON(writer, http.StatusOK, map[string]string{
		"url":        url,
		"quality":    asset.Quality,
		"expires_at": time.Now().Add(s.cfg.PresignTTL).UTC().Format(time.RFC3339),
	})
}

func (s *Server) handleHLS(writer http.ResponseWriter, request *http.Request, trackID, hlsAsset string, entry *accessEntry) {
	s.serveAsset(writer, request, entry, assetcache.Key{Track: trackID, Kind: "hls", Asset: hlsAsset})
}

// resolve asks the control plane which object a request maps to, unless the
// answer is cached. The epoch goes back to the cache with anything learnt later.
func (s *Server) resolve(writer http.ResponseWriter, request *http.Request, entry *accessEntry, key assetcache.Key) (assetcache.Entry, uint64, bool) {
	cached, epoch, hit := s.cache.Get(key)
	if hit {
		entry.cache = "hit"
		entry.served = cached.Asset.Quality
		return cached, epoch, true
	}
	entry.cache = "miss"
	started := time.Now()
	asset, err := s.catalogue.Resolve(request.Context(), key.Track, key.Kind, key.Quality, key.Asset)
	entry.resolve = time.Since(started)
	if err != nil {
		if errors.Is(err, catalogue.ErrNotFound) {
			entry.failure = "not_found"
			writeJSON(writer, http.StatusNotFound, map[string]string{"error": "track or rendition not found"})
		} else {
			entry.fail(request, "catalogue", err)
			writeJSON(writer, http.StatusBadGateway, map[string]string{"error": "catalogue unavailable"})
		}
		return assetcache.Entry{}, 0, false
	}
	entry.served = asset.Quality
	cached = assetcache.Entry{Asset: asset}
	s.cache.Put(key, cached, epoch)
	return cached, epoch, true
}

func (s *Server) serveAsset(writer http.ResponseWriter, request *http.Request, entry *accessEntry, key assetcache.Key) {
	cached, epoch, ok := s.resolve(writer, request, entry, key)
	if !ok {
		return
	}
	if cached.Size == 0 {
		started := time.Now()
		size, err := s.storage.Size(request.Context(), cached.Asset.Key)
		entry.stat = time.Since(started)
		if err != nil {
			entry.fail(request, "storage_stat", err)
			writeJSON(writer, http.StatusBadGateway, map[string]string{"error": "audio storage unavailable"})
			return
		}
		cached.Size = size
		s.cache.Put(key, cached, epoch)
	}
	s.proxy(writer, request, cached.Asset, cached.Size, entry)
}

func (s *Server) proxy(writer http.ResponseWriter, request *http.Request, asset catalogue.Asset, size int64, entry *accessEntry) {

	start, end, partial, err := parseRange(request.Header.Get("Range"), size)
	if err != nil {
		entry.failure = "bad_range"
		writer.Header().Set("Content-Range", fmt.Sprintf("bytes */%d", size))
		writeJSON(writer, http.StatusRequestedRangeNotSatisfiable, map[string]string{"error": "invalid range"})
		return
	}
	entry.partial, entry.offset = partial, start

	object, err := s.storage.Open(request.Context(), asset.Key, start, end)
	if err != nil {
		entry.fail(request, "storage_open", err)
		writeJSON(writer, http.StatusBadGateway, map[string]string{"error": "audio storage unavailable"})
		return
	}
	defer object.Reader.Close()

	length := end - start + 1
	writer.Header().Set("Accept-Ranges", "bytes")
	writer.Header().Set("Content-Type", asset.ContentType)
	writer.Header().Set("Content-Length", strconv.FormatInt(length, 10))
	writer.Header().Set("Cache-Control", "private, max-age=60")
	writer.Header().Set("X-Content-Type-Options", "nosniff")
	if partial {
		writer.Header().Set("Content-Range", fmt.Sprintf("bytes %d-%d/%d", start, end, size))
		writer.WriteHeader(http.StatusPartialContent)
	}
	entry.copyStarted = time.Now()
	if _, err := io.CopyN(writer, object.Reader, length); err != nil {
		entry.fail(request, "copy", err)
	}
}

func parseRoute(requestPath string) (trackID, operation, hlsAsset string, ok bool) {
	const prefix = "/v1/tracks/"
	if !strings.HasPrefix(requestPath, prefix) {
		return "", "", "", false
	}
	parts := strings.Split(strings.TrimPrefix(requestPath, prefix), "/")
	if len(parts) < 2 || !publicID.MatchString(parts[0]) {
		return "", "", "", false
	}
	switch {
	case len(parts) == 2 && parts[1] == "stream":
		return parts[0], "stream", "", true
	case len(parts) == 2 && parts[1] == "presigned-url":
		return parts[0], "presign", "", true
	case len(parts) == 3 && parts[1] == "hls" && isSafeHLSAsset(parts[2]):
		return parts[0], "hls", parts[2], true
	default:
		return "", "", "", false
	}
}

// parseQuality returns the quality to serve and logs the one asked for.
// Lossless needs the account's lossless grant; without it the best MP3 plays
// instead, so a revoked grant never stops playback.
func (s *Server) parseQuality(writer http.ResponseWriter, request *http.Request, claims auth.Claims, entry *accessEntry) (string, bool) {
	quality := request.URL.Query().Get("quality")
	if quality == "" {
		quality = "auto"
	}
	if quality != "auto" && quality != "standard" && quality != "high" && quality != "lossless" {
		writeJSON(writer, http.StatusBadRequest, map[string]string{"error": "quality must be auto, standard, high, or lossless"})
		return "", false
	}
	entry.quality = quality
	if quality == "lossless" && !claims.Lossless {
		quality = "high"
	}
	if s.cfg.RequireHQClaim && quality == "high" && !claims.High {
		entry.failure = "hq_not_allowed"
		writeJSON(writer, http.StatusForbidden, map[string]string{"error": "high quality is not available for this account"})
		return "", false
	}
	return quality, true
}

func isSafeHLSAsset(asset string) bool {
	extension := path.Ext(asset)
	return path.Base(asset) == asset && (extension == ".m3u8" || extension == ".ts" || extension == ".aac" || extension == ".m4s")
}

func parseRange(header string, size int64) (start, end int64, partial bool, err error) {
	if size <= 0 {
		return 0, 0, false, errors.New("empty object")
	}
	if header == "" {
		return 0, size - 1, false, nil
	}
	if !strings.HasPrefix(header, "bytes=") || strings.Contains(header, ",") {
		return 0, 0, false, errors.New("unsupported range")
	}
	value := strings.TrimPrefix(header, "bytes=")
	parts := strings.Split(value, "-")
	if len(parts) != 2 {
		return 0, 0, false, errors.New("invalid range")
	}
	if parts[0] == "" {
		suffix, parseErr := strconv.ParseInt(parts[1], 10, 64)
		if parseErr != nil || suffix <= 0 {
			return 0, 0, false, errors.New("invalid suffix range")
		}
		if suffix > size {
			suffix = size
		}
		return size - suffix, size - 1, true, nil
	}
	start, err = strconv.ParseInt(parts[0], 10, 64)
	if err != nil || start < 0 || start >= size {
		return 0, 0, false, errors.New("invalid start range")
	}
	end = size - 1
	if parts[1] != "" {
		end, err = strconv.ParseInt(parts[1], 10, 64)
		if err != nil || end < start {
			return 0, 0, false, errors.New("invalid end range")
		}
		if end >= size {
			end = size - 1
		}
	}
	return start, end, true, nil
}

func writeJSON(writer http.ResponseWriter, status int, body any) {
	writer.Header().Set("Content-Type", "application/json; charset=utf-8")
	writer.WriteHeader(status)
	_ = json.NewEncoder(writer).Encode(body)
}
