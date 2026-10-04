# Vận hành

[English](../en/operations.md) · **Tiếng Việt**

Các việc hằng ngày của người vận hành một instance. Phần lớn làm trong trang quản trị `/admin`; phần còn lại là lệnh Artisan chạy trong container `control-plane`.

## Trang quản trị

| Mục | Dùng để |
| --- | --- |
| **Tổng quan** | Số lượng trong catalogue và mã QR để app quét khi thêm instance. |
| **Nghệ sĩ, Album, Bài hát** | Catalogue: upload, metadata, xuất bản, xử lý lại. |
| **Lời mời** | Mã mời dùng một lần hoặc giới hạn số lần, có hạn dùng. |
| **Role** | Quyền của các role `super_admin`, `admin` và `user` (người nghe), quản lý bằng filament-shield. |
| **Bảo mật mobile** (chỉ super admin) | Phiên mobile, quyền stream, nhật ký JWT mobile và khoá ký JWT. |
| **Hệ thống** | Sao lưu dữ liệu và cài đặt sao lưu. |

Trang quản trị không bao giờ hiển thị token, refresh token hay private key.

## Thêm nhạc

1. Tạo **nghệ sĩ** (và **album**, nếu có).
2. Tạo **bài hát** và upload file audio: FLAC, WAV hoặc MP3, tối đa 500 MiB. Nguồn lossless (FLAC hoặc WAV) có thêm bản lossless.
3. Bài đi qua các trạng thái `pending` → `processing` → `completed` (hoặc `failed`, kèm thông báo lỗi trên bài).
4. Bật **Đã xuất bản?**. Chỉ bài vừa được xuất bản vừa xử lý xong mới hiện với người nghe.

**Xử lý lại** (một bài hoặc nhiều bài đã chọn) xoá các rendition audio hiện có và gửi bài lại cho media worker. Trong lúc xử lý, bài không công khai và ai đang nghe bài đó sẽ bị ngắt, nên hãy xử lý lại theo từng đợt vào lúc ít người nghe.

Chỉ upload nhạc mà bạn có quyền phân phối cho người nghe của mình.

## Tài khoản và quyền truy cập

### Đăng ký

`INSTANCE_REGISTRATION_MODE` quyết định cách tạo tài khoản:

| Chế độ | Hành vi |
| --- | --- |
| `closed` | Không đăng ký, không lời mời. Chỉ có các tài khoản tạo bằng `user:create-super-admin`, hợp với instance dùng một mình. |
| `invite_only` | Đăng ký cần mã mời hợp lệ, trên web (`/invite/{code}`) hoặc trong app. Nên dùng cho một nhóm bạn. |
| `open` | Ai cũng đăng ký được. Văn bản pháp lý và các lớp bảo vệ hiện viết cho nhóm được mời; rà soát cả hai trước khi mở instance ra công chúng. |

Mã mời chỉ được lưu dưới dạng băm. Mỗi lời mời có thể có hạn dùng và số lần dùng tối đa, và có thể thu hồi. `INSTANCE_MAX_USERS` và `INSTANCE_MAX_ACTIVE_INVITES` giới hạn tổng số.

Mỗi người nên có tài khoản riêng: thu hồi quyền, giới hạn tần suất, playlist và nhật ký đều tính theo từng user.

### Quyền stream

Trong **Bảo mật mobile → Quyền stream**, chính sách cho từng user có thể:

- cấp quyền nghe **lossless** (FLAC), mặc định tắt;
- đặt claim `hq` (chỉ có tác dụng khi gateway chạy với `REQUIRE_HQ_CLAIM=true`);
- khoá stream tới một thời điểm, kèm lý do.

Thay đổi có hiệu lực ngay trên web, và trên app ở lần cấp hoặc làm mới access token kế tiếp (mặc định trong vòng 15 phút).

### Thu hồi thiết bị

