# Hệ thiết kế "Biển hiệu"

[English](../en/design-system.md) · **Tiếng Việt**

Giao diện lấy cảm hứng từ Sài Gòn về đêm: biển hiệu neon, tiệm băng đĩa trong hẻm, mặt đường ướt phản chiếu ánh đèn. Web, app iOS và app Android dùng chung một bộ nhận diện, định nghĩa bằng các token trong `src/control-plane/resources/css/app.css`.

## Nguyên tắc

- **Artwork là trung tâm.** Nền tối có chiều sâu; trang chi tiết và trình phát lấy màu từ chính artwork. Không phủ neon toàn màn hình, không glassmorphism nặng, không gradient tím-xanh.
- **Vàng biển hiệu là tín hiệu, không phải trang trí.** Vàng chỉ dùng cho nút Play, tiến trình phát, bài đang phát và trạng thái đang chọn. Nút phụ dùng nền sáng hoặc viền.
- **Góc vát thay cho bo tròn.** Nút Play, nút pill và artwork cắt chéo góc; nghệ sĩ và avatar là hình bát giác; các khối khác gần như vuông.
- **Mono cho dữ liệu.** Thời lượng, số đếm, số thứ tự, eyebrow, hostname và phiên bản dùng JetBrains Mono; mọi chữ khác dùng Chakra Petch.
- **Bố cục quen thuộc, nhận diện riêng.** Thư viện bên trái, trình phát bám đáy, hàng đợi bên phải (trên điện thoại: bốn tab, mini player và trình phát toàn màn hình). Không dùng màu, font hay logo của dịch vụ khác.
- **Mọi trạng thái đều được thiết kế:** skeleton khi đang tải, empty state gợi ý bước tiếp theo, lỗi có nút thử lại, nút đang bận có nhãn riêng.
- **Gọi đúng tên.** Tiêu đề trang nói trang đó là gì ("Khám phá", "Thư viện", "Cài đặt"). Không khẩu hiệu hay ẩn dụ, không đánh số mục, không dấu chấm than. Mọi câu đều có bản tiếng Việt và tiếng Anh.

## Màu

| Token | Giá trị | Dùng cho |
| --- | --- | --- |
| `--ih-bg` | `#07090E` | Khung ứng dụng và nền trình phát |
| `--ih-surface` | `#0E1118` | Panel: thư viện, nội dung, hàng đợi |
| `--ih-surface-raised` | `#151A25` | Card khi hover, mini player trên mobile, thẻ nổi |
| `--ih-surface-hover` | `#1D2432` | Menu, trạng thái nhấn |
| `--ih-text` | `#EEF1F6` | Chữ chính, nút sáng |
| `--ih-text-soft` | `#BCC3D0` | Mô tả và metadata cần đọc được |
| `--ih-muted` | `#858EA0` | Metadata, eyebrow, icon chưa active |
| `--ih-faint` | `#566074` | Placeholder, chú thích rất phụ |
| `--ih-accent` | `#F7E23B` | Vàng biển hiệu: Play, tiến trình, trạng thái active |
| `--ih-accent-bright` | `#FFF07A` | Nút vàng khi hover hoặc nhấn |
| `--ih-ink` | `#0A0B0F` | Chữ và icon trên nền vàng hoặc nền sáng |
| `--ih-pink` | `#FF3D81` | Hồng karaoke: avatar, nhãn "Nghe nhiều nhất" |
| `--ih-cyan` | `#27E3EC` | Xanh neon: viền focus |
| `--ih-line` | `rgb(238 241 246 / 12%)` | Viền và đường chia |
| `--ih-danger` | `#FF5A4F` | Hành động phá huỷ và lỗi |

Placeholder artwork dùng sáu tone chọn theo `crc32(seed) % 6`, cùng thứ tự trên mọi nền tảng: vàng `#F7E23B`, hồng `#FF3D81`, xanh neon `#27E3EC`, tím `#9D7CFF`, cam `#FF8A3D`, xanh dương `#5B8CFF`.

## Chữ

- **Chakra Petch** (400–700) cho mọi chữ giao diện, **JetBrains Mono** (400–500) cho số và dữ liệu. Cả hai dùng giấy phép SIL Open Font License, đủ dấu tiếng Việt, và được tự host (bản build web đóng gói sẵn; app mang theo bản đã cắt gọn).
- Tiêu đề dùng độ đậm 700, giãn chữ −0,01em và line-height không dưới 1,05, để dấu tiếng Việt không chạm dòng trên. Tiêu đề album và playlist dài được thu nhỏ dần.
- Eyebrow là chữ mono 11px viết hoa, màu `--ih-muted`.

## Hình khối và texture

