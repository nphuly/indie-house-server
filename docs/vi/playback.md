# Phát nhạc và streaming

[English](../en/playback.md) · **Tiếng Việt**

Control plane quyết định ai được nghe bài nào ở chất lượng nào. Gateway viết bằng Go (`stream-service`) chuyển byte. Ở production, audio không bao giờ đi qua PHP, và client không bao giờ nhận key trong storage.

## Rendition

Media worker tạo các file sau trong `processed/{trackId}/`:

| Rendition | Định dạng | Ai nhận |
| --- | --- | --- |
| `preview_30` | MP3, 96 kbps, 30 giây đầu | Khách chưa đăng nhập |
| `mp3_128` | MP3, 128 kbps bitrate cố định | Người nghe đã đăng nhập, "128 kbps" |
| `mp3_320` | MP3, 320 kbps bitrate cố định | Người nghe đã đăng nhập, "320 kbps" |
| `lossless` | FLAC, 16-bit / 44,1 kHz stereo | Người nghe được cấp lossless, "Lossless" |

- Rendition MP3 là CBR thật. Worker không bao giờ thêm `-q:a`, vì tham số này đưa LAME sang bitrate thay đổi.
- FLAC chỉ được tạo khi `ffprobe` thấy nguồn lossless (FLAC, WAV/PCM, ALAC, WavPack, APE, TTA). File upload là MP3 thì không bao giờ có bản lossless. Nguồn có độ sâu bit hoặc tần số lấy mẫu cao hơn được chuyển bằng resampler soxr kèm dither tam giác, và `metaflac` thêm điểm tua mỗi 10 giây.
- Preview là một file encode riêng, không phải giới hạn thời gian trên bản đầy đủ. Khi bài chưa có preview, khách không phát được bài đó.
- Bài cũ có thể còn rendition HLS (`hls_128`, `hls_320`). Chúng được giữ lại, nhưng job mới không tạo HLS nữa.

## Lựa chọn chất lượng

Người nghe chọn trong **Cài đặt** (web) hoặc phần cài đặt của app. Giá trị API:

| Lựa chọn | Giá trị API | Phát | Nếu thiếu |
| --- | --- | --- | --- |
| 128 kbps | `standard` (cả giá trị cũ `auto`) | `mp3_128` | `mp3_320` |
| 320 kbps | `high` | `mp3_320` | `mp3_128` |
| Lossless | `lossless` | `lossless` | `mp3_320`, rồi `mp3_128` |