**Bảo mật mobile → Phiên mobile** liệt kê từng thiết bị đang đăng nhập với nền tảng, địa chỉ IP, lần dùng gần nhất và hạn. Bạn có thể thu hồi một phiên hoặc mọi phiên của một user. Người nghe cũng tự thu hồi thiết bị của mình trong **Cài đặt**.

Thu hồi chặn việc làm mới token ngay lập tức, nhưng access token đã cấp vẫn dùng được tới khi hết hạn (mặc định 15 phút), và vé phát web tới khi hết hạn của nó (mặc định 60 phút). Muốn khoá một người thật nhanh: khoá stream của họ, thu hồi mọi phiên, rồi đợi hết một vòng đời token.

**Nhật ký JWT mobile** ghi đăng nhập, làm mới, thu hồi, lần bị chặn, việc dùng lại refresh token cũ và kích hoạt khoá, không ghi credential.

## Xoay khoá ký

Gateway chấp nhận nhiều public key, chọn theo `kid`, nên token ký bằng khoá cũ và khoá mới cùng dùng được trong lúc xoay.

1. Tạo cặp khoá mới và tính fingerprint của public key:

   ```bash
   openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out infrastructure/stream-keys/stream-v2.private.pem
   openssl pkey -in infrastructure/stream-keys/stream-v2.private.pem -pubout -out infrastructure/stream-public-keys/stream-v2.public.pem
   openssl pkey -pubin -in infrastructure/stream-public-keys/stream-v2.public.pem -outform DER | sha256sum
   ```

2. Khởi động lại gateway để nó nạp public key mới: `docker compose up -d --force-recreate stream-service`.
3. Trong **Bảo mật mobile → Khóa ký JWT**, đăng ký `stream-v2` với fingerprint đó, rồi kích hoạt. Control plane kiểm tra private key trên đĩa khớp fingerprint trước khi chuyển.
4. Sau ít nhất một vòng đời access token (và một vòng đời vé phát), xoá file khoá cũ ở cả `infrastructure/stream-keys/` lẫn `infrastructure/stream-public-keys/`. Khoá đã ngừng dùng vẫn được chấp nhận chừng nào file còn được mount.

Không bao giờ dán private key vào trang quản trị và không bao giờ mount thư mục private key vào gateway. Nếu mất private key, kích hoạt một khoá mới; mọi app sẽ phải đăng nhập lại.

## Lệnh Artisan

Chạy bằng `docker compose exec control-plane php artisan <lệnh>`.

| Lệnh | Tác dụng |
| --- | --- |
| `user:create-super-admin` | Tạo hoặc nâng quyền một super admin, hỏi thông tin tương tác. |
| `instance:health [--json]` | Kiểm tra database, Redis, object storage, dung lượng đĩa, tuổi bản sao lưu và mã hoá sao lưu. Trả exit code khác 0 khi có mục lỗi. |
| `backup:run` | Sao lưu database và storage local ngay. |
| `backup:clean` | Áp dụng chính sách giữ bản sao lưu trong cài đặt sao lưu. |
| `media:purge-pending-uploads --hours=24` | Xoá file upload bị bỏ dở. Chạy hằng ngày theo lịch. |
| `media:repair-uploads [--dry-run]` | Sửa file upload bị kẹt ở chỗ tạm (xem bên dưới). |
| `tracks:backfill-previews [--dispatch] [--limit=N]` | Liệt kê bài chưa có preview 30 giây; với `--dispatch`, xếp job chỉ tạo preview. |
| `tracks:backfill-lossless [--dispatch] [--limit=N]` | Liệt kê bài có file gốc `.flac` hoặc `.wav` nhưng chưa có rendition FLAC; với `--dispatch`, xếp job chỉ tạo FLAC. |

Lệnh backfill không bao giờ tự chạy khi deploy. Bắt đầu với `--limit` nhỏ để kiểm tra kết quả.

### Sửa file upload bị kẹt