| Token | Hình | Dùng cho |
| --- | --- | --- |
| `--ih-clip-art` | Vát góc trên phải, tối đa 18px | Artwork |
| `--ih-clip-play` | Vát góc trên phải và dưới trái, 28% cạnh | Nút Play, ô icon của empty state |
| `--ih-clip-octagon` | Vát bốn góc, 22% | Nghệ sĩ, avatar |
| trong `.button` | Vát hai góc 9px, viền vẽ lại bằng gradient | Nút, chip |

`clip-path` cắt cả outline và shadow, nên phần tử có góc vát vẽ viền focus vào trong (`outline-offset` âm). Bán kính (`--ih-radius-*`) giữ ở 2–4px, và thứ tự lớp dùng thang `--ih-z-*`.

**Lớp hologram.** Artwork đã tải có một lớp phủ nhẹ: scanline 1px cách nhau 3px, ánh xanh neon mờ ở một góc và hồng ở góc đối diện, viền hồng mảnh bên trái và xanh bên phải. Khi hover, một vệt sáng xanh–hồng lướt qua; artwork trong trình phát lặp lại vệt sáng chậm rãi khi đang phát. Placeholder không có lớp này, và lớp phủ không bao giờ được làm tối, làm mờ hay đổi màu ảnh. `prefers-reduced-motion` tắt chuyển động.

## Nhận diện

- Logo là dấu neon `ih` trong `src/control-plane/public/images/indie-house-mark.svg`: nét vuông màu vàng biển hiệu, có lõi sáng và quầng sáng, không có khung. Wordmark là "INDIE HOUSE" bằng Chakra Petch 700, viết hoa, giãn chữ 0,1em. Logo cần nền tối.
- `python3 scripts/generate-brand-assets.py` dựng icon app, ảnh splash và favicon từ file SVG đó.
- Icon lấy từ một bộ duy nhất (`App\Support\Icon`: lưới 24px, một độ dày nét). `scripts/generate-legacy-android-assets.py` xuất cùng bộ glyph thành PNG cho Android và PDF template cho iOS. Không dùng icon font hay ký tự đặc biệt.
- Ảnh hero, một tiệm băng đĩa trong hẻm Sài Gòn về đêm, được vẽ hoàn toàn bằng code trong `scripts/generate-record-shop-hero.py`: không có ảnh chụp, ảnh stock hay logo của bên thứ ba.

## Bố cục

```text
Desktop (≥ 1200px)
┌ logo ‹ › ─────── ⌂ [ Tìm bài hát, nghệ sĩ, album ] ─────────── VI|EN avatar ┐
├─ Thư viện ─────┬──────────── panel nội dung ─────────────────┬ Hàng đợi ────┤
│ Lịch sử nghe   │ hero lấy màu từ artwork                      │ (bật/tắt)    │
│ playlist …     │ action bar · header dính khi cuộn             │              │
├────────────────┴───────────────────────────────────────────────┴─────────────┤
│ bài đang phát │ ⤮ ⏮ ▶ ⏭ ⟲   0:42 ━━━━━━▮──── 3:12 │ hàng đợi · âm lượng      │
└──────────────────────────────────────────────────────────────────────────────┘

Tablet (768–1199px): thư viện thu thành thanh icon 80px; hàng đợi phủ lên nội dung.

Điện thoại (< 768px)
┌ logo ───────────────── VI|EN avatar ┐
│ nội dung tràn viền                  │
│ ┌ mini player (chạm để mở) ──────┐  │
└ Trang chủ · Tìm kiếm · Khám phá · Thư viện ┘
```

Kệ cuộn ngang và có nút ‹ › khi tràn; lưới xuống dòng từ sáu cột xuống hai cột theo bề rộng của section. Trình phát luôn chừa chỗ ở đáy để không che hàng cuối.

## Thành phần chính

- **Nút Play:** khối vàng vát hai góc, icon màu `ink`. Khi gắn với một ngữ cảnh đang phát, nút đổi thành Tạm dừng.
- **Hero của trang chi tiết** (album, nghệ sĩ, playlist): artwork 232px trên nền là chính artwork làm mờ kèm scanline, eyebrow mono, tiêu đề lớn và một dòng metadata ngắn. Playlist hiện mosaic bốn bìa khi có đủ bìa khác nhau.
- **Dòng bài hát:** chạm để phát. Số thứ tự thành nút phát khi hover và thành equalizer vàng ở bài hiện tại; tiêu đề bài đó chuyển vàng, có vạch vàng bên trái. Menu `⋯` thêm vào playlist, tạo playlist, tới album hoặc nghệ sĩ, hoặc xoá bài khỏi playlist của chủ sở hữu.
- **Trình phát:** artwork, tiêu đề và nghệ sĩ có link, nút điều khiển, thanh tua có thumb dạng vạch đứng, hàng đợi, tắt tiếng và âm lượng. Khách thấy nhãn "Nghe thử 30 giây". Trên điện thoại, mini player có vạch tiến trình mảnh, chạm để mở trình phát toàn màn hình.
- **Empty state, toast và trang lỗi:** một icon, một tiêu đề ngắn nói thứ gì đang thiếu, một hành động. Các trang lỗi (403, 404, 419, 429, 500, 503) dùng chung một bố cục.

