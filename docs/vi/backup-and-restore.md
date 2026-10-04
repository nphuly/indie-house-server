# Sao lưu và khôi phục

[English](../en/backup-and-restore.md) · **Tiếng Việt**

Một instance có bốn loại dữ liệu. Sao lưu có sẵn của ứng dụng bao gồm loại đầu tiên và một phần loại thứ hai; phần còn lại do người vận hành tự lo.

| Dữ liệu | Nằm ở đâu | Sao lưu có sẵn có bao gồm không |
| --- | --- | --- |
| Tài khoản, metadata catalogue, playlist, lịch sử, phiên, cài đặt | PostgreSQL | Có |
| File trên đĩa local của control plane | `storage/app` trong container | Có |
| File upload, rendition, artwork, ảnh đại diện | Bucket object storage | **Không**: tự sao chép bucket |
| `.env`, `INSTANCE_ID`, khoá ký, cấu hình tunnel | Máy chủ | **Không**: giữ một bản mã hoá ở nơi khác |

Mất bucket là mất nhạc. Mất khoá ký thì mọi app phải đăng nhập lại. Mất `BACKUP_ENCRYPTION_KEY` thì mọi bản sao lưu làm bằng khoá đó không đọc được.

## Sao lưu có sẵn

**Hệ thống → Sao lưu dữ liệu** tạo bản sao lưu ngay và liệt kê các bản cũ. **Hệ thống → Cài đặt sao lưu** cấu hình:

- lịch chạy (biểu thức cron, mặc định `0 2 * * *`) và lịch dọn dẹp (mặc định `0 3 * * *`);
- thời gian giữ: giữ tối đa *N* bản gần nhất (mặc định 10) và không giữ bản nào cũ hơn *D* ngày (mặc định 30);
- ổ offsite: đặt `s3` để chép mỗi bản đã hoàn tất sang object storage.

Có thể chạy tương tự từ dòng lệnh bằng `backup:run` và `backup:clean`.

Mỗi bản sao lưu là một file zip gồm `database.sql` (dump PostgreSQL), `settings.json`, một manifest, và các file trên đĩa local của control plane. **Mọi file bên trong được mã hoá AES-256** bằng `BACKUP_ENCRYPTION_KEY`. Tên file trong zip không được mã hoá, chỉ nội dung được mã hoá.

- Thiếu khoá thì việc sao lưu báo lỗi chứ không ghi file zip không mã hoá. `instance:health` báo thiếu khoá ở mục `backup_encryption`.
- Lưu khoá trong trình quản lý mật khẩu, ngoài máy chủ.
- Mở file zip bằng tay thì dùng 7-Zip (`unzip` không đọc được AES): `7z x -p"$BACKUP_ENCRYPTION_KEY" backup-….zip`, trong thư mục chỉ bạn đọc được.
- Khi đổi khoá, giữ khoá cũ chừng nào còn bản sao lưu làm bằng nó. Tạo một bản mới ngay sau khi đổi.
- Bản sao lưu tạo trước khi có mã hoá vẫn khôi phục được, nhưng đó là file zip thường. Xoá chúng, cả bản offsite, khi đã có bản mã hoá.

## Một bản sao lưu đầy đủ

Để phòng sự cố lớn, lấy đủ các phần sau tại cùng một thời điểm:

1. **Database.** Bản sao lưu có sẵn, hoặc `pg_dump -Fc` để có dump dạng custom. Kiểm tra dump mà không cần import: `pg_restore --list backup.dump`.
2. **Bucket.** Sao chép bằng công cụ S3 (`mc mirror` hoặc `aws s3 sync`) rồi so số object và tổng dung lượng giữa nguồn và bản sao. Tạm dừng upload trong lúc chép để có bản chụp nhất quán.
3. **Secret.** `.env`, `infrastructure/stream-keys/`, `infrastructure/stream-public-keys/` và cấu hình tunnel hoặc proxy. Mã hoá gói này (ví dụ bằng `age`) trước khi đưa ra khỏi máy chủ.

Ghi instance ID, hostname và checksum SHA-256 vào một manifest để cạnh bản sao lưu, không chứa secret. Giữ ít nhất một bản ngoài máy chủ, và không bao giờ đưa dump hay khoá vào Git.

## Diễn tập khôi phục

Thử khôi phục định kỳ trên một **instance staging rỗng**, không bao giờ trên volume production:

1. Tạo một database rỗng và một bucket rỗng.
2. Khôi phục dump, sao chép media vào bucket mới và giải mã secret vào một thư mục có quyền hạn chế.
3. Cho staging một hostname HTTPS riêng. Nếu giữ nguyên `INSTANCE_ID`, đảm bảo không có app thật nào kết nối cả hai instance.
4. So số user, bài hát, playlist, rendition và object. Kiểm tra `/up`, `/api/v1/stream/healthz` và manifest, đăng nhập, làm mới token, phát và tua một bài, thu hồi một phiên.
5. Ghi lại thời gian, checksum và những gì phải sửa. Sau đó xoá staging.

## Khôi phục production

1. Đưa instance vào chế độ bảo trì và tạo một bản sao lưu cuối của trạng thái hiện tại.
2. Khôi phục database, bucket và secret từ **cùng một** bản chụp.
3. Khởi động service theo thứ tự phụ thuộc và kiểm tra giống như lúc diễn tập.
4. Mở lại instance.

Giữ bản chụp trước khi khôi phục cho tới khi kiểm tra xong. Không bao giờ xoá volume để "rollback".

Trang quản trị cũng khôi phục được một bản sao lưu do chính nó tạo. Nó import dump database, file local và cài đặt bằng khoá mã hoá hiện tại, và dừng trước khi thay đổi gì nếu khoá sai. Nó không khôi phục bucket hay secret.
