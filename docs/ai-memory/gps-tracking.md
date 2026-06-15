---
name: gps-tracking
description: Theo dõi xe realtime (GPS) đa nhà cung cấp — proxy backend + auto-login + Google Maps
metadata:
  type: project
---

Trang **Theo dõi xe realtime** `/trucking-v2/theo-doi-xe` (`trucking2.tracking`, nav "Theo dõi xe", icon geo-alt). Gộp nhiều nhà cung cấp GPS, vẽ trên **Google Maps**, frontend poll **15s** (`tracking.positions`), tab ẩn thì ngừng poll.

**Kiến trúc backend = proxy + adapter** (ẩn credential + tránh CORS):
- `app/Services/Gps/AbstractGpsProvider.php`: login()/fetchRaw()/normalizeRaw() + quản phiên. **Cache token LÂU DÀI (30 ngày) — CHỈ login lại khi `fetchRaw()` trả NULL** (token hỏng) → relogin 1 lần rồi fetch lại. KHÔNG login theo lịch. Config lưu ở `TruckingSetting` key `gps.<key>` (JSON, **password mã hóa Crypt**). Phiên (cookie) ở Cache key `gps.session.<key>`.
- `ViettelProvider` (key `viettel`): login `POST /login1` JSON `{username,password}` → cookie `presence`. Data: `POST /portDataWithParamAndProjectId` (proxy `/api/devices/vtracking/vehicle/filter`), cần `org_ids`. Vị trí nằm trong attribute **`datas`** (JSON: latitude/longitude/speed/direction/ignition/geocoding/timestamp); driver ở attribute `driverName`.
- `DvbkProvider` (Bình Anh, key `dvbk`): login `POST /Login/LoginProcess` form → cookie `ASP.NET_SessionId`+`TrackingBKGPS`. Data: `POST /Home/get_AllTIBase` form `{UserID}` → mảng phẳng có sẵn `Lt/Ln/Speed/Angle/NumberPlate/NormalizedPlate/DriverName/Address/RealDate/AccHard`.
- `GpsTrackingService::snapshot()` cache **10s**: gộp provider bật + `matchVehicles()` map biển số chuẩn hóa về `trucking_vehicles` (qua `App\Support\PlateNormalizer::norm` = uppercase + bỏ ký tự không chữ/số). Shape vị trí chuẩn hóa: `{provider,providerLabel,plate,plateNorm,lat,lng,speed,angle,ignition,status,driver,address,ts,deviceId,matched,vehicleId,...}`. status: run/idle/off (frontend thêm `lost` khi ts > 10 phút).

**Auto-login (đã có endpoint login thật):** viettel `POST /login1` JSON `{username,password}` → cookie `presence`; dvbk `POST /Login/LoginProcess` form `{txtLoginName,txtPass,txtPassLayer2,SaveLogin}` → cookie `ASP.NET_SessionId`+`TrackingBKGPS`. login() đọc Set-Cookie qua `$resp->cookies()`.

**Controller** `TrackingController`: index (boot `providers` ẩn password + `mapsKey`) + positions (poll). Route view+positions quyền `shipments.view`. (config/saveConfig/test vẫn còn route nhưng UI cấu hình đã CHUYỂN sang trang Cài đặt.)

**CẤU HÌNH credentials Ở TRANG CÀI ĐẶT HỆ THỐNG** (KHÔNG ở trang theo dõi nữa): tab **"Giám sát hành trình"** (`/system-settings#gps`) — `SystemSettingController@index` trả `gps`(publicConfig keyBy key)+`mapsKey`, `@updateGps` (route `system.settings.gps`) lưu Google Maps key + viettel/dvbk (enabled/username/password/org_ids/user_id), password để trống = giữ nguyên. Nút "Test kết nối" ở tab này gọi AJAX route `trucking2.tracking.test`. Trang theo dõi: nút "Kết nối" + CTA empty-state chỉ là LINK tới `#gps`.

