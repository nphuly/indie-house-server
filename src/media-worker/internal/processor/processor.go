package processor

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"path"
	"path/filepath"
	"regexp"
	"strings"

	"github.com/nphuly/indie-house-server/src/media-worker/internal/config"
	"github.com/nphuly/indie-house-server/src/media-worker/internal/ffmpeg"
	minioClient "github.com/nphuly/indie-house-server/src/media-worker/internal/minio"
	redisClient "github.com/nphuly/indie-house-server/src/media-worker/internal/redis"
)

type Processor struct {
	cfg    *config.Config
	redis  *redisClient.Client
	minio  *minioClient.Client
	ffmpeg *ffmpeg.Processor
}

func New(cfg *config.Config, redis *redisClient.Client, minio *minioClient.Client) *Processor {
	return &Processor{
		cfg:    cfg,
		redis:  redis,
		minio:  minio,
		ffmpeg: ffmpeg.New(cfg.TmpDir),
	}
}

func (p *Processor) Process(ctx context.Context, job *redisClient.AudioJob) error {
	if err := validateJob(job); err != nil {
		return err
	}
	if job.Kind == "preview_backfill" {
		return p.ProcessPreview(ctx, job)
	}
	if job.Kind == "lossless_backfill" {
		return p.ProcessLossless(ctx, job)
	}
	if job.Kind != "" && job.Kind != "full" {
		return fmt.Errorf("unsupported audio job kind: %s", job.Kind)
	}
	trackID := job.TrackID
	rawKey := job.RawFileKey

	// 1. Update status: processing
	p.updateTrackStatus(ctx, trackID, "processing", "")
	p.redis.PublishProgress(ctx, trackID, "processing", "Downloading raw file")

	// 2. Download raw file
	tmpInput := filepath.Join(p.cfg.TmpDir, trackID, "input"+inputExtension(rawKey))
	if err := p.minio.Download(ctx, rawKey, tmpInput); err != nil {
		p.updateTrackStatus(ctx, trackID, "failed", err.Error())
		return fmt.Errorf("download raw: %w", err)
	}

	p.redis.PublishProgress(ctx, trackID, "processing", "Encoding audio")

	// 3. FFmpeg encode
	renditions, err := p.ffmpeg.Process(tmpInput, trackID)
	if err != nil {
		p.updateTrackStatus(ctx, trackID, "failed", err.Error())
		return fmt.Errorf("ffmpeg process: %w", err)
	}

	p.redis.PublishProgress(ctx, trackID, "processing", "Uploading to storage")

	// 4. Upload results
	for _, r := range renditions {
		if err := p.minio.Upload(ctx, r.LocalPath, r.FileKey); err != nil {
			p.updateTrackStatus(ctx, trackID, "failed", err.Error())
			return fmt.Errorf("upload %s: %w", r.QualityLabel, err)
		}
	}

	// 5. Get duration
	duration, _ := p.ffmpeg.GetDuration(tmpInput)

	// 6. Create renditions in DB
	if err := p.createRenditions(ctx, trackID, renditions, duration); err != nil {
		p.updateTrackStatus(ctx, trackID, "failed", err.Error())
		return fmt.Errorf("create renditions: %w", err)
	}

	// 7. Update status: completed
	p.updateTrackStatus(ctx, trackID, "completed", "")
	p.redis.PublishProgress(ctx, trackID, "completed", "Done")

	// 8. Cleanup
	p.ffmpeg.Cleanup(trackID)

	return nil
}

// ProcessPreview only creates the guest preview. It never changes track status
// or the existing full-length rendition records.
func (p *Processor) ProcessPreview(ctx context.Context, job *redisClient.AudioJob) error {
	workID := "preview-backfill-" + job.TrackID
	defer p.ffmpeg.Cleanup(workID)
	input := filepath.Join(p.cfg.TmpDir, workID, "input"+inputExtension(job.RawFileKey))
	if err := p.minio.Download(ctx, job.RawFileKey, input); err != nil {
		return fmt.Errorf("download preview source: %w", err)
	}
	preview, err := p.ffmpeg.EncodePreview(input, workID)
	if err != nil {
		return err
	}
	preview.FileKey = fmt.Sprintf("processed/%s/preview_30.mp3", job.TrackID)
	if err := p.minio.Upload(ctx, preview.LocalPath, preview.FileKey); err != nil {
		return fmt.Errorf("upload preview: %w", err)
	}
	return p.callDBAPI(ctx, "POST", "/tracks/preview", map[string]interface{}{
		"track_id":        job.TrackID,
		"source_file_key": job.RawFileKey,
		"file_key":        preview.FileKey,
		"size_bytes":      preview.SizeBytes,
	})
}

