/* ===================================================================
 * Product tour dùng chung cho mọi trang (driver.js).
 * - Việt hóa nút: Tiếp / Quay lại / Xong + nút "Bỏ qua toàn bộ".
 * - Tự nhớ "đã xem" qua localStorage theo key.
 * - Lazy-load: trang nên dynamic import("@trk/tour.js") để chỉ tải khi cần.
 *
 * Dùng ở trang khác:
 *   import("@trk/tour.js").then(({ runTour, tourSeen }) => {
 *     if (!tourSeen("trk_tour_xxx")) runTour(STEPS, { key: "trk_tour_xxx" });
 *   });
 * STEPS = [{ element: '[data-tour="a"]', title, description, side?, align? }, ...]
 * (bước không có `element` → hiện popover giữa màn hình như modal)
 * =================================================================== */
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

let styled = false;
function ensureStyle() {
  if (styled) return; styled = true;
  const s = document.createElement("style");
  s.id = "trk-tour-style";
  s.textContent =
    ".driver-popover.trk-tour{border-radius:14px;box-shadow:0 16px 48px rgba(0,0,0,.34);max-width:340px;font-family:inherit;padding:16px}" +
    ".driver-popover.trk-tour .driver-popover-title{font-size:15px;font-weight:700;color:#101317}" +
    ".driver-popover.trk-tour .driver-popover-description{font-size:13px;line-height:1.55;color:#3a4452}" +
    ".driver-popover.trk-tour .driver-popover-progress-text{font-size:11.5px;font-weight:700;color:#2a6fdb}" +
    ".driver-popover.trk-tour .driver-popover-footer{margin-top:12px;gap:8px}" +
    ".driver-popover.trk-tour .driver-popover-next-btn{background:#2a6fdb;color:#fff;border:none;text-shadow:none;border-radius:9px;font-weight:700;font-size:13px;padding:7px 16px}" +
    ".driver-popover.trk-tour .driver-popover-prev-btn{border:1px solid #e3e7ec;background:#fff;color:#3a4452;border-radius:9px;font-weight:600;font-size:13px;padding:7px 13px;text-shadow:none}" +
    ".driver-popover.trk-tour .trk-tour-skip{background:transparent;border:none;color:#8a94a6;font-size:12.5px;font-weight:600;cursor:pointer;margin-right:auto;padding:6px 2px}" +
    ".driver-popover.trk-tour .trk-tour-skip:hover{color:#d64545}";
  document.head.appendChild(s);
}

export function tourSeen(key) {
  try { return !!(key && localStorage.getItem(key)); } catch (e) { return false; }
}

export function runTour(steps, opts = {}) {
  const { key, onDone } = opts;
  ensureStyle();
  // Bỏ các bước có mục tiêu nhưng phần tử đang ẩn/không tồn tại (vd control ẩn trên mobile).
  const valid = (steps || []).filter((s) => !s.element || document.querySelector(s.element));
  if (!valid.length) return;
  const mark = () => { try { if (key) localStorage.setItem(key, "1"); } catch (e) {} };

  let d;
  d = driver({
    showProgress: valid.length > 1,
    progressText: "{{current}}/{{total}}",
    nextBtnText: "Tiếp →",
    prevBtnText: "Quay lại",
    doneBtnText: "Xong",
    popoverClass: "trk-tour",
    allowClose: true,            // bấm nền hoặc Esc để đóng
    overlayColor: "rgba(15,20,30,.6)",
    stagePadding: 6,
    stageRadius: 10,
    steps: valid.map((s) => ({
      element: s.element,
      popover: { title: s.title, description: s.description, side: s.side || "bottom", align: s.align || "start" },
    })),
    // Chèn nút "Bỏ qua toàn bộ" vào footer mỗi bước.
    onPopoverRender: (popover) => {
      if (!popover || !popover.footerButtons) return;
      const skip = document.createElement("button");
      skip.type = "button";
      skip.innerText = "Bỏ qua toàn bộ";
      skip.className = "trk-tour-skip";
      skip.addEventListener("click", () => { try { d.destroy(); } catch (e) {} });
      popover.footerButtons.prepend(skip);
    },
    onDestroyed: () => { mark(); if (onDone) onDone(); },
  });
  d.drive();
  return d;
}
