# Triển khai

[English](../en/deployment.md) · **Tiếng Việt**

Hướng dẫn này dựng một instance production trên một máy chủ Linux có Docker. Bản triển khai tham chiếu chạy trên một VM trong homelab, dùng Cloudflare Tunnel để có HTTPS, nên không phải mở cổng nào trên router.

```text
Trình duyệt / app ──HTTPS──▶ Cloudflare ──tunnel──▶ cloudflared trên máy chủ
                                                      ├─ example.com          → 127.0.0.1:8080 → Caddy edge (Docker)
                                                      └─ objects.example.com  → <storage>:9000 → MinIO S3 API
```

Trước khi bắt đầu, đọc [DISCLAIMER.md](../../DISCLAIMER.md#tiếng-việt): bạn chịu trách nhiệm về máy chủ, dữ liệu và quyền đối với mọi bài nhạc bạn tải lên.

## Yêu cầu

- Máy chủ hoặc VM Linux có Docker Engine, plugin Compose, Git, curl và OpenSSL. Chuyển mã là việc nặng nhất; Compose giới hạn media worker và queue worker mỗi service 2 CPU và 2 GB bộ nhớ.
- Một hostname HTTPS ổn định. Origin này là danh tính của instance: app và token gắn với nó.
- Object storage tương thích S3, có một bucket private và credential chỉ có quyền trên bucket đó.
- Một cách đưa HTTPS tới máy chủ. Hướng dẫn này dùng Cloudflare Tunnel; xem thêm [HTTPS proxy khác](#https-proxy-khác).

## 1. Chuẩn bị máy chủ

Tạo một tài khoản riêng để deploy và chỉ cấp quyền Docker cho tài khoản đó (quyền Docker tương đương root):

```bash
sudo adduser --disabled-password --gecos '' indie-house
sudo usermod -aG docker indie-house
sudo install -d -o indie-house -g indie-house -m 0750 /srv/indie-house
sudo -u indie-house git clone https://github.com/nphuly/indie-house-server.git /srv/indie-house
```

Compose production không publish cổng database hay Redis. Cổng duy nhất được publish là edge, trên `127.0.0.1:8080`.

## 2. Cấu hình

Với user `indie-house`, trong `/srv/indie-house`:

```bash
test ! -e .env && cp .env.example .env
chmod 600 .env
```

Điền `.env` theo [Cấu hình](configuration.md) và [checklist production](configuration.md#checklist-production). Thêm hai dòng sau để lệnh `docker compose` gõ tay dùng file production và profile của gateway:

```dotenv
COMPOSE_FILE=docker-compose.yml:docker-compose.production.yml
COMPOSE_PROFILES=stream
```

Tạo khoá ký một lần (bỏ qua nếu instance đã có khoá):

```bash
install -d -m 0700 infrastructure/stream-keys
(umask 077; openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/private.key)
openssl pkey -in infrastructure/stream-keys/private.key -pubout -out infrastructure/stream-keys/public.key
```

Sao lưu `.env` và khoá vào nơi an toàn trước khi làm tiếp.

## 3. Object storage

- Giữ bucket private. Indie House không bao giờ cần object công khai.
- Cấp cho ứng dụng một access key riêng, chỉ có quyền trên bucket.
- Chỉ mở S3 API, không bao giờ mở console của storage.
- Trỏ `MINIO_ENDPOINT` (service Go) và `AWS_INTERNAL_ENDPOINT` (Laravel) vào địa chỉ nội bộ của storage, còn `AWS_ENDPOINT` / `MINIO_PUBLIC_ENDPOINT` vào hostname công khai. Client chỉ dùng hostname công khai cho upload có chữ ký, artwork và presigned URL; audio cho web đi qua gateway.

## 4. HTTPS bằng Cloudflare Tunnel

1. Thêm domain vào Cloudflare và đợi zone chuyển sang Active.
2. Trong **Networking → Tunnels**, tạo tunnel, cài service `cloudflared` trên máy chủ bằng lệnh dashboard hiển thị, và đợi connector báo Healthy. Coi token của tunnel là secret.
3. Thêm hai route **published application**, không đặt path:

   | Hostname | Service | HTTP Host header |
   | --- | --- | --- |
   | `example.com` | `http://127.0.0.1:8080` | `example.com` |
   | `objects.example.com` | `http://<địa-chỉ-storage>:9000` | `objects.example.com` |

   Storage phải thấy đúng hostname mà request được ký.
4. Tạo Cache Rule **bỏ qua cache** cho cả hai hostname. Với hostname của storage, rule này là bắt buộc: thiếu nó, request `HEAD` có chữ ký tới object `.flac` và `.jpg` mới sẽ lỗi `403`, và upload bị kẹt. Kiểm tra bằng `curl -sI https://objects.example.com/<bucket>/x.jpg | grep -i cf-cache-status`, kết quả phải là `DYNAMIC`.
5. Không đặt Cloudflare Access trước hostname nào: app và presigned URL không đăng nhập qua đó được.

Gói Free và Pro của Cloudflare giới hạn body request 100 MB, nên upload lớn hơn qua tunnel sẽ lỗi `413`.

### HTTPS proxy khác

Proxy nào chạy trên cùng máy, kết thúc TLS và chuyển tiếp tới `http://127.0.0.1:8080` đều dùng được. Edge đọc địa chỉ người dùng từ `Cf-Connecting-Ip`; với proxy khác, đổi `client_ip_headers` trong `infrastructure/caddy/Caddyfile` thành header mà proxy của bạn đặt (ví dụ `X-Forwarded-For`), và giữ edge ở loopback.

## 5. Build và khởi động

### Thủ công: build trên máy chủ

```bash
docker build --target production -t indie-house/control-plane:current src/control-plane
docker build -t indie-house/media-worker:current src/media-worker
docker build -t indie-house/stream-service:current src/stream-service

docker compose up -d postgres redis
docker compose run --rm --no-deps --entrypoint php control-plane artisan migrate --force
docker compose up -d
```

File Compose production mặc định dùng tag `indie-house/<service>:current`.

### Tự động: pipeline release và script deploy

Repo có sẵn pipeline mà bản triển khai tham chiếu dùng (`.gitlab-ci.yml`, viết cho GitLab CI). Pipeline chỉ chạy trên branch được bảo vệ `release/X.Y[.Z]`:

1. **Kiểm tra:** `php -l` mọi file PHP, `docker compose config` cho cả hai môi trường, `go vet` và `go test` cho hai module Go, `sh -n` cho script deploy cùng script test của chúng, và bộ test Laravel build từ target `test` của Dockerfile.
2. **Build:** ba image bằng BuildKit rootless, chạy song song với bước kiểm tra, gắn tag `sha-<commit>`, có build cache trong registry.
3. **Deploy:** job chạy tay, SSH vào máy chủ và chạy `infrastructure/scripts/deploy-release.sh` với SHA của commit.

Trên máy chủ, script deploy:

- từ chối chạy nếu `.env` không qua [checklist production](configuration.md#checklist-production) hoặc thiếu khoá;
- checkout đúng commit và pull ba image của commit đó, bằng credential registry chỉ đọc lưu trong `/etc/indie-house/registry.env`;
- chuyển Redis đang chạy sang chế độ AOF một cách an toàn nếu cần;
- nếu có migration đang chờ, tạo và kiểm tra một bản dump PostgreSQL, rồi chạy `migrate --force` từ image mới **trước khi** thay container ứng dụng nào;
- khởi động bản mới, reload edge (`caddy reload`), và đợi `/up` cùng `/.well-known/indie-house` trả `200`;
- nếu rollout thất bại, đưa image cũ trở lại. Script không bao giờ đảo ngược migration, không seed, không xử lý lại media và không xoá volume;
- sau khi rollout thành công, gắn tag `:current` cho image và xoá image của các release cũ hơn release trước đó.

Nếu dùng hệ thống CI khác, gọi cùng script với `<commit-sha> <registry-image> <release-branch>` từ job deploy của bạn.

## 6. Tạo admin đầu tiên

```bash
docker compose exec control-plane php artisan user:create-super-admin
```

Lệnh hỏi tên, email và mật khẩu. Không bao giờ truyền mật khẩu qua tham số dòng lệnh. Sau đó đăng nhập `https://example.com/admin`, đặt `INSTANCE_REGISTRATION_MODE` theo cách bạn muốn mọi người tham gia, và tạo lời mời trong **Lời mời**.

## 7. Kiểm tra bản triển khai

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://example.com/up                          # 200, không redirect
curl -s https://example.com/.well-known/indie-house                                       # 200, URL đều là https://example.com
curl -s -o /dev/null -w '%{http_code}\n' https://example.com/api/internal/tracks/status   # 404
curl -sI https://example.com/ | grep -i -E 'content-security|strict-transport'            # có cả hai header
```

Sau đó upload một bài ngắn trong trang quản trị, đợi bài chuyển sang **completed**, bật xuất bản, rồi phát thử trên web và trên điện thoại dùng mạng di động.

## Nâng cấp

Với pipeline, push commit đã review lên branch release và chạy job deploy. Thủ công:

```bash
git pull
# build lại ba image như bước 5
docker compose run --rm --no-deps --entrypoint php control-plane artisan migrate --force
docker compose up -d
docker compose exec edge caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
```

Sao lưu database trước khi chạy migration. Caddyfile thay đổi không làm container edge được tạo lại, nên luôn reload nó. Migration được viết để chạy được với bản đang chạy, nên rollout lỗi có thể quay về image cũ mà không phải đụng tới schema.

## Xử lý sự cố

| Triệu chứng | Kiểm tra |
| --- | --- |
| Cloudflare `1033` | `cloudflared` chưa kết nối. Xem `sudo systemctl status cloudflared` và trạng thái tunnel. |
| Cloudflare `502` | Tunnel đã kết nối nhưng origin không trả lời: chạy `curl http://127.0.0.1:8080/up` trên máy chủ. |
| Cloudflare `522` | DNS record của hostname không phải CNAME `cfargotunnel.com` của tunnel (thường là A record được import). |
| `/up` redirect về chính nó (`308`) | Control plane cần `SERVER_NAME=:80` (đã đặt trong Compose) và `PUBLIC_SCHEME=https`. |
| Manifest `503`, hoặc đăng nhập được nhưng audio trả `401` | `APP_URL`, `INSTANCE_BASE_URL` và `STREAM_JWT_ISSUER` khác nhau, `INSTANCE_ID` không phải UUID, hoặc container chưa được tạo lại sau khi sửa `.env`. |
| Upload bị kẹt, mất artwork sau khi lưu form | Hostname của storage chưa có rule bỏ qua cache. Thêm rule, rồi chạy `php artisan media:repair-uploads --dry-run` (xem [Vận hành](operations.md#sửa-file-upload-bị-kẹt)). |
| Upload lỗi `413` | File lớn hơn giới hạn request của proxy. |
