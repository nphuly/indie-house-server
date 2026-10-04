# Bảo mật

[English](../en/security.md) · **Tiếng Việt**

Trang này mô tả cách Indie House bảo vệ một instance, những gì để lại cho người vận hành, và các giới hạn đã biết.

## Báo lỗ hổng

Đừng mở issue công khai. Dùng **Security → Report a vulnerability** trên repo GitHub để gửi báo cáo riêng. Nếu bạn dùng một instance và nghi nó bị xâm nhập, hãy liên hệ người vận hành instance đó.

## Ranh giới tin cậy

```text
Internet ──▶ HTTPS proxy ──▶ edge (chỉ loopback) ──▶ control-plane ──▶ PostgreSQL, Redis, storage
                                    │                     ▲
                                    └──▶ stream-service ──┘ internal API (chỉ trong mạng Compose)
```

- Chỉ edge publish cổng, trên `127.0.0.1`. PostgreSQL, Redis và internal API chỉ truy cập được trong mạng Compose private.
- Edge trả `404` cho `/api/internal/*`, kể cả các biến thể mã hoá và viết hoa của path.
- Edge đặt địa chỉ người dùng theo header của proxy và ghi đè `X-Forwarded-For`, `X-Forwarded-Proto`. Laravel chỉ tin hai header này, không bao giờ tin forwarded host.
- Service Go chỉ nhận những biến môi trường chúng cần. Chúng không bao giờ có `APP_KEY` hay credential database, và gateway không bao giờ có private key.

## Xác thực

| Lối vào | Cơ chế |
| --- | --- |
| Web | Cookie session Laravel (`Secure` ở production). Đổi mật khẩu sẽ kết thúc mọi phiên web khác và cookie "ghi nhớ đăng nhập". Redirect sau đăng nhập chỉ nhận URL cùng host. |
| Mobile API | Access JWT RS256 (15 phút) và refresh token không mang thông tin (30 ngày), xoay sau mỗi lần dùng và lưu dạng băm. Dùng lại refresh token cũ sẽ thu hồi phiên. |
| Gateway | Cùng access JWT, hoặc vé phát web gắn với một bài và một chất lượng. `token_use` tách hai loại này. |
| Internal API | `X-Internal-Key`, so với `INTERNAL_API_KEY`. Key trống thì mọi request bị từ chối. |

