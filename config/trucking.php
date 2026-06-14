<?php

return [
    /*
     | Disk lưu file upload (tài liệu lái xe/xe, ảnh phiếu chi).
     | Đổi sang 's3' ở .env (TRUCKING_UPLOAD_DISK=s3) khi migrate lên S3.
     | File CŨ vẫn đọc đúng vì mỗi file lưu kèm 'disk' trong bảng trucking_attachments.
    */
    'upload_disk' => env('TRUCKING_UPLOAD_DISK', 'local'),
];
