package main

import (
	"context"
	"errors"
	"log"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/nphuly/indie-house-server/src/stream-service/internal/assetcache"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/auth"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/catalogue"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/config"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/httpapi"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/ratelimit"
	"github.com/nphuly/indie-house-server/src/stream-service/internal/storage"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatal(err)
	}

	validator, err := auth.NewValidatorWithKeys(cfg.JWTPublicKeyPath, cfg.JWTPublicKeysDirectory, cfg.JWTLegacyKeyID, cfg.JWTIssuer, cfg.JWTAudience)
	if err != nil {
		log.Fatal(err)
	}
	limiter := ratelimit.New(cfg.RedisAddress, cfg.RedisPassword, cfg.RedisDB)
	defer limiter.Close()
	if err := limiter.Ping(context.Background()); err != nil {
		log.Fatalf("redis connection failed: %v", err)
	}
	objectStorage, err := storage.New(
		storage.Endpoint{Host: cfg.MinioEndpoint, UseSSL: cfg.MinioUseSSL},
		storage.Endpoint{Host: cfg.MinioPublicEndpoint, UseSSL: cfg.MinioPublicUseSSL},
		cfg.MinioAccessKey, cfg.MinioSecretKey, cfg.MinioBucket,
	)
	if err != nil {
		log.Fatal(err)
	}

	// One JSON line per audio request on stdout; Docker's json-file driver rotates it.
	accessLog := slog.New(slog.NewJSONHandler(os.Stdout, nil))

	listening, stopListening := context.WithCancel(context.Background())
	defer stopListening()
	assets := assetcache.New(cfg.CatalogueCacheTTL, 20000)
	if cfg.CatalogueCacheTTL > 0 {
		events := redis.NewClient(&redis.Options{Addr: cfg.RedisAddress, Password: cfg.RedisPassword, DB: cfg.RedisDB})
		defer events.Close()
		go assets.Listen(listening, events, accessLog)
	}

	server := &http.Server{
		Addr:              cfg.ListenAddr,
		Handler:           httpapi.New(cfg, validator, limiter, catalogue.New(cfg.ControlPlaneInternalURL, cfg.InternalAPIKey, cfg.UpstreamTimeout), objectStorage, assets, accessLog),
		ReadHeaderTimeout: 5 * time.Second,
		IdleTimeout:       70 * time.Second,
	}

	go func() {
		log.Printf("stream service listening on %s", cfg.ListenAddr)
		if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("server failed: %v", err)
		}
	}()

	signals := make(chan os.Signal, 1)
	signal.Notify(signals, syscall.SIGINT, syscall.SIGTERM)
	<-signals

	shutdown, cancel := context.WithTimeout(context.Background(), 15*time.Second)
	defer cancel()
	if err := server.Shutdown(shutdown); err != nil {
		log.Printf("graceful shutdown failed: %v", err)
	}
}