**Marker = HÌNH Ô TÔ nhìn từ trên** (`vehicleSvg(color,angle)` → SVG data-URI, url icon, xoay theo `angle`, thân tô màu status) + **nhãn biển số pill** dưới xe (Marker `label` className `.trk-plate`, CSS inject `ensurePlateStyle`). Marker **trượt mượt** tới vị trí mới ~1.2s (`tweenMarker`, easeInOutQuad, nhảy thẳng nếu lệch >0.08°) → cảm giác xe chạy realtime. Google Maps key ở `TruckingSetting` `gps.google_maps_key`, load script động.

**Tab mobile (Bản đồ/Danh sách)** lưu ở `sessionStorage["trk_track_view"]` + init từ đó → KHÔNG nhảy về "Bản đồ" mỗi 15s khi re-render/dựng lại. Card xe hiện **khoảng cách Haversine tới kho gần nhất** (whGeo) — "Cách kho X: 1.2km" / "Đã ở kho X" (≤300m, AT_WH_KM).

**Poll IM LẶNG:** sau 15s chỉ cập nhật, KHÔNG hiện spinner/loading; header chỉ chấm xanh "Trực tuyến · cập nhật Xs". InfoWindow chỉ refresh nội dung khi đang mở (không tự mở lại mỗi poll). Tab ẩn → ngừng poll. Marker chỉ `setIcon` khi đổi hướng(làm tròn 6°)/trạng thái (chữ ký `m.__sig`) → KHÔNG nạp lại ảnh = hết nháy.

**GOTCHA loading overlay:** `public/js/loading.js` MONKEY-PATCH `window.fetch` → mọi fetch (kể cả `trkApi`) bật overlay "Đang xử lý…" sau 600ms. Poll 15s bị dính → phải `window.AppLoading.addSilentPattern(/tracking\/positions/i)` (cơ chế có sẵn: SILENT_URL_PATTERNS / `silent:true` / header `X-Silent-Request:1`). Trang realtime nào poll ngầm đều phải đăng ký silent.

**GOTCHA giờ dvbk:** dvbk trả `RealDate` `/Date(ms)/` LỆCH +7h (encode giờ VN). Phải parse cột `Date` ("HH:MM:SS dd/MM/yyyy") theo `Asia/Ho_Chi_Minh` → epoch chuẩn (`DvbkProvider::dvbkTs`). Viettel `datas.timestamp` đã là UTC ms chuẩn.

**Đã seed (DB dev)**: viettel `mbf`/`Mbf0105040296@`, dvbk `ctymbf`/`123456` (chạy OK), org_ids/user_id thật. Test live: **23/23 xe đều có tọa độ** (viettel 13 + dvbk 10), cập nhật tươi vài giây.

**LIÊN KẾT xe hệ thống ↔ xe GPS:** `trucking_vehicles.gps_ref` = `"provider:deviceId"` (vd `dvbk:90003946`, `viettel:<uuid>`). Gán ở **Cài đặt → Đội xe** (`cai-dat#vehicles`): xe MBF có dropdown chọn xe GPS (chỉ xe MBF). `catalogData('vehicles')` trả thêm `vehicleGps` (map plate→ref) + `gpsVehicles` (list cho dropdown, gọi `GpsTrackingService::vehicleOptions()` — LAZY khi mở tab, cache 10s). `reconcileVehicles` chỉ ghi gps_ref khi payload có key `vehicleGps` (tránh xóa nhầm); CAT_KEYS.vehicles += `vehicleGps`. `matchVehicles` ƯU TIÊN gps_ref rồi mới dò biển số; trả thêm `vehiclePlate`. **Mục đích:** sau này tracking lô hàng (xe nào hoàn thành) chính xác, không phụ thuộc biển số khớp. Kho có cột `address` (mig `..._000004`) + `lat`/`lng` (mig `..._000006`): tab Kho có nút **"Ghim BĐ"** mở **MapPicker** (Google Maps + Places Autocomplete, key từ `gps.google_maps_key` truyền qua boot cai-dat) — tìm địa chỉ / bấm / kéo marker → lat,lng (tự điền địa chỉ nếu trống). Lưu qua `warehouseGeoArr` ("lat,lng" theo chỉ số) → `reconcileLookup` parse (`parseLatLng`). CAT_KEYS.warehouses += warehouseAddrArr/warehouseGeoArr. **`.pac-container` cần z-index 3000** để gợi ý nổi trên Modal. → SẴN SÀNG geofence.

