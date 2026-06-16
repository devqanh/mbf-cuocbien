---
name: route-trips
description: Trang Lộ trình lái xe theo chuyến (ca đêm 20:00→08:00) — dựng từ giờ xe ra + ra_mode
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

Trang **Lộ trình** `/trucking-v2/lo-trinh` (`trucking2.loTrinh`, nav "Lộ trình" cạnh Lô hàng, quyền `shipments.view`). Xem lộ trình từng xe trong 1 **NGÀY VẬN HÀNH = 08:00 ngày D → 08:00 ngày D+1** (24h; 07:59 hôm sau là hết — user chốt, ĐÃ SỬA từ 20:00→08:00 vì hiểu nhầm). Lọc theo ngày (nút ‹ › + Hôm nay).

**Hiển thị = 1 lộ trình liền mạch/ngày** (user: "thành 1 lộ trình trong 1 ngày, không chia theo lô khó nhìn"): mỗi xe 1 card, các hoạt động xếp trên **timeline dọc nối liền** (component `TripNode`: rail + chấm mốc màu theo mode). Mỗi nút = giờ ra + hành động (`actionNode`) + `PointChain` (Nơi lấy→Kho→Hạ) + **2 dòng chi tiết Xe vào / Xe ra** (user yêu cầu ghi rõ xe nào đưa cont số nào vào/ra) + KH/booking.
- **Xe vào**: `bks_vao` đưa cont `cont_no` vào (+ giờ xe đến). **none**: ghi "đã vào nơi lấy {from} lúc {gioDenLabel}" (user: không kéo cont thì ghi đã vào đâu theo giờ xe đến). **PointChain của none CHỈ hiện điểm nơi lấy** (`pts = points.filter(kind==='pickup')`) — KHÔNG vẽ tới kho/nơi hạ vì chưa giao cont (user: "xe không kéo cont thì hiện mỗi nơi lấy thôi"). self/other giữ chain đầy đủ.
- **Xe ra**: self → `bks_ra||bks_vao` đưa cont `cont_no` ra (cảnh báo nếu khác xe vào); other → `bks_vao` kéo cont khác `refCont` ra, kèm "(cont {refCont} do xe `refBksVao` đưa vào)"; none → ra không kéo cont.
- Leg thêm field: `refBksVao` (xe đưa cont-bị-kéo vào trước đó), `refBksRa` (xe kéo nó ra). Seed none case có `gio_xe_den` để demo "đã vào nhà máy".

**Logic** (`HandlesShipments::routeTripByDate($date)` → `LoTrinhController::data`): gom **theo `bks_vao`** (xe vào — user chốt). Mỗi lô = 1 hoạt động dưới bks_vao, xếp theo giờ xe ra, theo `ra_mode`:
- **self**: "Kéo cont [X] ra" tại `gio_xe_ra`.
- **none**: "Ra xe (không kéo cont)" tại `gio_xe_ra_xe`.
- **other**: "Kéo cont khác ([Y]) ra" tại `gio_xe_ra` của lô `ra_other_id`=Y (cont X chờ, X.gio_xe_ra trống), leg dưới `X.bks_vao` (xe kéo). **DEDUP QUAN TRỌNG**: cont Y (target) BỎ self-leg — exit của Y thuộc xe kéo qua leg other, KHÔNG tạo self-leg dưới Y.bks_vao (xe vào Y chỉ mang vào, không tự kéo Y ra) → tránh gắn nhầm xe / đếm 2 lần. (`$targetSet` = ids ra_other_id; self-leg chỉ khi `mode∉{none,other}` && không là target.)
- Cấu hình ở form lô hàng (popups.jsx): khối "Giờ xe ra này là của:" 3 nút Chính cont này(self)/Cont khác ra(other → chọn cont cùng chuyến + nhập giờ ra/BKS ghi sang cont đó)/Không kéo cont ra(none → gio_xe_ra_xe).
Chỉ tính hoạt động có giờ ra trong **[08:00 D, 08:00 D+1)** (`gte start && lt end`, biên 08:00 đầu ngày TÍNH, 08:00 hôm sau LOẠI; `$start=$date 08:00`, `$end=addDay()`). Trả `{date,start,end(ms),startLabel,endLabel,trucks:[{bks,matched,type,legs:[{time,timeLabel,gioDen,gioDenLabel,mode,cont,refCont,bksRa,points,route,from,to,customer,booking,hashid}]}],totalLegs}`.

**Hành trình điểm** (user: "nghiên cứu thành hành trình 1 lái xe"): mỗi leg có `points:[{label,kind:pickup|kho|drop}]` = Nơi lấy (`from_loc`) → các Kho (tách qua `khoPoints($kho)` PUBLIC trong HandlesTripAndDrivers) → Nơi hạ (`to_loc`), bỏ điểm trùng liền kề. Frontend render bằng `PointChain` (chip + mũi tên). Hiện thêm "vào kho {gioDenLabel} → ra {timeLabel}".

**TZ-safe**: `app.timezone=UTC` nhưng giờ lưu naive-local → `new Date(ms)` bị trình duyệt lệch ngày/giờ. ⇒ backend format SẴN `startLabel/endLabel` (d/m) + `timeLabel/gioDenLabel` (H:i); frontend dùng label, KHÔNG dùng fmtHm/fmtDM trên ms nữa.

**Seed test**: `php artisan trucking:seed-routes {date?}` tạo lô booking `RT-TEST-*`. ÁNH XẠ DANH MỤC THẬT (user nhấn mạnh — cần truy vấn nhanh sau): mỗi lô gọi `recomputeShipmentDerived` để gán `vehicle_id`/`from/to_location_id`/kho pivot. bks_vao **lấy từ xe đã cấu hình** (`TruckingVehicle::where('kind','vehicle')->pluck('plate')`, cột **`plate`**); **nếu fleet rỗng → tự tạo 5 xe DEMO** (`info.demo_route=true`, `--clear` xóa kèm, KHÔNG đụng xe thật). Khách hàng = KH thật đầu tiên (vd Canon). from=`ICDQV` to=`HPP` (mã địa điểm thật). Thêm 1 plate ảo `99X-000.00` test "(ngoài hệ thống)". Phủ: 2 self qua nửa đêm, none+self, kéo cont khác ra (ra_other_id), ban ngày 14:00 (TÍNH), biên 08:00 (TÍNH), 09:00 hôm sau (LOẠI — sang ngày kế). `$at($h)`: h>=8 ngày D, h<8 ngày D+1. Verify: 6 xe / 9 hoạt động (10 lô seed, 1 bị loại).

**Fix tách kho pivot**: `recomputeShipmentDerived` trước split `kho` chỉ theo dấu phẩy → tuyến dùng `→` bị gộp 1 dòng, `warehouse_id` null. Đã đổi sang cùng regex với `khoPoints` (`,|→|->|–|—|" - "`) → mỗi kho 1 dòng có `warehouse_id`. ÁP CHO CẢ LÔ THẬT.

**Mấu chốt dữ liệu** (xem [[ra-status-rule]]): `gio_xe_ra`=giờ CONT ra (self ghi vào chính lô; other ghi sang lô ra_other_id, lô gốc để TRỐNG); `gio_xe_ra_xe`=giờ XE ra khi none; `bks_vao`=xe kéo cont vào (đại diện lộ trình), `bks_ra`=xe kéo ra (có thể khác). Entry Vite `lo-trinh.jsx` (phải restart npm run dev). Liên quan [[gps-tracking]] (sau có thể đối chiếu lộ trình GPS thực với lộ trình khai báo).