Nếu storage từ chối request trong một thời gian (ví dụ đứng sau Cloudflare mà thiếu rule bỏ qua cache), file upload có thể nằm lại trong `livewire-tmp/` hoặc `tracks/_pending/` trong khi database đã trỏ tới key cuối cùng.

1. Sửa nguyên nhân trước.
2. Nếu file đã kẹt gần 24 giờ, dừng lịch dọn dẹp ban đêm: `docker compose stop scheduler`.
3. Xem trước, rồi sửa:

   ```bash
   docker compose exec control-plane php artisan media:repair-uploads --dry-run
   docker compose exec control-plane php artisan media:repair-uploads
   ```

   Lệnh chuyển file về đúng key mà bản ghi trỏ tới, xếp lại job cho bài chưa xử lý xong, chuyển ảnh bìa nghệ sĩ và ảnh đại diện kiểu cũ lên object storage, và liệt kê bản ghi cần upload lại.
4. `docker compose start scheduler`.

## Giám sát

- **`instance:health --json`** dành cho bộ lập lịch hoặc hệ thống giám sát. Lệnh trả `ok` hoặc các mục lỗi, kèm ước lượng dung lượng database và media đã xử lý để vẽ xu hướng. Ngưỡng: `MONITOR_BACKUP_MAX_AGE_HOURS` (26) và `MONITOR_MIN_DISK_FREE_GB` (10).
- **`infrastructure/monitor/check-instance.sh`** chạy trên máy Docker. Script kiểm tra `/up` của edge, `/healthz` của gateway qua edge và `instance:health`, rồi đếm các response `401`, `429` và `5xx` trong log edge của năm phút gần nhất. Script trả exit code `1` khi vượt ngưỡng (`MONITOR_MAX_401`, `MONITOR_MAX_429`, `MONITOR_MAX_5XX`); nối exit code đó vào hệ thống cảnh báo của bạn.
- **Access log của gateway.** Mỗi request audio ghi một dòng JSON gồm status, số byte, thời gian từng chặng (`limit_ms`, `resolve_ms`, `stat_ms`, `get_ms`, `first_byte_ms`), chất lượng được xin và được phục vụ, kết quả cache và lý do lỗi. Log không có token, key của object, presigned URL hay địa chỉ IP.

  ```bash
  docker compose logs --no-log-prefix --since 24h stream-service | grep -E '"level":"(WARN|ERROR)"'
  docker compose logs --no-log-prefix --since 24h stream-service | grep -o '"failure":"[^"]*"' | sort | uniq -c | sort -rn
  ```

- **Log của edge.** Caddy chỉ ghi status và thời gian; toàn bộ object request bị bỏ, nên URL lời mời, token và cookie không bao giờ bị ghi.

Log container xoay vòng 5 × 10 MB mỗi service.

## Redis

Redis giữ queue và session web, chạy với AOF nên mất điện không làm mất dữ liệu. Không bao giờ khởi động một Redis chỉ có file RDB với `--appendonly yes`: nó sẽ khởi động rỗng. Script deploy chuyển server đang chạy sang AOF trước.

Khi có mật khẩu, `redis-cli` trong container chỉ xác thực qua `REDISCLI_AUTH`:

```bash
docker compose exec redis sh -c 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli ping'
```

## Dọn dẹp

- Trước mỗi lần migration, script deploy giữ một bản dump database đã kiểm tra trong `infrastructure/deploy/backups/`. Script không tự xoá dump cũ: chuyển chúng tới nơi lưu trữ an toàn và dọn theo lịch của bạn.
- Giữ thêm khoá mã hoá sao lưu, `.env` và khoá ký ở ngoài máy chủ. Xem [Sao lưu và khôi phục](backup-and-restore.md).
- Thỉnh thoảng chạy các lệnh kiểm tra thư viện trong [Bảo mật](security.md#kiểm-tra-định-kỳ).