**Ghim kho NGAY TRÊN BẢN ĐỒ theo dõi** (`theo-doi-xe`, nút "Vị trí kho", admin): marker 🏭 (WAREHOUSE_PIN) cho kho có tọa độ; panel liệt kê kho + nút "Đặt" → bấm lên bản đồ HOẶC **bấm vào xe đang đỗ** ở kho để lấy đúng tọa độ (trực quan); marker kho **kéo** được khi panel mở. API: GET `tracking.warehouses` (shipments.view), POST `tracking.warehouseGeo` {id,lat,lng} (settings.update). placingRef/placeFnRef (ref) để map/marker listener đọc giá trị mới nhất (tránh stale, vì listener gắn 1 lần).

**LỊCH SỬ ĐẾN/RỜI KHO (geofence visit)** — `trucking_warehouse_visits` (1 dòng = 1 lần ghé). Command `trucking:scan-warehouse-visits` (lịch `everyFiveMinutes` ở console.php, CẦN cron `schedule:run`) gọi `GpsTrackingService::scanVisits()`: máy trạng thái: VÀO mở visit với bán kính THEO TRẠNG THÁI — xe ĐANG CHẠY ≤ enter (**400m**, tránh tính xe chạy ngang), xe ĐỖ/TẮT MÁY/mất tín hiệu (speed≤1) ≤ parked (**1000m**, vì đỗ trong khuôn viên kho cách điểm ghim vài trăm m = đã ở kho); còn ≤ exit (**1500m**, phải > parked) giữ + cập nhật; RA > exit đóng (`departed_at = last_inside_at`); dwell < ngưỡng (10' theo **wall-clock** `created_at`, KHÔNG theo ts GPS vì xe đỗ báo thưa) → xóa (tạt ngang). Tham số cấu hình: `gps.geofence_enter_m/exit_m/dwell_min`. Lưu **snapshot biển số xe HỆ THỐNG** (`vehicle_plate`, vehicle_id) khi GPS khớp xe (cập nhật cả khi đang mở nếu mới gán gps_ref) → sau tính thời gian lô hàng tới kho theo xe. `arrived_at` = ts GPS (xe offline thì là mốc cuối). UI: trang riêng **`/trucking-v2/lich-su-kho`** (`tracking.visitsPage`, jsx `lich-su-kho.jsx`) phân trang + tìm kiếm server-side (`tracking.visits` JSON: q/page/perPage); nút "Lịch sử đến kho" trên trang theo dõi link sang. **History hiện visit confirmed HOẶC đang mở** (đang ở kho) để thấy ngay. **CHỈ kho có lat/lng mới phát hiện** — phải ghim hết kho. Danh sách xe trang theo dõi sort: xe ĐANG CHẠY + gần kho nhất lên đầu.

**TƯƠNG LAI (user dự định):** cảnh báo khi xe **đến gần kho** (geofence) — sẽ cần tọa độ lat/lng của KHO (trucking_warehouses/locations hiện CHƯA lưu tọa độ → phải thêm).

**LƯU Ý vận hành:**
- Entry Vite khai báo tường minh → thêm `theo-doi-xe.jsx` vào `vite.config.js`; **phải RESTART `npm run dev`** để entry mới được phục vụ.
- Cần cài `npm i leaflet`? KHÔNG — đã đổi sang Google Maps, đã gỡ leaflet.
- Map JS API key là client key (lộ ở browser là bình thường) — nên giới hạn HTTP referrer + bật "Maps JavaScript API" trong Google Cloud.

Liên quan [[trucking-architecture]], [[trucking-vite-architecture]], [[two-factor-auth]] (Crypt), [[ra-status-rule]].
