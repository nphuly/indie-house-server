package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	ListenAddr string

	ControlPlaneInternalURL string
	InternalAPIKey          string

	RedisAddress  string
	RedisPassword string
	RedisDB       int

	MinioEndpoint  string
	MinioAccessKey string
	MinioSecretKey string
	MinioBucket    string
	MinioUseSSL    bool
	// Where presigned URLs point. Reads use MinioEndpoint.
	MinioPublicEndpoint string
	MinioPublicUseSSL   bool

	JWTPublicKeyPath       string
	JWTPublicKeysDirectory string
	JWTLegacyKeyID         string
	JWTIssuer              string
	JWTAudience            string
	RequireHQClaim         bool

	StreamRequestsPerMinute  int
	PresignRequestsPerMinute int
	HLSRequestsPerMinute     int
	PresignTTL               time.Duration
	UpstreamTimeout          time.Duration
	// How long a catalogue answer is reused; invalidation usually ends it sooner. Zero turns the cache off.
	CatalogueCacheTTL time.Duration
}

func Load() (*Config, error) {
	cfg := &Config{
		ListenAddr: getEnv("LISTEN_ADDR", ":8080"),

		ControlPlaneInternalURL: strings.TrimRight(getEnv("CONTROL_PLANE_INTERNAL_URL", "http://control-plane/api/internal"), "/"),
		InternalAPIKey:          os.Getenv("INTERNAL_API_KEY"),

		RedisAddress:  getEnv("REDIS_ADDR", "redis:6379"),
		RedisPassword: os.Getenv("REDIS_PASSWORD"),
		RedisDB:       getEnvInt("REDIS_DB", 0),

		MinioEndpoint:  os.Getenv("MINIO_ENDPOINT"),
		MinioAccessKey: os.Getenv("MINIO_ACCESS_KEY"),
		MinioSecretKey: os.Getenv("MINIO_SECRET_KEY"),
		MinioBucket:    os.Getenv("MINIO_BUCKET"),
		MinioUseSSL:    getEnvBool("MINIO_USE_SSL", false),

		JWTPublicKeyPath:       getEnv("JWT_PUBLIC_KEY_PATH", "/app/keys/public.key"),
		JWTPublicKeysDirectory: os.Getenv("JWT_PUBLIC_KEYS_DIRECTORY"),
		JWTLegacyKeyID:         getEnv("JWT_LEGACY_KEY_ID", "stream-v1"),
		JWTIssuer:              os.Getenv("JWT_ISSUER"),
		JWTAudience:            os.Getenv("JWT_AUDIENCE"),
		RequireHQClaim:         getEnvBool("REQUIRE_HQ_CLAIM", false),

		StreamRequestsPerMinute:  getEnvInt("STREAM_RATE_LIMIT_PER_MINUTE", 120),
		PresignRequestsPerMinute: getEnvInt("PRESIGN_RATE_LIMIT_PER_MINUTE", 30),
		HLSRequestsPerMinute:     getEnvInt("HLS_RATE_LIMIT_PER_MINUTE", 600),
		PresignTTL:               time.Duration(getEnvInt("PRESIGN_TTL_SECONDS", 300)) * time.Second,
		UpstreamTimeout:          time.Duration(getEnvInt("UPSTREAM_TIMEOUT_SECONDS", 10)) * time.Second,
		CatalogueCacheTTL:        time.Duration(getEnvIntAllowZero("CATALOGUE_CACHE_TTL_SECONDS", 300)) * time.Second,
	}

	cfg.MinioPublicEndpoint = getEnv("MINIO_PUBLIC_ENDPOINT", cfg.MinioEndpoint)
	cfg.MinioPublicUseSSL = getEnvBool("MINIO_PUBLIC_USE_SSL", cfg.MinioUseSSL)

	if cfg.InternalAPIKey == "" || cfg.MinioEndpoint == "" || cfg.MinioAccessKey == "" || cfg.MinioSecretKey == "" || cfg.MinioBucket == "" || cfg.JWTIssuer == "" || cfg.JWTAudience == "" {
		return nil, fmt.Errorf("stream service is missing a required secret or endpoint")
	}
	if cfg.PresignTTL <= 0 || cfg.PresignTTL > 15*time.Minute {
		return nil, fmt.Errorf("PRESIGN_TTL_SECONDS must be between 1 and 900")
	}

	return cfg, nil
}

func getEnv(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}

func getEnvInt(key string, fallback int) int {
	value, err := strconv.Atoi(os.Getenv(key))
	if err != nil || value <= 0 {
		return fallback
	}
	return value
}

func getEnvIntAllowZero(key string, fallback int) int {
	value, err := strconv.Atoi(os.Getenv(key))
	if err != nil || value < 0 {
		return fallback
	}
	return value
}

func getEnvBool(key string, fallback bool) bool {
	value, err := strconv.ParseBool(os.Getenv(key))
	if err != nil {
		return fallback
	}
	return value
}
