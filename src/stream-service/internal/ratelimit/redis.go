package ratelimit

import (
	"context"
	"fmt"
	"time"

	"github.com/redis/go-redis/v9"
)

type Limiter struct {
	client *redis.Client
}

func New(address, password string, database int) *Limiter {
	return &Limiter{client: redis.NewClient(&redis.Options{Addr: address, Password: password, DB: database})}
}

func (l *Limiter) Ping(ctx context.Context) error {
	return l.client.Ping(ctx).Err()
}

func (l *Limiter) Close() error {
	return l.client.Close()
}

// Allow uses a per-subject fixed window. A Redis outage fails closed: serving
// unmetered audio is more costly and less safe than returning a retryable 503.
func (l *Limiter) Allow(ctx context.Context, route, subject string, limit int) (bool, error) {
	window := time.Now().UTC().Format("200601021504")
	key := fmt.Sprintf("stream-rate:%s:%s:%s", route, subject, window)
	count, err := l.client.Incr(ctx, key).Result()
	if err != nil {
		return false, err
	}
	if count == 1 {
		if err := l.client.Expire(ctx, key, 2*time.Minute).Err(); err != nil {
			return false, err
		}
	}
	return count <= int64(limit), nil
}