// ProcessLossless adds the FLAC rendition to a track processed before lossless
// existed. Like the preview backfill it never changes the track's status or
// its other renditions, and a lossy source (MP3) is skipped: there is no
// lossless audio to keep.
func (p *Processor) ProcessLossless(ctx context.Context, job *redisClient.AudioJob) error {
	workID := "lossless-backfill-" + job.TrackID
	defer p.ffmpeg.Cleanup(workID)
	input := filepath.Join(p.cfg.TmpDir, workID, "input"+inputExtension(job.RawFileKey))
	if err := p.minio.Download(ctx, job.RawFileKey, input); err != nil {
		return fmt.Errorf("download lossless source: %w", err)
	}
	lossless, err := p.ffmpeg.IsLossless(input)
	if err != nil {
		return fmt.Errorf("probe lossless source: %w", err)
	}
	if !lossless {
		log.Printf("track %s: source is not lossless, no FLAC rendition", job.TrackID)
		return nil
	}
	flac, err := p.ffmpeg.EncodeLossless(input, workID, job.TrackID)
	if err != nil {
		return err
	}
	if err := p.minio.Upload(ctx, flac.LocalPath, flac.FileKey); err != nil {
		return fmt.Errorf("upload lossless: %w", err)
	}
	return p.callDBAPI(ctx, "POST", "/tracks/lossless", map[string]interface{}{
		"track_id":        job.TrackID,
		"source_file_key": job.RawFileKey,
		"file_key":        flac.FileKey,
		"size_bytes":      flac.SizeBytes,
		"bitrate_kbps":    flac.BitrateKbps,
	})
}

func (p *Processor) updateTrackStatus(ctx context.Context, trackID, status, errMsg string) {
	payload := map[string]interface{}{
		"track_id":           trackID,
		"status":             status,
		"last_error_message": errMsg,
	}
	p.callDBAPI(ctx, "POST", "/tracks/status", payload)
}

func (p *Processor) createRenditions(ctx context.Context, trackID string, renditions []ffmpeg.Rendition, duration int) error {
	payload := map[string]interface{}{
		"track_id":         trackID,
		"duration_seconds": duration,
		"renditions":       renditions,
	}
	return p.callDBAPI(ctx, "POST", "/tracks/renditions", payload)
}

func (p *Processor) callDBAPI(ctx context.Context, method, path string, payload interface{}) error {
	url := p.cfg.DBApiURL + path
	body, _ := json.Marshal(payload)

	req, err := http.NewRequestWithContext(ctx, method, url, bytes.NewReader(body))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Internal-Key", p.cfg.InternalAPIKey) // Add auth header

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		return fmt.Errorf("db api %s returned %d", path, resp.StatusCode)
	}
	return nil
}

// trackIDPattern matches the UUIDs the control plane gives tracks. A job's
// track ID names a temporary directory that is later removed, and object
// keys, so a job from anyone else who can write to Redis must not steer them.
var trackIDPattern = regexp.MustCompile(`^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$`)

func validateJob(job *redisClient.AudioJob) error {
	if !trackIDPattern.MatchString(job.TrackID) {
		return fmt.Errorf("invalid track id %q", job.TrackID)
	}
	if job.RawFileKey == "" {
		return fmt.Errorf("track %s: missing raw file key", job.TrackID)
	}
	return nil
}

// audioExtensions are the upload types FFmpeg may pick a demuxer for by name.
// A playlist extension such as .m3u8 would let an upload read other files.
var audioExtensions = map[string]bool{
	".mp3": true, ".flac": true, ".wav": true, ".wave": true, ".aif": true, ".aiff": true,
	".m4a": true, ".aac": true, ".ogg": true, ".oga": true, ".opus": true,
}

// inputExtension keeps the raw key's audio extension for the local copy.
// Anything else, including a key without one, is detected from the content.
func inputExtension(key string) string {
	extension := strings.ToLower(path.Ext(key))
	if audioExtensions[extension] {
		return extension
	}
	return ""
}
