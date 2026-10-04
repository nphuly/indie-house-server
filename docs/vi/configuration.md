# Cấu hình

[English](../en/configuration.md) · **Tiếng Việt**

Mọi service đọc chung một file `.env` ở thư mục gốc của repo. Bắt đầu từ [`.env.example`](../../.env.example), giữ file ngoài Git (`chmod 600 .env`), và tạo mỗi secret riêng cho từng instance, từng môi trường.

```bash
test ! -e .env && cp .env.example .env
chmod 600 .env
```

Service Go không đọc `.env` trực tiếp. `docker-compose.yml` chỉ truyền cho mỗi service những biến nó cần, dưới tên riêng của service đó (liệt kê [ở cuối trang](#biến-của-gateway-và-worker)).

## Tạo giá trị

| Giá trị | Lệnh |
| --- | --- |
| `APP_KEY` | `printf 'base64:%s\n' "$(openssl rand -base64 32)"` |
| `INSTANCE_ID` | `python3 -c 'import uuid; print(uuid.uuid4())'` |
| `INTERNAL_API_KEY`, `REDIS_PASSWORD` | `openssl rand -hex 32` |
| `BACKUP_ENCRYPTION_KEY` | `openssl rand -base64 32` |
| Cặp khoá ký | Xem [Khoá ký](#khoá-ký) |

Tạo `APP_KEY`, `INSTANCE_ID` và khoá ký **một lần** rồi sao lưu. Đổi chúng trên instance đang chạy sẽ đăng xuất mọi người, khiến app coi đây là instance mới, hoặc làm dữ liệu đã mã hoá không đọc được nữa.

## Ứng dụng và instance

| Biến | Mặc định | Ghi chú |
| --- | --- | --- |
| `APP_ENV`, `APP_DEBUG` | `local`, `true` | Production phải là `production` và `false`; script deploy từ chối giá trị khác. |
| `APP_URL` | — | Origin HTTPS công khai, ví dụ `https://example.com`. Không có path, không có `/` ở cuối. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `vi`, `en` | Ngôn ngữ giao diện mặc định và ngôn ngữ dự phòng. |
| `INSTANCE_ID` | — | UUID v4, tạo một lần. App gắn ID này với hostname; ID khác nghĩa là một thư viện khác. |
| `INSTANCE_NAME` | `APP_NAME` | Hiện cho người dùng trước khi đăng nhập và trong trang pháp lý. |
| `INSTANCE_BASE_URL` | `APP_URL` | Phải bằng `APP_URL`. |
| `INSTANCE_CONTACT_EMAIL` | trống | Hiện trong `/terms` và `/privacy` để nhận yêu cầu về dữ liệu và khiếu nại bản quyền. |
| `INSTANCE_REGISTRATION_MODE` | `closed` | `closed` (không đăng ký, không lời mời), `invite_only` (cần mã mời) hoặc `open` (đăng ký công khai; chỉ bật sau khi tự thêm biện pháp chống lạm dụng). |
| `INSTANCE_MAX_USERS`, `INSTANCE_MAX_ACTIVE_INVITES` | `100`, `20` | Giới hạn số tài khoản và số lời mời chưa dùng. |
| `INSTANCE_MINIMUM_MOBILE_VERSION` | `1.0.0` | Được công bố trong manifest của instance. |

Manifest tại `/.well-known/indie-house` trả `503` cho tới khi `INSTANCE_ID` là UUID, `INSTANCE_BASE_URL` là URL `https` và issuer của JWT bằng đúng URL đó.

## Proxy và HTTPS

| Biến | Giá trị production | Lý do |
| --- | --- | --- |
| `PUBLIC_SCHEME` | `https` | Edge đặt `X-Forwarded-Proto` theo biến này, nên client không tự chọn scheme được. |
| `TRUSTED_PROXIES` | `*` | Laravel tin edge trong mạng Compose private. Không bao giờ publish cổng của control plane. |
| `SESSION_SECURE_COOKIE` | `true` | Cookie session chỉ gửi qua HTTPS. |
| `EDGE_BIND_ADDRESS`, `EDGE_PORT` | `127.0.0.1`, `8080` | Edge phải ở loopback: ai gọi thẳng được edge đều giả được header IP của người dùng. |

## Database

| Biến | Ghi chú |
| --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT` | `pgsql`, `postgres`, `5432` với container đi kèm. Script deploy bắt buộc các giá trị này. |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Compose tạo container PostgreSQL bằng chúng. Không đặt `DB_URL`. |

Migration tìm kiếm tạo extension `unaccent` và `pg_trgm`, nên role database cần quyền `CREATE EXTENSION` lúc chạy migration.

## Redis

| Biến | Ghi chú |
| --- | --- |
| `REDIS_HOST`, `REDIS_PORT` | `redis`, `6379` (tên service trong Compose, không phải `127.0.0.1`). |
| `REDIS_PASSWORD` | Bắt buộc ở production, tối thiểu 32 ký tự. Khi có giá trị, Compose chạy Redis với `requirepass` và mọi client dùng chung biến này. Để trống khi development. |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `redis` |
| `REDIS_QUEUE_RETRY_AFTER` | `630`. Phải lớn hơn `--timeout=600` của queue worker, nếu không job chậm sẽ bị nhận hai lần. |
| `SESSION_LIFETIME` | Số phút không hoạt động trước khi session web hết hạn (mặc định `120`). |

Phiên refresh của mobile nằm trong PostgreSQL, không nằm trong Redis.

## Object storage

Laravel và service Go dùng định dạng biến khác nhau:

| Biến | Ai dùng | Định dạng |
| --- | --- | --- |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_DEFAULT_REGION` | tất cả | Credential chỉ có quyền trên một bucket. Không dùng credential root của storage. |
| `AWS_ENDPOINT` | Laravel | URL đầy đủ mà client truy cập được, ví dụ `https://objects.example.com`. Upload từ trình duyệt và URL artwork được ký cho host này. |
| `AWS_INTERNAL_ENDPOINT` | Laravel | URL đầy đủ trong mạng nội bộ, không bắt buộc, ví dụ `http://10.0.0.5:9000`. Các lệnh S3 của chính Laravel đi thẳng tới đây, vẫn ký cho `AWS_ENDPOINT`. |
| `AWS_URL` | Laravel | Gốc của URL thường; kèm tên bucket khi dùng path-style. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Laravel | `true` với MinIO. |
| `MINIO_ENDPOINT`, `MINIO_USE_SSL` | service Go | `host:port` **không có** scheme, kèm `true`/`false`. Dùng địa chỉ mạng nội bộ để audio không đi ra ngoài rồi vòng lại máy chủ. |
| `MINIO_PUBLIC_ENDPOINT`, `MINIO_PUBLIC_USE_SSL` | gateway | `host[:port]` công khai dùng để ký presigned URL. Để trống thì dùng `MINIO_ENDPOINT`. |

Nếu storage đứng sau Cloudflare, tạo một Cache Rule **bỏ qua cache** cho hostname của storage. Nếu không, Cloudflare biến lần `HEAD` đầu tới object `.flac` hoặc `.jpg` mới thành `GET`, chữ ký S3 không còn khớp, storage trả `403`, và Laravel coi như file không tồn tại.

## Khoá ký

Access token và vé phát là JWT RS256. Control plane giữ private key; gateway chỉ nhận public key.

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/private.key
openssl pkey -in infrastructure/stream-keys/private.key -pubout -out infrastructure/stream-keys/public.key
chmod 600 infrastructure/stream-keys/private.key
```

| Biến | Mặc định | Ghi chú |
| --- | --- | --- |
| `STREAM_JWT_PRIVATE_KEY_PATH` | `/app/storage/keys/private.key` | Đường dẫn **trong** container; Compose mount `infrastructure/stream-keys` vào đó ở chế độ chỉ đọc. |
| `STREAM_JWT_KEY_ID` | `stream-v1` | `kid` của khoá đó. |
| `STREAM_JWT_MANAGED_KEYS_DIRECTORY` | `/app/storage/keys` | Nơi chứa khoá thêm từ trang quản trị, tên `<kid>.private.pem`. Nửa public đặt ở `infrastructure/stream-public-keys/<kid>.public.pem`. |
| `STREAM_JWT_ISSUER` | — | Phải bằng đúng `APP_URL`. Đổi giá trị này làm mọi token mất hiệu lực. |
| `STREAM_JWT_AUDIENCE` | `indie-house-mobile` | Phải khớp audience của gateway; Compose truyền cùng giá trị. |

Cách xoay khoá có trong [Vận hành](operations.md#xoay-khoá-ký).

## Streaming và phiên

| Biến | Mặc định | Ghi chú |
| --- | --- | --- |
| `INTERNAL_API_KEY` | — | Control plane, media worker và gateway dùng chung cho `/api/internal/*`. Khi biến trống, control plane từ chối mọi request nội bộ. |
| `STREAM_JWT_ACCESS_TTL_MINUTES` | `15` | Thời hạn access token mobile (tối đa 60). |
| `STREAM_REFRESH_TTL_DAYS` | `30` | Thời hạn phiên refresh mobile. |
| `STREAM_MAX_SESSIONS_PER_USER` | `10` | Số phiên mobile hoạt động tối đa của một tài khoản. |
| `STREAM_AUTH_RATE_LIMIT_STORE` | `redis` | Nơi lưu bộ đếm giới hạn đăng nhập mobile. |
| `STREAM_DEFAULT_HQ_ENABLED` | `true` | Giá trị claim `hq` cho user chưa có chính sách stream. |
| `STREAM_WEB_AUDIO_VIA_GATEWAY` | `true` | Audio web redirect sang gateway kèm vé phát. Development đặt `false`. |
| `STREAM_PLAYBACK_TICKET_TTL_MINUTES` | `60` | 5–360. Vé bị lộ chỉ phát được một bài ở một chất lượng cho tới khi hết hạn. |
| `STREAM_CACHE_INVALIDATION` | `true` | Báo thay đổi của bài lên `stream:invalidate` để gateway bỏ kết quả đã cache. |
| `CATALOGUE_CACHE_TTL_SECONDS` | `300` | Thời gian gateway cache một lần tra cứu; `0` tắt cache. |

## Sao lưu và giám sát

| Biến | Mặc định | Ghi chú |
| --- | --- | --- |
| `BACKUP_ENCRYPTION_KEY` | — | Bắt buộc ở production (từ 32 ký tự). Mọi file trong bản sao lưu được mã hoá AES-256 bằng khoá này. Giữ một bản ngoài máy chủ: mất khoá thì không khôi phục được bản nào. |
| `MONITOR_BACKUP_MAX_AGE_HOURS` | `26` | `instance:health` báo lỗi khi bản sao lưu mới nhất cũ hơn ngưỡng này. |
| `MONITOR_MIN_DISK_FREE_GB` | `10` | Ngưỡng dung lượng đĩa trống cho `instance:health`. |

Lịch sao lưu, thời gian giữ và ổ offsite không phải biến môi trường; đặt chúng trong trang quản trị, mục **Hệ thống → Cài đặt sao lưu**.

## Checklist production

Script deploy dừng trước khi đụng tới bất cứ thứ gì nếu `.env` không có:

- `APP_ENV=production`, `APP_DEBUG=false`
- `PUBLIC_SCHEME=https`, `TRUSTED_PROXIES=*`, `SESSION_SECURE_COOKIE=true`, `EDGE_BIND_ADDRESS=127.0.0.1`
- `APP_URL` dạng `https://`, với `INSTANCE_BASE_URL` và `STREAM_JWT_ISSUER` bằng đúng giá trị đó
- `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432` và không có `DB_URL`
- `REDIS_PASSWORD` và `BACKUP_ENCRYPTION_KEY` dài ít nhất 32 ký tự
- `infrastructure/stream-keys/private.key` và `public.key`

Kiểm tra Compose đọc được file mà không in secret ra màn hình:

```bash
docker compose config --quiet
```

## Biến của gateway và worker

Compose ánh xạ các biến này từ `.env`; hiếm khi bạn phải tự đặt.

**stream-service:** `LISTEN_ADDR`, `CONTROL_PLANE_INTERNAL_URL`, `INTERNAL_API_KEY`, `REDIS_ADDR`, `REDIS_PASSWORD`, `MINIO_*`, `JWT_PUBLIC_KEY_PATH`, `JWT_PUBLIC_KEYS_DIRECTORY`, `JWT_LEGACY_KEY_ID`, `JWT_ISSUER`, `JWT_AUDIENCE`, `CATALOGUE_CACHE_TTL_SECONDS`. Các biến tinh chỉnh không bắt buộc, kèm mặc định:

| Biến | Mặc định |
| --- | --- |
| `STREAM_RATE_LIMIT_PER_MINUTE` | `120` mỗi user |
| `PRESIGN_RATE_LIMIT_PER_MINUTE` | `30` mỗi user |
| `HLS_RATE_LIMIT_PER_MINUTE` | `600` mỗi user |
| `PRESIGN_TTL_SECONDS` | `300` |
| `UPSTREAM_TIMEOUT_SECONDS` | `10` |
| `REQUIRE_HQ_CLAIM` | `false` |

**media-worker:** `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_QUEUE`, `MINIO_ENDPOINT`, `MINIO_ACCESS_KEY`, `MINIO_SECRET_KEY`, `MINIO_BUCKET`, `MINIO_USE_SSL`, `INTERNAL_API_KEY`.
