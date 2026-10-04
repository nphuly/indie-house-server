FROM golang:1.27.1-alpine AS builder

WORKDIR /src
# A separate module layer keeps downloads in the CI registry cache.
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-w -s" -o /out/stream-service ./cmd/server

FROM alpine:3.24
RUN apk add --no-cache ca-certificates && adduser -D -u 1000 stream
COPY --from=builder /out/stream-service /app/stream-service
USER stream
EXPOSE 8080
ENTRYPOINT ["/app/stream-service"]
