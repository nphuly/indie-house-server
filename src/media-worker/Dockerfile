# ============================================================
# Build stage
# ============================================================
FROM golang:1.27.1-alpine AS builder

WORKDIR /src

# A separate module layer keeps downloads in the CI registry cache.
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-w -s" -o /out/media-worker ./cmd/worker

# ============================================================
# Runtime stage — FFmpeg + Go binary
# ============================================================
FROM alpine:3.24

# flac provides metaflac, which adds seek points to the lossless rendition.
RUN apk add --no-cache ca-certificates ffmpeg flac \
    && adduser -D -u 1000 worker \
    && install -d -o worker -g worker /tmp/media-worker

WORKDIR /app
COPY --from=builder /out/media-worker /app/media-worker
USER worker

ENV TMP_DIR=/tmp/media-worker
ENV DB_API_URL=http://control-plane:80/api/internal

ENTRYPOINT ["/app/media-worker"]
