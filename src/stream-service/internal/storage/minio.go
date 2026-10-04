package storage

import (
	"context"
	"fmt"
	"io"
	"time"

	"github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
)

type Object struct {
	Reader io.ReadCloser
	Size   int64
}

type Client struct {
	client *minio.Client
	signer *minio.Client
	bucket string
}

// Endpoint is an S3 host:port without a scheme; UseSSL picks HTTPS.
type Endpoint struct {
	Host   string
	UseSSL bool
}

// New reads objects through endpoint, which should be the nearest address of
// the storage (the LAN on the homelab). Presigned URLs go to clients, so they
// are signed for public, the address clients can reach; an empty public host
// signs for endpoint. Signing is computed locally, so no audio travels the
// public route.
func New(endpoint, public Endpoint, accessKey, secretKey, bucket string) (*Client, error) {
	creds := credentials.NewStaticV4(accessKey, secretKey, "")
	client, err := minio.New(endpoint.Host, &minio.Options{Creds: creds, Secure: endpoint.UseSSL})
	if err != nil {
		return nil, fmt.Errorf("create object storage client: %w", err)
	}
	signer := client
	if public.Host != "" && public != endpoint {
		signer, err = minio.New(public.Host, &minio.Options{Creds: creds, Secure: public.UseSSL})
		if err != nil {
			return nil, fmt.Errorf("create object storage signing client: %w", err)
		}
	}
	return &Client{client: client, signer: signer, bucket: bucket}, nil
}

func (c *Client) Size(ctx context.Context, key string) (int64, error) {
	stat, err := c.client.StatObject(ctx, c.bucket, key, minio.StatObjectOptions{})
	if err != nil {
		return 0, err
	}
	return stat.Size, nil
}

func (c *Client) Open(ctx context.Context, key string, start, end int64) (Object, error) {
	options := minio.GetObjectOptions{}
	if start >= 0 && end >= start {
		if err := options.SetRange(start, end); err != nil {
			return Object{}, err
		}
	}
	reader, err := c.client.GetObject(ctx, c.bucket, key, options)
	if err != nil {
		return Object{}, err
	}
	return Object{Reader: reader}, nil
}

func (c *Client) Presign(ctx context.Context, key string, expiry time.Duration) (string, error) {
	url, err := c.signer.PresignedGetObject(ctx, c.bucket, key, expiry, nil)
	if err != nil {
		return "", err
	}
	return url.String(), nil
}
