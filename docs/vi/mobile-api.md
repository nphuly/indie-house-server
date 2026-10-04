# Mobile API

[English](../en/mobile-api.md) · **Tiếng Việt**

App [iOS](https://github.com/nphuly/indie-house-ios) và [Android](https://github.com/nphuly/indie-house-android) dùng một JSON API có đánh phiên bản. Client nào cho Indie House cũng dùng được theo cùng cách. Request và response đều là JSON; gửi `Accept-Language` (`vi` hoặc `en`) để nhận thông báo của server theo ngôn ngữ của app.

## 1. Tìm instance

Người dùng nhập địa chỉ HTTPS của instance hoặc quét mã QR trong trang quản trị. Client sau đó gọi:

```http
GET https://example.com/.well-known/indie-house
```

```json
{
  "schema_version": 1,
  "instance_id": "0d6f4afc-6a0e-4c5b-9a4e-2f1c9e1b7a10",
  "display_name": "Indie House",
  "api_base": "https://example.com/api/v1/mobile",
  "stream_base": "https://example.com/api/v1/stream",
  "jwt_issuer": "https://example.com",
  "registration_mode": "invite_only",
  "minimum_mobile_version": "1.0.0"
}
```

Quy tắc cho client:

1. Chỉ nhận `https`. Không đi theo redirect sang host khác, và từ chối manifest có endpoint trỏ tới host mà người dùng chưa xác nhận.
2. Hiện tên instance và hostname, cho người dùng xác nhận trước khi gửi mật khẩu.
3. Sau khi xác nhận, lưu một profile (`instance_id`, URL gốc, thời điểm xác nhận) và gắn `instance_id` với host. Nếu sau này host trả ID khác, cảnh báo và hỏi lại người dùng.
4. Chỉ gửi token tới origin đã xác nhận. Xoá profile thì xoá token của nó; không dùng token của instance này cho instance khác.
5. Dùng cách kiểm tra TLS mặc định của nền tảng.

## 2. Đăng nhập

Mọi route xác thực nằm dưới `/api/v1/mobile/auth`.

```http
POST /api/v1/mobile/auth/token
{"email": "listener@example.com", "password": "…", "device_name": "iPhone", "platform": "ios"}
```

```json
{"access_token": "eyJ…", "refresh_token": "96 ký tự hex", "token_type": "Bearer", "expires_in": 900}
```

| Route | Body | Kết quả |
| --- | --- | --- |
| `POST /token` | `email`, `password`, tuỳ chọn `device_name`, `platform` | `200` kèm cặp token, `401` khi sai thông tin |
| `POST /refresh` | `refresh_token`, tuỳ chọn `device_name`, `platform` | `200` kèm cặp token **mới**; refresh token cũ hết dùng được |
| `POST /logout` | `refresh_token` | `204`, gọi nhiều lần vẫn an toàn |

Response xác thực có `Cache-Control: no-store`.

- **Access token** là JWT RS256, mặc định có hạn 15 phút, với `token_use: access`. Gửi dưới dạng `Authorization: Bearer <token>`.
- **Refresh token** là chuỗi ngẫu nhiên không mang thông tin. Server chỉ lưu bản băm. Mặc định có hạn 30 ngày và được xoay sau mỗi lần làm mới.
- **Dùng lại một refresh token cũ sẽ thu hồi cả phiên** và được ghi vào nhật ký. Sau khi làm mới, lưu cặp token mới một cách nguyên tử rồi mới gửi lại request.
- Khi gặp `401`, chỉ cho một lần làm mới chạy tại một thời điểm. Nếu làm mới thất bại, xoá token đã lưu và yêu cầu người dùng đăng nhập lại.
- Giữ token trong Keychain (iOS) hoặc kho được bọc bằng Android Keystore. Không bao giờ ghi token vào log.

Claim của access token: `sub` (ID user), `iss`, `aud`, `iat`, `nbf`, `exp`, `jti`, `token_use`, `hq`, và `lossless` khi user có quyền lossless. Header mang `kid` của khoá ký.

### Tham gia bằng lời mời

Khi `registration_mode` là `invite_only`:

```http
POST /api/v1/mobile/invites/redeem
{"invite_code": "…64 ký tự…", "name": "…", "email": "…", "password": "…", "password_confirmation": "…"}
```

Tài khoản được tạo; sau đó đăng nhập bằng `/auth/token`.

## 3. Endpoint

Mọi route dưới đây nằm dưới `/api/v1/mobile` và cần access token hợp lệ, trừ `/legal/*`. Media luôn được định danh bằng `public_id`.

| Method | Path | Trả về |
| --- | --- | --- |
| GET | `/catalogue?page=N` | Bài đã xuất bản, nghe nhiều trước, 20 bài/trang, kèm `meta` |
| GET | `/browse` | Số lượng, 20 bài mới nhất, 12 album mới nhất, 12 nghệ sĩ nghe nhiều nhất |
| GET | `/albums?page=N` | Album có bài công khai, mới nhất trước, 24/trang |
| GET | `/artists?page=N` | Nghệ sĩ có bài công khai, A–Z, 24/trang |
| GET | `/search?q=…` | Bài hát, nghệ sĩ và album khớp |
| GET | `/tracks/{id}` | Chi tiết bài, gồm `stream_path` |
| GET | `/artists/{id}`, `/albums/{id}` | Nghệ sĩ hoặc album kèm các bài công khai theo thứ tự |
| GET | `/tracks/{id}/artwork`, `/artists/{id}/artwork`, `/albums/{id}/artwork` | Byte ảnh (cần Bearer) |
| GET | `/library` | Playlist của user và tối đa 30 bài nghe gần đây, mỗi bài một lần |
| GET, POST | `/playlists` | Danh sách, hoặc tạo mới với `title` (2–120 ký tự), `is_public`, tuỳ chọn `description` → `201` |
| GET, PATCH, DELETE | `/playlists/{id}` | Đọc, sửa hoặc xoá. Chỉ chủ playlist được sửa; playlist công khai người khác chỉ xem |
| POST | `/playlists/{id}/tracks` | Thêm `track_id` → `204` |
| DELETE | `/playlists/{id}/tracks/{trackId}` | Bỏ một bài → `204` |
| POST | `/play-logs` | Ghi một lượt nghe: `track_id`, `played_duration_seconds`, `completed`, `playback_id` → `201`, hoặc `204` khi lượt đó đã được ghi hoặc user tắt lịch sử |
| GET | `/devices` | Các phiên mobile của user |
| DELETE | `/devices/{sessionId}` | Thu hồi một phiên |
| GET, PATCH | `/preferences` | `audio_quality`, `autoplay`, `record_listening_history`, và `lossless_available` (chỉ đọc) |
| GET | `/account` | `name`, `email`, `avatar_url` (hoặc `null`) |
| GET | `/account/avatar/{version}` | Ảnh đại diện của user; `version` đổi khi ảnh đổi |
| GET | `/legal/terms`, `/legal/privacy` | **Không cần token.** Điều khoản hoặc chính sách quyền riêng tư của instance dạng JSON có cấu trúc, theo ngôn ngữ trong `Accept-Language` |

Mọi response về catalogue chỉ chứa bài đã xuất bản và đã xử lý xong. Giá trị `artwork_url` và `avatar_url` trỏ tới các route ở trên và cũng cần header Bearer.

`audio_quality` nhận `auto`, `standard`, `high`, và `lossless` chỉ khi `lossless_available` là `true`; nếu không, `lossless` bị từ chối với `422`. Xem [Phát nhạc](playback.md#lựa-chọn-chất-lượng).

## 4. Phát audio

`stream_path` là `/api/v1/stream/v1/tracks/{id}/stream` trên cùng origin. Gọi nó kèm access token và tuỳ chọn `quality` (`auto`, `standard`, `high`, `lossless`). Gateway hỗ trợ request `Range` một dải để tua. Các route khác của gateway có trong [Phát nhạc](playback.md#phát-trên-app).

## Lỗi và giới hạn

| Status | Ý nghĩa |
| --- | --- |
| `401` | Thiếu token, token hết hạn hoặc không hợp lệ, hoặc sai thông tin đăng nhập |
| `403` / `404` | Không có quyền, hoặc không tìm thấy (media chưa xuất bản trông như không tồn tại) |
| `422` | Lỗi dữ liệu gửi lên, kèm object `errors` của Laravel |
| `429` | Bị giới hạn tần suất |
| `503` | Manifest: instance chưa được cấu hình đủ |

Giới hạn tần suất: 120 request mỗi phút cho mỗi user trên các route cần xác thực; đăng nhập giới hạn theo email và theo IP; làm mới và đăng xuất 30 lần mỗi phút mỗi IP; nhận lời mời 5 lần mỗi phút; văn bản pháp lý 60 lần mỗi phút. Gateway áp giới hạn riêng theo user.