Việc kiểm tra JWT chỉ nhận `RS256` với `kid` hợp lệ, và kiểm tra `iss`, `aud`, `exp`, `nbf` ở cả control plane lẫn gateway. Khoá ký có thể xoay mà không gián đoạn dịch vụ; xem [Vận hành](operations.md#xoay-khoá-ký).

**Giới hạn tần suất** tính theo từng người vì IP người dùng lấy từ proxy:

- đăng nhập web: 5 lần mỗi phút mỗi email và 30 lần mỗi phút mỗi IP;
- đăng nhập mobile: theo email và theo IP; làm mới và đăng xuất: 30 lần mỗi phút mỗi IP;
- đăng ký: 10 lần mỗi phút; nhận lời mời: 5 lần mỗi phút;
- gateway: theo user và route, từ chối khi Redis không hoạt động.

**Phân quyền.** Playlist đi qua policy (chỉ chủ playlist được sửa), thu hồi thiết bị kiểm tra chủ sở hữu, và bản xuất dữ liệu chỉ có dữ liệu của người yêu cầu. Mọi truy vấn của người nghe dùng scope catalogue công khai, nên bài chưa xuất bản hoặc chưa xử lý xong không bao giờ lộ ra. Khu vực quản trị được bảo vệ bằng role; các trang bảo mật mobile chỉ super admin thấy.

## Tăng cứng trình duyệt

- **Content-Security-Policy.** Trang nghe nhạc, xác thực và `/legacy` chỉ chạy script cùng origin mang nonce của request: không `unsafe-inline`, không `unsafe-eval`. Trang quản trị cần script inline và `eval` cho Filament và Alpine, nhưng vẫn chỉ tải script từ chính origin của nó. Cả hai chính sách đặt `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` và `frame-ancestors 'self'`.
- **Header khác:** `Strict-Transport-Security: max-age=31536000` trên HTTPS, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- Trình duyệt không bao giờ nhận key trong storage hay presigned URL. Xem [Phát nhạc](playback.md).

## Xử lý dữ liệu đầu vào

- Upload bài hát bị giới hạn loại file, dung lượng và được chống sửa đường dẫn. Ảnh đại diện chỉ nhận JPEG, PNG hoặc WebP.
- Media worker chỉ nhận track ID dạng UUID và một danh sách đuôi file audio cố định, và chạy `ffmpeg`/`ffprobe` với `-protocol_whitelist file`, nên một file được tạo có chủ đích không khiến FFmpeg đọc được file hay URL khác.
- SQL thô luôn dùng tham số bind; tên cột tìm kiếm lấy từ một danh sách cố định.
- Khôi phục sao lưu kiểm tra mọi đường dẫn trong file zip trước khi giải nén, và tải bản sao lưu cần quyền của trang sao lưu.

## Bảo vệ dữ liệu

- Mật khẩu được băm bằng bcrypt. Refresh token và mã mời chỉ lưu dạng băm.
- Redis bắt buộc có mật khẩu ở production.
- Bản sao lưu được mã hoá AES-256; script deploy không chạy nếu thiếu khoá.
- Lịch sử nghe không lưu địa chỉ IP hay user agent. Log của gateway không có địa chỉ IP, token hay key của object, còn log của edge bỏ toàn bộ request.
- Trang nghe nhạc không gọi dịch vụ bên thứ ba nào. Dữ liệu được lưu có trong [Quyền riêng tư](privacy.md).

## Client

- **iOS** lưu token trong Keychain với `AfterFirstUnlockThisDeviceOnly`, bắt buộc HTTPS, và chỉ gửi token tới origin đã xác nhận.
- **Android (API 18)** mã hoá token bằng khoá Android Keystore khi thiết bị hỗ trợ, nếu không thì chỉ giữ trong bộ nhớ. Sao lưu ứng dụng bị tắt (`allowBackup="false"`). App kiểm tra chuỗi chứng chỉ và hostname, và mang theo chứng chỉ ISRG Root X1 làm root tin cậy bổ sung vì Android 4.3 không có sẵn.

## Giới hạn đã biết

Đây là các đánh đổi đã chấp nhận hoặc việc để lại cho người vận hành:

1. **Token sống tới khi hết hạn.** Sau khi thu hồi phiên hay khoá user, access token còn dùng được tối đa 15 phút và vé phát tối đa `STREAM_PLAYBACK_TICKET_TTL_MINUTES` (60).
2. **Khoá ký đã ngừng dùng** vẫn được chấp nhận chừng nào file còn được mount. Xoá chúng sau khi xoay khoá.
3. **CSP của trang quản trị yếu hơn** (`unsafe-inline`, `unsafe-eval`) vì Filament cần. Chỉ admin thấy các trang đó.
4. **HSTS chỉ áp cho hostname chính.** Bật cho subdomain ở proxy khi mọi subdomain đều có HTTPS.
5. **User database là superuser PostgreSQL** của container đi kèm. Một role riêng (vẫn cần `CREATE EXTENSION` lúc migration) để người vận hành tự làm.
6. **Quy tắc mật khẩu tối thiểu** (8 ký tự) và không kiểm tra mật khẩu đã bị lộ.
7. **Thời gian đăng nhập** hơi khác với email không tồn tại; giới hạn theo email làm chậm việc dò.
8. **Số lượt nghe tin cờ `completed` của client.** Lượt trùng trong một lần phát bị bỏ qua và việc ghi bị giới hạn tần suất, nhưng một user vẫn có thể thổi phồng lượt nghe.
9. **FrankenPHP chạy bằng root** trong container control plane.
10. **Trang quản trị tải font từ Bunny Fonts**, được cho phép trong CSP của nó.
11. **Một key internal API** dùng chung cho media worker và gateway.
12. **Tên file trong bản sao lưu không được mã hoá**, chỉ nội dung được mã hoá.
13. **Không có DRM.** Ai phát được bài thì ghi lại được; xem [Phát nhạc](playback.md#giới-hạn-của-việc-bảo-vệ).

## Checklist cho người vận hành

- [ ] `APP_DEBUG=false`, `APP_KEY` mạnh, không có secret nào trong Git.
- [ ] `APP_URL`, `INSTANCE_BASE_URL` và `STREAM_JWT_ISSUER` là cùng một origin HTTPS.
- [ ] Private key chỉ tài khoản deploy đọc được và được sao lưu dạng mã hoá; gateway chỉ có public key.
- [ ] Edge ở `127.0.0.1`; PostgreSQL, Redis, console của storage và internal API không bị mở ra ngoài.
- [ ] Bucket private và ứng dụng dùng credential chỉ có quyền trên bucket đó.
- [ ] Đã đặt `REDIS_PASSWORD` và `BACKUP_ENCRYPTION_KEY`, và khoá sao lưu được giữ ngoài máy chủ.
- [ ] Chế độ đăng ký là `invite_only` hoặc `closed`, và mỗi người có tài khoản riêng.
- [ ] Hệ thống giám sát cảnh báo theo `instance:health` và khi `401`, `429`, `5xx` tăng đột biến.
- [ ] `https://example.com/api/internal/tracks/status` trả `404`, và có header CSP lẫn HSTS.

## Kiểm tra định kỳ

```bash
cd src/control-plane && composer audit --locked && npm audit --omit=dev
cd src/stream-service && GOTOOLCHAIN=go1.27.1 go run golang.org/x/vuln/cmd/govulncheck@latest ./...
cd src/media-worker && GOTOOLCHAIN=go1.27.1 go run golang.org/x/vuln/cmd/govulncheck@latest ./...
```

Chạy `govulncheck` với đúng phiên bản Go của image; Go cũ hơn trên máy có thể báo lỗi của thư viện chuẩn không có trong bản build.
