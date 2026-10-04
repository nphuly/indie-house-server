package minio

import (
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
    "bytes"

	"github.com/nphuly/indie-house-server/src/media-worker/internal/config"
	minio "github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
)

type Client struct {
	client *minio.Client
	bucket string
}

func New(cfg *config.Config) (*Client, error) {
	client, err := minio.New(cfg.MinioEndpoint, &minio.Options{
		Creds:  credentials.NewStaticV4(cfg.MinioAccessKey, cfg.MinioSecretKey, ""),
		Secure: cfg.MinioUseSSL,
	})
	if err != nil {
		return nil, fmt.Errorf("create minio client: %w", err)
	}

	return &Client{
		client: client,
		bucket: cfg.MinioBucket,
	}, nil
}

func (c *Client) Download(ctx context.Context, objectKey, destPath string) error {
	obj, err := c.client.GetObject(ctx, c.bucket, objectKey, minio.GetObjectOptions{})
	if err != nil {
		return fmt.Errorf("get object: %w", err)
	}
	defer obj.Close()

	if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
		return err
	}

	out, err := os.Create(destPath)
	if err != nil {
		return err
	}
	defer out.Close()

	if _, err := io.Copy(out, obj); err != nil {
		return fmt.Errorf("download: %w", err)
	}

	return nil
}

func (c *Client) Upload(ctx context.Context, localPath, objectKey string) error {
	_, err := c.client.FPutObject(ctx, c.bucket, objectKey, localPath, minio.PutObjectOptions{})
	if err != nil {
		return fmt.Errorf("upload %s: %w", objectKey, err)
	}
	return nil
}

func (c *Client) UploadBytes(ctx context.Context, data []byte, objectKey string) error {
	_, err := c.client.PutObject(ctx, c.bucket, objectKey, bytes.NewReader(data), int64(len(data)), minio.PutObjectOptions{})
	return err
}