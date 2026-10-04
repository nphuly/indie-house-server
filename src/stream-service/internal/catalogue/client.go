package catalogue

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"
)

var ErrNotFound = errors.New("stream asset not found")

type Asset struct {
	Key         string `json:"key"`
	ContentType string `json:"content_type"`
	Quality     string `json:"quality"`
}

type Client struct {
	baseURL     string
	internalKey string
	httpClient  *http.Client
}

func New(baseURL, internalKey string, timeout time.Duration) *Client {
	return &Client{
		baseURL:     strings.TrimRight(baseURL, "/"),
		internalKey: internalKey,
		httpClient:  &http.Client{Timeout: timeout},
	}
}

func (c *Client) Resolve(ctx context.Context, trackID, kind, quality, asset string) (Asset, error) {
	endpoint, err := url.Parse(c.baseURL + "/stream/tracks/" + trackID + "/asset")
	if err != nil {
		return Asset{}, err
	}
	query := endpoint.Query()
	query.Set("kind", kind)
	if quality != "" {
		query.Set("quality", quality)
	}
	if asset != "" {
		query.Set("asset", asset)
	}
	endpoint.RawQuery = query.Encode()

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, endpoint.String(), nil)
	if err != nil {
		return Asset{}, err
	}
	req.Header.Set("X-Internal-Key", c.internalKey)
	req.Header.Set("Accept", "application/json")

	response, err := c.httpClient.Do(req)
	if err != nil {
		return Asset{}, fmt.Errorf("request catalogue: %w", err)
	}
	defer response.Body.Close()

	if response.StatusCode == http.StatusNotFound {
		return Asset{}, ErrNotFound
	}
	if response.StatusCode != http.StatusOK {
		return Asset{}, fmt.Errorf("catalogue returned %d", response.StatusCode)
	}

	var result Asset
	if err := json.NewDecoder(response.Body).Decode(&result); err != nil || result.Key == "" || result.ContentType == "" {
		return Asset{}, errors.New("invalid catalogue asset response")
	}
	return result, nil
}
