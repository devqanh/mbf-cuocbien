---
name: vietqr-bank
description: "Chọn NH lái xe từ VietQR (lưu bin) + QR chuyển khoản ở popup \"chi cho lái\" lo-trinh"
metadata: 
  node_type: memory
  type: project
  originSessionId: 796005c8-78dc-47ff-827f-1058cd1cdbe3
---

Tài khoản NH lái xe dùng danh sách VietQR + dựng QR chuyển khoản.

**Helper `resources/js/trucking2/banks.js`:** `loadBanks()` fetch `https://api.vietqr.io/v2/banks` 1 lần, cache localStorage 30 ngày (key `trk_vietqr_banks_v1`) + RAM; `banksSync()` đọc cache đồng bộ; `findBank({bin,bank})` (khớp bin, fallback code/shortName cho data cũ); `vietqrImg({bin,account,name,amount,info,template})` → URL ảnh `img.vietqr.io/image/<bin>-<acc>-<template>.png`. Không có CSP nên gọi client-side OK.

**Lưu trữ:** bank object = `{bank(=shortName), bin, code, number, holder}`, cột `bank_accounts` (cast array) của TruckingDriver. `cleanBanks()` ở [[trucking-architecture]] HandlesTripAndDrivers giữ thêm bin/code.

**Cài đặt lái xe** (config/DriversManager.jsx): ô NH là `BankPicker` (Combo `strict` từ VietQR list, value=bin) — chỉ chọn, không gõ tự do.

**Popup "chi cho lái"** (pages/lo-trinh.jsx `PayBankBox`): khi chọn "Lái xe nhận tiền" → hiện NH + STK (nút copy) + nút "Mã QR" (ẩn mặc định cho gọn) hiện QR VietQR với amount=tổng chi, addInfo="Chi lai xe <bks> <ngày>". LoTrinhController boot `drivers` đổi từ pluck('name') → objects `[{name, banks}]` (Combo dùng `.map(d=>d.name)`).
