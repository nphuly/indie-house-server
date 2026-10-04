# Quyền riêng tư và trang pháp lý

[English](../en/privacy.md) · **Tiếng Việt**

Indie House được làm để chỉ lưu những gì dịch vụ cần và không dùng dữ liệu nào cho mục đích thương mại: không bán hay chia sẻ dữ liệu, không quảng cáo, không công cụ phân tích. Dữ liệu của mỗi instance ở lại với người vận hành instance đó; tác giả phần mềm không nhận được gì.

## Trang điều khoản và quyền riêng tư

Mỗi instance có `/terms` (điều khoản sử dụng) và `/privacy` (chính sách quyền riêng tư) bằng tiếng Việt và tiếng Anh. App hiển thị cùng nội dung theo cách native qua `GET /api/v1/mobile/legal/{terms|privacy}`, nên sửa văn bản không cần phát hành bản app mới.

- Văn bản nằm trong `src/control-plane/lang/vi/legal.php` và `lang/en/legal.php`. Hai file phải có cùng khoá.
- `:instance` được thay bằng `INSTANCE_NAME`. Mục liên hệ hiện `INSTANCE_CONTACT_EMAIL`, hoặc ghi là liên hệ người vận hành đã gửi lời mời.
- Liên kết tới hai trang có ở chân trang web nghe nhạc, cạnh dòng đồng ý trên form đăng ký và nhận lời mời, trong phần lịch sử của Cài đặt, và cuối trang `/legacy`.
- Sau khi sửa, cập nhật khoá `updated` ở cả hai file.

Văn bản đi kèm viết cho một nhóm nhỏ được mời và mô tả đúng những dữ liệu bên dưới. Đó là điểm khởi đầu, không phải tư vấn pháp lý. Hãy rà soát theo pháp luật nơi bạn hoạt động; ở Việt Nam, đối chiếu với Nghị định 13/2023/NĐ-CP và Luật Bảo vệ dữ liệu cá nhân, nhất là về sự đồng ý và yêu cầu xoá dữ liệu.

Cập nhật văn bản mỗi khi bạn thay đổi những gì được lưu hay ghi log, thêm dịch vụ bên thứ ba (analytics, CDN, email, báo lỗi), đổi thời gian lưu, mở đăng ký công khai, hoặc không dùng Cloudflare.

## Instance lưu những gì

| Dữ liệu | Nơi lưu | Mục đích | Giữ bao lâu |
| --- | --- | --- | --- |
| Tên, email, hash mật khẩu, ảnh đại diện, token ghi nhớ đăng nhập | `users`; ảnh trong bucket private tại `avatars/{user_id}/` | Đăng nhập, hồ sơ | Tới khi xoá tài khoản (ảnh bị xoá cùng) |
| Chất lượng phát, tự phát, có ghi lịch sử không | `user_preferences` | Phát nhạc | Tới khi xoá tài khoản |
| Playlist và bài trong playlist | `playlists`, `playlist_tracks` | Thư viện | Tới khi xoá tài khoản |
| Bài, số giây đã nghe, nghe hết chưa, thời điểm | `user_play_logs` (không có IP, không có user agent) | Nghe gần đây, lịch sử, số lượt nghe | Chưa có hạn tự xoá |
| Tên thiết bị, nền tảng, địa chỉ IP, user agent, hash refresh token, lần dùng gần nhất, hạn | `mobile_sessions` | Đăng nhập app, thu hồi thiết bị | Hết hạn sau `STREAM_REFRESH_TTL_DAYS` (30); hàng vẫn giữ để audit |
| Sự kiện đăng nhập, làm mới, thu hồi trên app, kèm IP và thiết bị | `mobile_auth_events` | Phát hiện lạm dụng | Chưa có hạn tự xoá |
| Email người được mời (nếu có), IP lúc dùng mã mời | `invites`, `invite_redemptions` | Kiểm soát lời mời | Chưa có hạn tự xoá |
| Session web: người đăng nhập, CSRF token, ngôn ngữ, HMAC của hash mật khẩu | Redis | Đăng nhập web | `SESSION_LIFETIME` (120 phút không hoạt động) |
| Bộ đếm giới hạn tần suất theo email và IP dạng băm | Redis | Chống dò mật khẩu | 1–2 phút |
| Log gateway: ID user, bài, thời gian, 100 ký tự đầu của user agent, không có IP | Output của container, 5 × 10 MB | Theo dõi chất lượng phát | Tới khi log xoay vòng |
| Log edge: chỉ status và thời gian | Output của container, 5 × 10 MB | Cảnh báo 401/429/5xx | Tới khi log xoay vòng |
| Toàn bộ dữ liệu database ở trên | Bản sao lưu, mã hoá AES-256; bản offsite nếu được cấu hình | Khôi phục | Theo cài đặt giữ bản sao lưu |

Trên thiết bị của người nghe:

- **Cookie trình duyệt:** cookie session, `XSRF-TOKEN`, `filament_language_switcher_locale` (ngôn ngữ đã chọn) và cookie ghi nhớ đăng nhập khi người dùng chọn.
- **localStorage của trình duyệt:** bài đang nghe, âm lượng, trạng thái shuffle/lặp, trạng thái bảng hàng đợi và tìm kiếm gần đây.
- **iOS:** token trong Keychain; địa chỉ server, hồ sơ kết nối, tìm kiếm gần đây và tuỳ chọn trong `UserDefaults`.
- **Android:** token và tuỳ chọn trong bộ nhớ của app, đã tắt sao lưu ứng dụng.

## Bên thứ ba

Với cấu hình mặc định:

- **Cloudflare** (tunnel và proxy) thấy địa chỉ IP và lưu lượng của người truy cập.
- **Object storage** do người vận hành chọn.
- **Bunny Fonts** cung cấp font cho trang quản trị. Trang nghe nhạc không gọi dịch vụ bên ngoài nào.

## Người nghe tự kiểm soát

Trong **Cài đặt**, người nghe có thể đổi tên, email (cần mật khẩu hiện tại) và ảnh, tắt ghi lịch sử, đổi mật khẩu, thu hồi phiên app, và tải dữ liệu của mình dưới dạng JSON. Chưa có chức năng tự xoá tài khoản: người vận hành xoá theo yêu cầu, và việc này cũng xoá ảnh đại diện.
