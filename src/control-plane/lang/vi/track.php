<?php

return [
    'general' => [
        'model_label' => 'Bài hát',
        'title' => 'Tên bài hát',
        'slug' => 'Slug',
        'public_id' => 'Mã công khai',
        'genre' => 'Thể loại',
        'duration' => 'Thời lượng',
        'lyrics' => 'Lời bài hát',
        'status' => 'Trạng thái',
        'last_error_message' => 'Thông báo lỗi gần nhất',
        'processing_error_help' => 'Không thể xử lý tệp âm thanh. Hãy kiểm tra tệp gốc rồi thử xử lý lại.',
        'is_published' => 'Đã xuất bản?',
        'play_count' => 'Số lượt nghe',
        'artist' => 'Nghệ sĩ',
        'raw_file_key' => 'Tệp gốc',
        'renditions_count' => 'Bản encode',
        'updated_at' => 'Cập nhật lúc',
    ],
    'renditions' => [
        'quality_label' => 'Chất lượng',
        'format' => 'Định dạng',
        'bitrate' => 'Bitrate',
        'size' => 'Dung lượng',
        'file_key' => 'Đường dẫn file',
    ],
    'statuses' => [
        'pending' => 'Đang chờ',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn tất',
        'failed' => 'Thất bại',
        'unknown' => 'Không rõ',
    ],
    'actions' => [
        'preview' => 'Nghe thử',
        'reprocess' => 'Xử lý lại',
        'reprocess_selected' => 'Xử lý lại đã chọn',
        'reprocess_description' => 'Các bản encode hiện tại sẽ được xóa và track sẽ được đưa lại vào hàng đợi media-worker.',
        'close' => 'Đóng',
    ],
    'notifications' => [
        'processing_queued' => 'Đã đưa track vào hàng đợi xử lý.',
    ],
    'preview' => [
        'unavailable' => 'Chưa có file để nghe thử hoặc chưa tạo được URL tạm thời.',
        'play' => 'Phát bản nghe thử',
        'pause' => 'Tạm dừng bản nghe thử',
        'position' => 'Vị trí bản nghe thử',
    ],
    'sections' => [
        'basic' => 'Thông tin cơ bản',
        'file_processing' => 'Xử lý tệp',
        'renditions' => 'Các bản âm thanh',
    ],
];
