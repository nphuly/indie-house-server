// Package assetcache remembers which storage object a track request maps to,
// and its size, so a playing song does not cost a control-plane call and a
// storage stat for every chunk.
//
// The control plane publishes a track's public ID on Channel after a change
// to its visibility or renditions commits, and that track's entries are
// dropped at once. Entries also expire after a TTL in case a message is lost.
package assetcache

import (
	"context"
	"log/slog"
	"sync"
	"time"

	"github.com/redis/go-redis/v9"

	"github.com/nphuly/indie-house-server/src/stream-service/internal/catalogue"
)

// Channel is part of the contract with the control plane: each message is a
// track public ID, or "*" to drop everything.
const Channel = "stream:invalidate"

type Key struct {
	Track, Kind, Quality, Asset string
}

type Entry struct {
	Asset catalogue.Asset
	Size  int64 // 0 until the object has been stat'ed
}

type item struct {
	entry   Entry
	expires time.Time
}

type Cache struct {
	ttl     time.Duration
	max     int
	now     func() time.Time
	mu      sync.Mutex
	entries map[Key]item
	// epoch moves on every invalidation. A request that looked the catalogue
	// up before an invalidation must not store what it found afterwards.
	epoch uint64
}

// New returns a cache holding at most max entries for ttl each. A zero ttl
// turns caching off.
func New(ttl time.Duration, max int) *Cache {
	return &Cache{ttl: ttl, max: max, now: time.Now, entries: map[Key]item{}}
}

// Get returns the cached entry, and the epoch to pass to Put for anything the
// caller learns while serving this request.
func (c *Cache) Get(key Key) (Entry, uint64, bool) {
	if c == nil || c.ttl <= 0 {
		return Entry{}, 0, false
	}
	c.mu.Lock()
	defer c.mu.Unlock()
	cached, ok := c.entries[key]
	if !ok || !c.now().Before(cached.expires) {
		delete(c.entries, key)
		return Entry{}, c.epoch, false
	}
	return cached.entry, c.epoch, true
}

// Put stores entry unless the cache was invalidated since the epoch was read.
func (c *Cache) Put(key Key, entry Entry, epoch uint64) {
	if c == nil || c.ttl <= 0 {
		return
	}
	c.mu.Lock()
	defer c.mu.Unlock()
	if epoch != c.epoch {
		return
	}
	if _, exists := c.entries[key]; !exists && len(c.entries) >= c.max {
		// Many tracks at once (HLS segments, a large library): start over
		// rather than track recency.
		c.entries = map[Key]item{}
	}
	c.entries[key] = item{entry: entry, expires: c.now().Add(c.ttl)}
}

// Forget drops every entry of one track.
func (c *Cache) Forget(track string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.epoch++
	for key := range c.entries {
		if key.Track == track {
			delete(c.entries, key)
		}
	}
}

func (c *Cache) Flush() {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.epoch++
	c.entries = map[Key]item{}
}

// Listen applies invalidation messages until ctx ends. Messages sent while the
// subscription was down are lost, so every (re)subscribe and every receive
// error clears the whole cache.
func (c *Cache) Listen(ctx context.Context, client *redis.Client, logger *slog.Logger) {
	subscription := client.Subscribe(ctx, Channel)
	defer subscription.Close()
	for {
		message, err := subscription.Receive(ctx)
		if err != nil {
			if ctx.Err() != nil {
				return
			}
			c.Flush()
			logger.Warn("cache invalidation channel unavailable", slog.String("error", err.Error()))
			select {
			case <-ctx.Done():
				return
			case <-time.After(time.Second):
			}
			continue
		}
		switch message := message.(type) {
		case *redis.Subscription:
			c.Flush()
		case *redis.Message:
			if message.Payload == "*" {
				c.Flush()
			} else {
				c.Forget(message.Payload)
			}
		}
	}
}
