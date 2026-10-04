package main

import (
	"context"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/nphuly/indie-house-server/src/media-worker/internal/config"
	"github.com/nphuly/indie-house-server/src/media-worker/internal/minio"
	"github.com/nphuly/indie-house-server/src/media-worker/internal/processor"
	"github.com/nphuly/indie-house-server/src/media-worker/internal/redis"
)

func main() {
	cfg := config.Load()

	log.Println("🎵 Indie House Media Worker starting...")
	log.Printf("Config: Redis=%s:%s, MinIO=%s, Queue=%s",
		cfg.RedisHost, cfg.RedisPort, cfg.MinioEndpoint, cfg.RedisQueue)

	// Init Redis
	rdb := redis.New(cfg)
	if err := rdb.Ping(context.Background()); err != nil {
		log.Fatalf("Redis connection failed: %v", err)
	}
	defer rdb.Close()
	log.Println("✅ Redis connected")

	// Init MinIO
	minioClient, err := minio.New(cfg)
	if err != nil {
		log.Fatalf("MinIO connection failed: %v", err)
	}
	log.Println("✅ MinIO connected")

	// Init Processor
	proc := processor.New(cfg, rdb, minioClient)

	// Graceful shutdown
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		<-sigChan
		log.Println("🛑 Shutdown signal received, finishing current job...")
		cancel()
	}()

	// Main loop
	log.Println("👂 Listening for jobs...")
	for {
		select {
		case <-ctx.Done():
			log.Println("👋 Worker stopped")
			return
		default:
		}

		job, err := rdb.BPopJob(ctx, 5*time.Second)
		if err != nil {
			log.Printf("❌ BRPOP error: %v", err)
			time.Sleep(1 * time.Second)
			continue
		}
		if job == nil {
			continue // Timeout, no job
		}

		log.Printf("🎧 Processing track: %s (attempt %d)", job.TrackID, job.Attempt)

		// The job is already off the queue. A shutdown signal must not cancel
		// its uploads or status callbacks; Compose's stop_grace_period lets it finish.
		jobCtx := context.WithoutCancel(ctx)
		if err := proc.Process(jobCtx, job); err != nil {
			log.Printf("❌ Failed to process %s: %v", job.TrackID, err)

			if job.Attempt < cfg.MaxRetries {
				log.Printf("🔄 Retrying %s (attempt %d/%d)", job.TrackID, job.Attempt+1, cfg.MaxRetries)
				if err := rdb.PushJob(jobCtx, job); err != nil {
					log.Printf("❌ Failed to requeue: %v", err)
				}
				// Delay before retry
				time.Sleep(cfg.RetryDelay)
			} else {
				log.Printf("💀 Max retries reached for %s, giving up", job.TrackID)
			}
		} else {
			log.Printf("✅ Completed track: %s", job.TrackID)
		}
	}
}