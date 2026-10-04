# Tài liệu Indie House

[English](../en/README.md) · **Tiếng Việt**

Indie House là dịch vụ nghe nhạc tự host, chỉ dành cho người được mời, phục vụ một nhóm nhỏ. Một người (*người vận hành*) chạy một instance trên máy chủ của mình, tải lên nhạc mà họ có quyền phân phối, rồi mời bạn bè. Người nghe dùng web, [app iOS](https://github.com/nphuly/indie-house-ios) hoặc [app Android cho BlackBerry Leap](https://github.com/nphuly/indie-house-android).

## Bắt đầu từ đâu

| Nếu bạn muốn… | Đọc |
| --- | --- |
| Hiểu các thành phần ghép với nhau thế nào | [Kiến trúc](architecture.md) |
| Chạy trên máy mình và sửa code | [Phát triển local](local-development.md) |
| Tự host một instance | [Triển khai](deployment.md), rồi [Cấu hình](configuration.md) |
| Vận hành instance hằng ngày | [Vận hành](operations.md) và [Sao lưu và khôi phục](backup-and-restore.md) |
| Hiểu âm thanh đến tai người nghe ra sao | [Phát nhạc và streaming](playback.md) |
| Viết client gọi API | [Mobile API](mobile-api.md) |
| Xem mô hình bảo mật | [Bảo mật](security.md) |
| Biết instance lưu những dữ liệu gì | [Quyền riêng tư và trang pháp lý](privacy.md) |
| Làm phần giao diện | [Hệ thiết kế](design-system.md) |

## Một instance có gì

**Người nghe**

- Duyệt catalogue, nghệ sĩ, album; tìm kiếm không cần gõ dấu và chấp nhận lỗi gõ nhỏ.
- Tạo playlist và xem lịch sử nghe.
- Chọn chất lượng phát: MP3 128 kbps, MP3 320 kbps hoặc FLAC lossless khi người vận hành cấp quyền.
- Dùng giao diện tiếng Việt hoặc tiếng Anh, trên web, iOS hoặc Android 4.3.
- Tự quản lý hồ sơ, mật khẩu, thiết bị đang đăng nhập và xuất dữ liệu trong trang cài đặt.

Khách chưa đăng nhập có thể duyệt catalogue và nghe thử 30 giây mỗi bài.

**Người vận hành**

- Trang quản trị (`/admin`) cho nghệ sĩ, album, bài hát, lời mời, role người dùng, quyền stream, phiên mobile, khoá ký JWT và sao lưu.
- Chế độ đăng ký: đóng, chỉ qua lời mời, hoặc mở.
- Sao lưu mã hoá theo lịch, có thể chép thêm một bản ra ngoài.
- Lệnh kiểm tra sức khoẻ và script giám sát để gắn cảnh báo.

## Cấu trúc repo

```text
src/control-plane/    Ứng dụng Laravel: web, admin, mobile API, internal API, job
src/media-worker/     Service Go chuyển mã file upload bằng FFmpeg
src/stream-service/   Cổng audio viết bằng Go
infrastructure/       Cấu hình Caddy edge, script deploy và giám sát, thư mục khoá
scripts/              Script sinh asset (icon, ảnh hero) cho web và hai app
docker-compose*.yml   File Compose dùng chung, development và production
```

## Quy ước trong tài liệu

- *Instance* là một bản cài: database, bucket object storage, khoá ký và người dùng của riêng nó.
- Lệnh bắt đầu bằng `docker compose` chạy ở thư mục gốc của repo. Ở production, lệnh giả định `.env` đã đặt `COMPOSE_FILE` và `COMPOSE_PROFILES` (xem [Triển khai](deployment.md)).
- `example.com` là hostname công khai của instance của bạn.
