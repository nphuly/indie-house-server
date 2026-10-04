package assetcache

import (
	"testing"
	"time"

	"github.com/nphuly/indie-house-server/src/stream-service/internal/catalogue"
)

func TestEntriesExpireAndCanBeForgottenPerTrack(t *testing.T) {
	now := time.Unix(1_700_000_000, 0)
	cache := New(time.Minute, 100)
	cache.now = func() time.Time { return now }
	high := Key{Track: "AbcDef123456", Kind: "audio", Quality: "high"}
	other := Key{Track: "Zyx987654321", Kind: "audio", Quality: "high"}

	_, epoch, _ := cache.Get(high)
	cache.Put(high, Entry{Asset: catalogue.Asset{Key: "processed/a/mp3_320.mp3"}, Size: 10}, epoch)
	cache.Put(other, Entry{Asset: catalogue.Asset{Key: "processed/b/mp3_320.mp3"}}, epoch)
	if entry, _, ok := cache.Get(high); !ok || entry.Size != 10 {
		t.Fatalf("expected a hit, got %#v %v", entry, ok)
	}

	cache.Forget("AbcDef123456")
	if _, _, ok := cache.Get(high); ok {
		t.Fatal("a forgotten track must be looked up again")
	}
	if _, _, ok := cache.Get(other); !ok {
		t.Fatal("other tracks stay cached")
	}

	now = now.Add(2 * time.Minute)
	if _, _, ok := cache.Get(other); ok {
		t.Fatal("entries expire after the TTL")
	}
}

func TestALookupFromBeforeAnInvalidationIsNotStored(t *testing.T) {
	cache := New(time.Minute, 100)
	key := Key{Track: "AbcDef123456", Kind: "audio", Quality: "auto"}

	_, epoch, _ := cache.Get(key)
	// The track is unpublished while this request is still asking the catalogue.
	cache.Forget("AbcDef123456")
	cache.Put(key, Entry{Asset: catalogue.Asset{Key: "processed/a/mp3_128.mp3"}}, epoch)

	if _, _, ok := cache.Get(key); ok {
		t.Fatal("a result from before the invalidation must not be cached")
	}
}

func TestAZeroTTLOrNilCacheNeverHits(t *testing.T) {
	var none *Cache
	for _, cache := range []*Cache{New(0, 100), none} {
		key := Key{Track: "AbcDef123456"}
		cache.Put(key, Entry{Size: 1}, 0)
		if _, _, ok := cache.Get(key); ok {
			t.Fatal("caching is off")
		}
	}
}

func TestAFullCacheStartsOver(t *testing.T) {
	cache := New(time.Minute, 2)
	for _, track := range []string{"AAAAAAAAAAAA", "BBBBBBBBBBBB", "CCCCCCCCCCCC"} {
		_, epoch, _ := cache.Get(Key{Track: track})
		cache.Put(Key{Track: track}, Entry{Size: 1}, epoch)
	}
	if len(cache.entries) != 1 {
		t.Fatalf("expected the cache to start over, have %d entries", len(cache.entries))
	}
}