`lossless` chỉ hiện với user có quyền lossless (xem [Vận hành](operations.md#quyền-stream)). Nếu quyền bị thu hồi, lựa chọn `lossless` đã lưu sẽ phát 320 kbps. Nếu thiết bị không giải mã được FLAC, client chuyển xuống 320 kbps và báo một lần. `App\Services\Media\PlaybackRenditions` là nơi duy nhất ánh xạ lựa chọn sang rendition.

## Phát trên web

Thẻ `<audio>` không gửi được header `Authorization`, và trình duyệt di động chỉ cho `play()` nếu nguồn được gán ngay trong thao tác chạm. Vì vậy player web luôn dùng URL cùng origin:

```text
<audio src="/tracks/{publicId}/audio">
  → control plane: kiểm tra session, chọn rendition, ký vé phát
  → 302 /api/v1/stream/v1/play/{vé}   (Cache-Control: no-store)
  → gateway: kiểm tra vé, tra object, stream có hỗ trợ Range
```

**Vé phát** là JWT RS256 ký bằng cùng khoá với access token mobile, có `token_use: playback`, một bài (`trk`) và một chất lượng (`q`). Vé sống `STREAM_PLAYBACK_TICKET_TTL_MINUTES` (mặc định 60 phút). Gateway chỉ nhận vé ở `/v1/play/{vé}`, còn mobile API từ chối vé. Với khách, subject là `guest:` cộng một HMAC của session ID, để giới hạn tần suất tính riêng từng trình duyệt.

Nếu vé hết hạn giữa bài (ví dụ sau khi tạm dừng lâu), request `Range` tiếp theo bị từ chối. Player khi đó gọi lại URL cùng origin, nhận vé mới và phát tiếp từ đúng vị trí cũ.

Trang `/legacy` gọn nhẹ dành cho trình duyệt di động cũ cũng hoạt động như vậy.

## Phát trên app

App gọi thẳng gateway bằng access token của mình:

| Endpoint (dưới `/api/v1/stream`) | Mục đích |
| --- | --- |
| `GET /healthz` | Kiểm tra sống, không cần xác thực |
| `GET /v1/tracks/{publicId}/stream?quality=…` | Stream MP3 hoặc FLAC qua gateway |
| `GET /v1/tracks/{publicId}/stream?quality=…&delivery=redirect` | `302` sang một presigned URL ngắn hạn |
| `GET /v1/tracks/{publicId}/presigned-url?quality=…` | JSON gồm presigned URL, chất lượng thực tế và thời điểm hết hạn |
| `GET /v1/tracks/{publicId}/hls/{asset}` | Playlist và segment HLS của bài cũ |
| `GET /v1/play/{vé}` | Vé phát của web (không dành cho app) |

`quality` là `auto`, `standard`, `high` hoặc `lossless`. Request `lossless` mà token không có claim `lossless` sẽ phát như `high`, nên thu hồi quyền không làm nhạc dừng giữa bài. Public ID phải gồm 12 ký tự chữ và số, còn asset HLS chỉ nhận tên file playlist và segment, nên các route này không đọc được key tuỳ ý.

## Gateway làm gì

- **Token.** Chỉ nhận RS256 với `kid` đã biết, và kiểm tra `iss`, `aud`, `exp`, `nbf` và `token_use`. Gateway chỉ giữ public key.
- **Giới hạn tần suất.** Cửa sổ cố định trong Redis theo user và route: mặc định 120 request stream, 30 presign và 600 HLS mỗi phút. Khi Redis lỗi, gateway từ chối request thay vì cho qua.
- **Tra cứu.** Khi cache miss, gateway hỏi internal API của control plane cần phục vụ object nào cho một bài và một chất lượng. Câu trả lời gồm key, MIME type và chất lượng thực tế; không thứ nào tới tay client.
- **Cache.** Kết quả tra cứu và kích thước object được cache trong `CATALOGUE_CACHE_TTL_SECONDS` (300). Khi trạng thái hiển thị hoặc rendition của một bài thay đổi, control plane gửi public ID của bài lên kênh Redis `stream:invalidate` sau khi transaction commit, và gateway xoá các mục đó ngay. Mỗi lần (re)subscribe, gateway xoá toàn bộ cache vì có thể đã lỡ tin. Một bộ đếm epoch ngăn lần tra cứu bắt đầu trước lệnh xoá ghi vào cache sau lệnh xoá.
- **Range.** Proxy một dải byte với `206`, `Content-Range` và `Accept-Ranges`, điều cần cho việc tua.
- **Presigned URL.** Mặc định có hạn 5 phút (tối đa 15 phút), kèm `Cache-Control: no-store`.
- **Access log.** Mỗi request một dòng JSON; xem [Vận hành](operations.md#giám-sát).

## Lịch sử nghe

- Client tạo một `playback_id` khi bài bắt đầu và ghi tối đa một sự kiện cho nó: khi hết bài, khi người nghe chuyển bài, hoặc khi đóng trang. Nghe dở được tính sau 10 giây; bài ngắn nghe hết luôn được tính.
- Server bỏ qua `(user, bài, playback_id)` lặp lại trong hai giờ. Lượt nghe hết bài tăng số lượt nghe của bài trong cùng transaction.
- Người nghe có thể tắt ghi lịch sử trong Cài đặt.
- Lịch sử hiện mỗi bài một lần, tại lần nghe gần nhất.

## Giới hạn của việc bảo vệ

Nếu thiết bị phát được audio không mã hoá thì thiết bị nhận được audio đó, và người cố ý vẫn ghi lại được. Indie House không dùng DRM. Điều nó đảm bảo hẹp hơn: file gốc không bao giờ bị lộ, storage luôn private, mọi request đều được kiểm tra quyền và giới hạn tần suất, không có link tải, và khách chỉ nhận file preview riêng.
