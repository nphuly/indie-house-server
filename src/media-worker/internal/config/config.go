package config

import (
	"os"
	"strconv"
	"time"
)

type Config struct {
	RedisHost     string
	RedisPort     string
	RedisPassword string
	RedisQueue    string
	RedisDB       int

	MinioEndpoint  string
	MinioAccessKey string
	MinioSecretKey string
	MinioBucket    string
	MinioUseSSL    bool

	DBApiURL string

	TmpDir        string
	MaxRetries    int
	RetryDelay    time.Duration
	WorkerTimeout time.Duration

    InternalAPIKey string
}

func Load() *Config {
	return &Config{
		RedisHost:     getEnv("REDIS_HOST", "redis"),
		RedisPort:     getEnv("REDIS_PORT", "6379"),
		RedisPassword: getEnv("REDIS_PASSWORD", ""),
		RedisQueue:    getEnv("REDIS_QUEUE", "audio-processing"),
		RedisDB:       getEnvInt("REDIS_DB", 0),

		MinioEndpoint:  getEnv("MINIO_ENDPOINT", "minio:9000"),
		MinioAccessKey: getEnv("MINIO_ACCESS_KEY", "minioadmin"),
		MinioSecretKey: getEnv("MINIO_SECRET_KEY", "minioadmin"),
		MinioBucket:    getEnv("MINIO_BUCKET", "indie-house"),
		MinioUseSSL:    getEnvBool("MINIO_USE_SSL", false),

		DBApiURL: getEnv("DB_API_URL", "http://control-plane:80/api/internal"),

		TmpDir:        getEnv("TMP_DIR", "/tmp/media-worker"),
		MaxRetries:    getEnvInt("MAX_RETRIES", 3),
		RetryDelay:    time.Duration(getEnvInt("RETRY_DELAY_SECONDS", 30)) * time.Second,
		WorkerTimeout: time.Duration(getEnvInt("WORKER_TIMEOUT_MINUTES", 10)) * time.Minute,

        InternalAPIKey: getEnv("INTERNAL_API_KEY", ""),
	}
}

func getEnv(key, defaultVal string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return defaultVal
}

func getEnvInt(key string, defaultVal int) int {
	if v := os.Getenv(key); v != "" {
		if i, err := strconv.Atoi(v); err == nil {
			return i
		}
	}
	return defaultVal
}

func getEnvBool(key string, defaultVal bool) bool {
	if v := os.Getenv(key); v != "" {
		if b, err := strconv.ParseBool(v); err == nil {
			return b
		}
	}
	return defaultVal
}