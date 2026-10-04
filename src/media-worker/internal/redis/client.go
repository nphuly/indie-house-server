package redis

import (
	"context"
	"encoding/json"
	"fmt"
	// 	"log"
	"time"

	"github.com/redis/go-redis/v9"
	"github.com/nphuly/indie-house-server/src/media-worker/internal/config"
)

type Client struct {
	client *redis.Client
	queue  string
}

type AudioJob struct {
	Kind       string `json:"kind,omitempty"`
	TrackID    string `json:"track_id"`
	RawFileKey string `json:"raw_file_key"`
	Title      string `json:"title"`
	ArtistID   int    `json:"artist_id"`
	Attempt    int    `json:"attempt"`
}

func New(cfg *config.Config) *Client {
	rdb := redis.NewClient(&redis.Options{
		Addr:     fmt.Sprintf("%s:%s", cfg.RedisHost, cfg.RedisPort),
		Password: cfg.RedisPassword,
		DB:       cfg.RedisDB,
	})

	return &Client{
		client: rdb,
		queue:  cfg.RedisQueue,
	}
}

func (c *Client) Ping(ctx context.Context) error {
	return c.client.Ping(ctx).Err()
}

func (c *Client) Close() error {
	return c.client.Close()
}

// BRPOP blocks until a job is available
func (c *Client) BPopJob(ctx context.Context, timeout time.Duration) (*AudioJob, error) {
	result, err := c.client.BRPop(ctx, timeout, c.queue).Result()
	if err != nil {
		if err == redis.Nil {
			return nil, nil // No job available
		}
		return nil, err
	}

	// result[0] = queue name, result[1] = payload
	if len(result) < 2 {
		return nil, fmt.Errorf("invalid BRPOP result")
	}

	var job AudioJob
	if err := json.Unmarshal([]byte(result[1]), &job); err != nil {
		return nil, fmt.Errorf("unmarshal job: %w", err)
	}

	return &job, nil
}

// Push job back to queue for retry
func (c *Client) PushJob(ctx context.Context, job *AudioJob) error {
	job.Attempt++
	payload, err := json.Marshal(job)
	if err != nil {
		return err
	}
	return c.client.LPush(ctx, c.queue, payload).Err()
}

// Publish progress (optional — for real-time admin dashboard)
func (c *Client) PublishProgress(ctx context.Context, trackID string, status string, message string) error {
	payload, _ := json.Marshal(map[string]string{
		"track_id": trackID,
		"status":   status,
		"message":  message,
	})
	return c.client.Publish(ctx, "audio-progress", payload).Err()
}
