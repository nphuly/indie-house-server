# Phát triển local

[English](../en/local-development.md) · **Tiếng Việt**

Stack development chạy các service PHP trong Docker và bind-mount mã nguồn, nên sửa PHP là có hiệu lực ngay, không cần build lại. Vite chạy trên máy của bạn.

## Yêu cầu

- Docker kèm plugin Compose
- Node.js 22 và npm (cho Vite)
- OpenSSL (để tạo secret và khoá)
- Go 1.27 nếu bạn sửa service Go ngoài Docker
- Một bucket tương thích S3 (ví dụ MinIO chạy local) khi cần upload bài hát. Catalogue demo chạy được mà không cần bucket.

## Chạy lần đầu

```bash
cp .env.example .env
```

Sửa `.env`:

| Biến | Giá trị cho development |
| --- | --- |
| `APP_KEY` | Kết quả của `printf 'base64:%s\n' "$(openssl rand -base64 32)"` |
| `APP_URL` | `http://localhost` (control plane development nghe ở cổng 80) |
| `DB_USERNAME`, `DB_PASSWORD` | Giá trị tuỳ ý; Compose tạo database bằng chúng |
| `REDIS_PASSWORD` | Để trống |
| `AWS_*`, `MINIO_*` | Bucket của bạn, khi đã có (xem [Cấu hình](configuration.md#object-storage)) |

Tạo cặp khoá ký. Control plane cần nó để cấp token cho app, còn gateway mount nửa public:

```bash
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/private.key
openssl pkey -in infrastructure/stream-keys/private.key -pubout -out infrastructure/stream-keys/public.key
```

Hai thư mục khoá trong `infrastructure/` đều bị Git bỏ qua. Không bao giờ commit khoá, kể cả khoá development.

Build và khởi động:

```bash
make build
make start              # control-plane ở http://localhost, PostgreSQL và Redis ở 127.0.0.1
make composer-install   # cài vendor/ vào volume dùng chung
make migrate
make create-super-admin # hỏi tên, email và mật khẩu
```

Nạp catalogue demo (hai nghệ sĩ, hai album, sáu đoạn âm tổng hợp, và một tài khoản người nghe `test@example.com` / `password`):

```bash
docker compose --project-name indie-house -f docker-compose.yml -f docker-compose.development.yml \
  exec control-plane php artisan db:seed
```

Sau đó chạy Vite trên máy:

```bash
cd src/control-plane
npm ci
npm run dev
```

Mở <http://localhost> để vào web nghe nhạc và <http://localhost/admin> để vào trang quản trị. Tìm "Nox", "Mây" hoặc "Ambient" để thấy các bài demo.

## Cái gì chạy ở đâu

| Service | Hành vi khi development |
| --- | --- |
| `control-plane`, `queue-worker`, `scheduler` | Target `development` của image, có Xdebug, gắn tag `indie-house/control-plane:dev`. `src/control-plane` được mount vào `/app`; `vendor/` nằm trong volume dùng chung `control_plane_vendor`, nên chạy Composer trong container. |
| `media-worker` | Build từ `src/media-worker`. Cần object storage mới làm được việc. |
| `stream-service` | Nằm trong profile Compose `stream`, nên `make start` không chạy nó. Thêm `--profile stream` để chạy; service nghe ở cổng `8081`. |
| `postgres`, `redis` | Chỉ publish trên `127.0.0.1`. Redis không có mật khẩu khi development. |

Development không có Caddy edge, nên không gì định tuyến `/api/v1/stream/*`. File Compose development đặt `STREAM_WEB_AUDIO_VIA_GATEWAY=false` để Laravel tự stream audio cho web. Bài demo luôn do Laravel phục vụ vì nằm trên disk `public` local.

App iOS và Android chỉ kết nối instance HTTPS. Muốn thử app với máy của bạn, đặt một HTTPS tunnel trước một stack giống production, hoặc dùng instance staging.

## Lệnh hay dùng

```bash
make help                        # liệt kê target của Makefile
make logs SERVICE=control-plane  # theo dõi log một service
make restart
make rebuild                     # build lại image, bỏ cache
```

Các lệnh Artisan riêng của dự án có trong [Vận hành](operations.md#lệnh-artisan).

### Debug

Image development có Xdebug ở chế độ `debug` cho container web, kết nối tới `host.docker.internal` với server name `docker`. Đặt `XDEBUG_MODE: debug` cho `queue-worker` hoặc `scheduler` trong `docker-compose.development.yml` để debug job hoặc lệnh chạy theo lịch.

## Kiểm thử

```bash
make test                               # bộ test Laravel trong container development
cd src/stream-service && go test ./...
cd src/media-worker && go test ./...
sh infrastructure/scripts/test-deploy-release.sh
```

- Bộ test Laravel chạy trên SQLite in-memory, nên PHP trên máy cần `pdo_sqlite` nếu bạn chạy `composer test` ngoài Docker.
- View trong test render `@vite`, nên chạy `npm run build` một lần để có `public/build/manifest.json`.
- Chạy một test với `php artisan test --filter=TenTest`.
- Format PHP bằng `vendor/bin/pint`, Go bằng `gofmt`.

## Quy ước

- **Catalogue công khai.** Mọi truy vấn người nghe chạm tới đều dùng `Track::scopePublicCatalogue()`, kể cả eager load.
- **Public ID.** Route bind theo `public_id` 12 ký tự; không lộ khoá database.
- **Code chỉ chạy trên PostgreSQL.** Tìm kiếm dùng `unaccent` và `pg_trgm`. Code phụ thuộc chúng kiểm tra driver và có nhánh dự phòng, để bộ test SQLite vẫn chạy; test cho hành vi chỉ có ở Postgres được bỏ qua trên driver khác.
- **Content-Security-Policy.** Trang nghe nhạc, xác thực và `/legacy` chỉ chạy script cùng origin mang nonce của request: không `<script>` inline, không thuộc tính `on*=` (JSON trong `<script type="application/json">` thì được). Origin bên ngoài mới phải được thêm vào `SecurityHeaders`.
- **Bản dịch.** `lang/vi` và `lang/en` khớp nhau từng file, từng khoá; `LocaleTest` kiểm tra các nhóm của trang nghe nhạc. Chuỗi dùng trong `app.js` nằm ở `lang/*/listen_client.php`.
- **Cài đặt.** Cài đặt runtime nằm trong database. Đọc bằng `Settings::get($group, $key)` và gọi `Settings::forget($group)` sau khi ghi.
- **Upload.** Trang tạo mới dùng `PromotesPendingUploads`; không chuyển file upload bằng `Storage::move()` mà không kiểm tra kết quả.
- **Contract giữa hai ngôn ngữ.** Job media, callback nội bộ và kênh `stream:invalidate` dùng chung với Go. Sửa cả hai bên trong cùng một thay đổi.
- **Migration.** Phải chạy được với bản đang chạy, vì deploy chạy migration trước khi thay container và không bao giờ rollback migration.
- **Trang pháp lý.** Nếu bạn đổi những gì được lưu hay ghi log, hoặc thêm bên thứ ba, cập nhật `lang/{vi,en}/legal.php` và [Quyền riêng tư](privacy.md) trong cùng thay đổi.
