package httpapi

import (
	"context"
	"log/slog"
	"math"
	"net/http"
	"time"
)

// accessEntry becomes one log line per audio request. It records where the
// time went (rate limiter, catalogue, storage) so a slow or failed play can be
// traced to one hop. It never holds the bearer token, a storage key or a
// presigned URL.
type accessEntry struct {
	started time.Time
	op      string
	track   string
	user    string
	client  string
	quality string // what the client asked for
	served  string // the rendition the catalogue picked
	partial bool
	offset  int64
	status  int
	bytes   int64
	failure string
	cache   string // "hit" or "miss" once the catalogue was needed

	limit, resolve, stat time.Duration
	copyStarted          time.Time
	firstByte            time.Time
}

// fail records why a request stopped. A listener who skips, seeks or closes
// the app hangs up mid-request; that is normal traffic, not a server fault.
func (e *accessEntry) fail(request *http.Request, stage string, err error) {
	if request.Context().Err() != nil {
		e.failure = "client_closed"
		return
	}
	e.failure = stage + ": " + err.Error()
}

// recorder notes the status, body size and first body byte for the log.
type recorder struct {
	http.ResponseWriter
	entry *accessEntry
}

func (r *recorder) WriteHeader(status int) {
	if r.entry.status == 0 {
		r.entry.status = status
	}
	r.ResponseWriter.WriteHeader(status)
}

func (r *recorder) Write(body []byte) (int, error) {
	if r.entry.status == 0 {
		r.entry.status = http.StatusOK
	}
	if r.entry.firstByte.IsZero() {
		r.entry.firstByte = time.Now()
	}
	written, err := r.ResponseWriter.Write(body)
	r.entry.bytes += int64(written)
	return written, err
}

func (r *recorder) Unwrap() http.ResponseWriter {
	return r.ResponseWriter
}

func (s *Server) logAccess(entry *accessEntry) {
	if entry.status == 0 {
		entry.status = http.StatusOK
	}
	level := slog.LevelInfo
	switch {
	case entry.status >= 500:
		level = slog.LevelError
	case entry.status >= 400, entry.failure != "" && entry.failure != "client_closed":
		level = slog.LevelWarn
	}

	attrs := []slog.Attr{
		slog.Int("status", entry.status),
		slog.Int64("bytes", entry.bytes),
		millis("total_ms", time.Since(entry.started)),
	}
	for _, field := range []struct{ key, value string }{
		{"op", entry.op}, {"track", entry.track}, {"user", entry.user}, {"client", entry.client},
		{"quality", entry.quality}, {"served", entry.served}, {"failure", entry.failure}, {"cache", entry.cache},
	} {
		if field.value != "" {
			attrs = append(attrs, slog.String(field.key, field.value))
		}
	}
	if entry.partial {
		attrs = append(attrs, slog.Int64("offset", entry.offset))
	}
	for _, phase := range []struct {
		key      string
		duration time.Duration
	}{
		{"limit_ms", entry.limit}, {"resolve_ms", entry.resolve}, {"stat_ms", entry.stat},
	} {
		if phase.duration > 0 {
			attrs = append(attrs, millis(phase.key, phase.duration))
		}
	}
	if !entry.firstByte.IsZero() {
		attrs = append(attrs, millis("first_byte_ms", entry.firstByte.Sub(entry.started)))
		// Storage GETs start lazily, so the wait for MinIO's first bytes
		// shows up between starting the copy and the first body write.
		if !entry.copyStarted.IsZero() && entry.firstByte.After(entry.copyStarted) {
			attrs = append(attrs, millis("get_ms", entry.firstByte.Sub(entry.copyStarted)))
		}
	}
	s.log.LogAttrs(context.Background(), level, "audio request", attrs...)
}

func millis(key string, duration time.Duration) slog.Attr {
	return slog.Float64(key, math.Round(float64(duration)/float64(time.Millisecond)*10)/10)
}

// clientName keeps enough of the User-Agent to tell the iOS app, the Leap's
// media player and browsers apart.
func clientName(userAgent string) string {
	if len(userAgent) > 100 {
		return userAgent[:100]
	}
	return userAgent
}