## Hành vi trình phát

Dùng chung cho web, iOS và Android:

- **Hàng đợi là cả danh sách** chứa bài được chạm (album, playlist, kết quả tìm kiếm, lịch sử…), bắt đầu từ bài đó. Mỗi danh sách có nhãn ngữ cảnh hiện ở "Đang phát từ".
- **Nút Play của danh sách** tạm dừng hoặc tiếp tục nếu chính danh sách đó đang phát; nếu không, phát danh sách từ bài đầu.
- **Phát ngẫu nhiên** giữ bài hiện tại ở đầu và xáo phần còn lại; tắt thì trở về thứ tự gốc tại bài hiện tại. Nút shuffle ở action bar bắt đầu từ một bài ngẫu nhiên.
- **Lặp lại** xoay vòng tắt → tất cả → một bài. Khi tắt lặp, hàng đợi dừng ở bài cuối.
- **Bài trước** tua về đầu bài nếu đã phát quá 3 giây (hoặc đang ở đầu hàng đợi mà không lặp tất cả); nếu không, lùi một bài.
- **Tự phát:** hết bài thì sang bài kế nếu tài khoản bật tự phát. Khi tắt tự phát, player web luôn dừng, kể cả khi đang lặp; app vẫn phát tiếp khi đang lặp tất cả.
- **Phát tiếp theo** chèn bài ngay sau bài hiện tại.
- **Lịch sử:** mỗi lần phát ghi một lượt, sau 10 giây hoặc khi hết bài (xem [Phát nhạc](playback.md#lịch-sử-nghe)). Lặp một bài tính là lượt mới.
- **Theo thiết bị:** shuffle và chế độ lặp. **Theo tài khoản:** chất lượng và tự phát.
- **Mạng chập chờn:** app thử lại trước khi báo lỗi. iOS thử lại mỗi đoạn audio tối đa ba lần (sau 0,5, 1 và 2 giây) khi mất kết nối, khi quá 15 giây không nhận được dữ liệu, hoặc khi gặp `5xx`, nhưng không thử lại với `4xx`. Android mở lại luồng bằng token mới và phát tiếp từ vị trí cũ, tối đa hai lần mỗi bài. Player web lấy vé phát mới và phát tiếp.
- **Sau khi lỗi,** bấm Play để phát tiếp chính bài đó từ chỗ đã dừng, vẫn tính là cùng một lượt.
- **Không phát được FLAC:** phát 320 kbps và báo một lần.

## Trợ năng

- Mọi nút icon có nhãn; viền focus màu xanh neon đủ tương phản; điểm chạm tối thiểu 44×44.
- Có link "Bỏ qua, tới nội dung", thanh tua dùng được bằng bàn phím và đọc được giá trị ("1:23 trên 3:45"), và phím tắt: `/` để tìm, `Space` để phát hoặc tạm dừng, `Esc` để đóng menu và sheet.
- Không thể hiện gì chỉ bằng màu: bài đang phát có equalizer, chế độ đang bật có chấm, lỗi có icon và chữ.
- Hành động phá huỷ (xoá playlist, thu hồi thiết bị) cần bấm lần hai trong 4 giây.
- `prefers-reduced-motion` bỏ hiệu ứng trượt và nảy, chỉ giữ fade.
- Không phát tiếng khi chưa có thao tác của người dùng. Dùng Media Session API (phát, tạm dừng, bài trước, bài sau, tua) khi trình duyệt hỗ trợ.

## Ngôn ngữ

Tiếng Việt là mặc định và tiếng Anh là lựa chọn thứ hai, đổi được bất cứ lúc nào mà không dừng nhạc.

| Nền tảng | Chuỗi | Nơi đổi |
| --- | --- | --- |
| Web | `src/control-plane/lang/{vi,en}`, hai bên cùng khoá (có test kiểm tra); `lang/*/listen_client.php` cho script của player | `VI \| EN` trên thanh trên cùng, trang đăng nhập và Cài đặt; lưu trong cookie `filament_language_switcher_locale` |
| API | Thông báo của server theo `Accept-Language`; ngôn ngữ không hỗ trợ nhận tiếng Việt | — |
| iOS | `IndieHouse/Resources/{vi,en}.lproj/Localizable.strings`, đọc qua `L()` | Màn kết nối, đăng nhập và Cài đặt |
| Android | `res/values` (vi) và `res/values-en` | Màn kết nối, đăng nhập và Cài đặt |
