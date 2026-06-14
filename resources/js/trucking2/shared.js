(function(){var fit=function(){var el=document.getElementById('trk-root');if(!el)return;el.style.height=Math.max(360,window.innerHeight-el.getBoundingClientRect().top)+'px';};window.addEventListener('resize',fit);window.__fitTrkRoot=fit;setTimeout(fit,0);})();

/* Dialog xác nhận dùng chung (đẹp hơn window.confirm). Trả về Promise<boolean>.
   opts: { title, text(HTML), confirmText(HTML), cancelText, danger, icon } */
window.confirmAction = function(opts){
  opts = opts || {};
  var danger = !!opts.danger;
  var accent = danger ? 'var(--danger)' : 'var(--accent)';
  var weak = danger ? '#fce8e8' : 'var(--accent-weak)';
  var icon = opts.icon || (danger ? 'bi-exclamation-triangle' : 'bi-question-circle');
  return new Promise(function(resolve){
    var ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;z-index:3000;background:rgba(16,19,23,.40);backdrop-filter:blur(2px);display:grid;place-items:center;padding:24px;animation:fadeIn .12s ease;';
    var card = document.createElement('div');
    card.style.cssText = 'width:min(440px,100%);background:#fff;border-radius:14px;box-shadow:0 24px 50px -16px rgba(16,19,23,.45),0 8px 24px -12px rgba(16,19,23,.25);overflow:hidden;font-family:inherit;';
    card.innerHTML =
      '<div style="padding:20px 22px 4px;display:flex;align-items:flex-start;gap:13px;">'
      + '<div style="width:38px;height:38px;border-radius:10px;flex-shrink:0;display:grid;place-items:center;background:'+weak+';color:'+accent+';font-size:18px;"><i class="bi '+icon+'"></i></div>'
      + '<div style="flex:1;min-width:0;padding-top:1px;">'
      + '<div style="font-size:16px;font-weight:700;color:var(--ink);letter-spacing:-.01em;">'+(opts.title||'Xác nhận')+'</div>'
      + '<div style="font-size:13.5px;color:var(--ink-2);margin-top:5px;line-height:1.55;">'+(opts.text||'')+'</div>'
      + '</div></div>'
      + '<div style="display:flex;justify-content:flex-end;gap:10px;padding:16px 22px 18px;">'
      + '<button data-a="cancel" style="padding:9px 18px;font-size:14px;font-weight:500;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink-2);cursor:pointer;">'+(opts.cancelText||'Hủy')+'</button>'
      + '<button data-a="ok" style="padding:9px 18px;font-size:14px;font-weight:600;border:none;border-radius:10px;background:'+accent+';color:#fff;cursor:pointer;box-shadow:0 1px 2px rgba(16,19,23,.2);">'+(opts.confirmText||'Đồng ý')+'</button>'
      + '</div>';
    ov.appendChild(card);
    document.body.appendChild(ov);
    var closed = false;
    function done(v){ if(closed) return; closed = true; document.removeEventListener('keydown', onKey); if(ov.parentNode) ov.parentNode.removeChild(ov); resolve(v); }
    function onKey(e){ if(e.key==='Escape') done(false); else if(e.key==='Enter') done(true); }
    ov.addEventListener('mousedown', function(e){ if(e.target===ov) done(false); });
    card.querySelector('[data-a=cancel]').onclick = function(){ done(false); };
    card.querySelector('[data-a=ok]').onclick = function(){ done(true); };
    document.addEventListener('keydown', onKey);
    var okb = card.querySelector('[data-a=ok]'); if(okb) okb.focus();
  });
};

/* Toast feedback nhẹ cho thao tác lưu/thêm/xoá. Auto-close 2.4s, stack ở góc phải.
   type: 'success' (mặc định) | 'error' | 'info' | 'warn' */
window.trkToast = function(msg, type){
  type = type || 'success';
  var palette = {
    success: { bg: '#16a34a', icon: 'bi-check-circle-fill' },
    error:   { bg: '#dc2626', icon: 'bi-exclamation-triangle-fill' },
    info:    { bg: '#2a6fdb', icon: 'bi-info-circle-fill' },
    warn:    { bg: '#e0a92e', icon: 'bi-exclamation-circle-fill' },
  };
  var p = palette[type] || palette.success;
  var stack = document.getElementById('trk-toast-stack');
  if (!stack){
    stack = document.createElement('div');
    stack.id = 'trk-toast-stack';
    stack.style.cssText = 'position:fixed;top:72px;right:16px;z-index:10000;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
    document.body.appendChild(stack);
  }
  var t = document.createElement('div');
  t.style.cssText = 'pointer-events:auto;background:#fff;color:#101317;border:1px solid var(--line);border-left:4px solid '+p.bg+';box-shadow:0 8px 24px -8px rgba(16,19,23,.22);border-radius:10px;padding:10px 14px;font-size:13.5px;font-weight:500;display:flex;align-items:center;gap:8px;min-width:200px;max-width:360px;transform:translateX(120%);transition:transform .22s ease,opacity .22s ease;opacity:0;';
  t.innerHTML = '<i class="bi '+p.icon+'" style="color:'+p.bg+';font-size:16px;flex:none;"></i><span style="line-height:1.35;">'+String(msg||'').replace(/</g,'&lt;')+'</span>';
  stack.appendChild(t);
  requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.style.transform='translateX(0)'; t.style.opacity='1'; }); });
  setTimeout(function(){
    t.style.transform = 'translateX(120%)'; t.style.opacity = '0';
    setTimeout(function(){ if (t.parentNode) t.parentNode.removeChild(t); }, 260);
  }, 2400);
};

/* Hộp LỖI HỆ THỐNG — hiện rõ (không tự tắt) để khách chụp màn hình gửi hỗ trợ.
   info: { status, message, url } */
window.trkError = function(info){
  info = info || {};
  var status = info.status || 0;
  var title = status >= 500 ? ('Lỗi máy chủ (mã ' + status + ')')
            : status === 0 ? 'Mất kết nối'
            : status ? ('Yêu cầu lỗi (mã ' + status + ')') : 'Đã xảy ra lỗi';
  var msg = info.message || 'Không xác định';
  var url = info.url || '';
  var when = new Date().toLocaleString('vi-VN');
  var esc = function(s){ return String(s==null?'':s).replace(/[&<>]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); };
  var old = document.getElementById('trk-error-ov'); if (old && old.parentNode) old.parentNode.removeChild(old);
  var ov = document.createElement('div'); ov.id = 'trk-error-ov';
  ov.style.cssText = 'position:fixed;inset:0;z-index:4000;background:rgba(16,19,23,.45);backdrop-filter:blur(2px);display:grid;place-items:center;padding:24px;';
  var card = document.createElement('div');
  card.style.cssText = 'width:min(520px,100%);background:#fff;border-radius:14px;box-shadow:0 24px 50px -16px rgba(16,19,23,.5);overflow:hidden;font-family:inherit;';
  card.innerHTML =
    '<div style="padding:20px 22px 4px;display:flex;align-items:flex-start;gap:13px;">'
    + '<div style="width:38px;height:38px;border-radius:10px;flex-shrink:0;display:grid;place-items:center;background:#fce8e8;color:#dc2626;font-size:18px;"><i class="bi bi-exclamation-octagon-fill"></i></div>'
    + '<div style="flex:1;min-width:0;padding-top:1px;">'
    + '<div style="font-size:16px;font-weight:700;color:var(--ink);">'+title+'</div>'
    + '<div style="font-size:13px;color:var(--ink-2);margin-top:6px;line-height:1.5;background:#f8f9fb;border:1px solid var(--line);border-radius:8px;padding:9px 11px;word-break:break-word;max-height:180px;overflow:auto;">'+esc(msg)+'</div>'
    + '<div style="font-size:11.5px;color:var(--ink-4);margin-top:8px;line-height:1.6;">'
      + (url?('<div><b>Đường dẫn:</b> '+esc(url)+'</div>'):'')
      + '<div><b>Thời gian:</b> '+esc(when)+'</div>'
      + '<div style="margin-top:6px;color:var(--ink-3);"><i class="bi bi-camera"></i> Vui lòng <b>chụp màn hình này</b> gửi bộ phận kỹ thuật để được hỗ trợ.</div>'
    + '</div></div></div>'
    + '<div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 22px 18px;">'
    + '<button data-a="copy" style="padding:8px 14px;font-size:13px;font-weight:500;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink-2);cursor:pointer;">Sao chép lỗi</button>'
    + '<button data-a="ok" style="padding:8px 16px;font-size:13px;font-weight:600;border:none;border-radius:9px;background:var(--accent);color:#fff;cursor:pointer;">Đóng</button>'
    + '</div>';
  ov.appendChild(card); document.body.appendChild(ov);
  function close(){ if (ov.parentNode) ov.parentNode.removeChild(ov); }
  ov.addEventListener('mousedown', function(e){ if (e.target===ov) close(); });
  card.querySelector('[data-a=ok]').onclick = close;
  card.querySelector('[data-a=copy]').onclick = function(){
    var txt = title + '\n' + msg + '\n' + (url?('Đường dẫn: '+url+'\n'):'') + 'Thời gian: ' + when;
    try { navigator.clipboard.writeText(txt); window.trkToast('Đã sao chép nội dung lỗi', 'info'); } catch(e){}
  };
};

/* Hộp "Phiên hết hạn" (lỗi 419) — KHÔNG báo lỗi kỹ thuật, chỉ hướng dẫn TẢI LẠI TRANG để đăng nhập lại. */
window.trkReloadPrompt = function(){
  if (document.getElementById('trk-reload-ov')) return;   // chỉ hiện 1 lần
  var ov = document.createElement('div'); ov.id = 'trk-reload-ov';
  ov.style.cssText = 'position:fixed;inset:0;z-index:4100;background:rgba(16,19,23,.45);backdrop-filter:blur(2px);display:grid;place-items:center;padding:24px;';
  var card = document.createElement('div');
  card.style.cssText = 'width:min(420px,100%);background:#fff;border-radius:16px;box-shadow:0 24px 50px -16px rgba(16,19,23,.5);overflow:hidden;font-family:inherit;text-align:center;padding:26px 22px 22px;';
  card.innerHTML =
    '<div style="width:52px;height:52px;border-radius:14px;display:grid;place-items:center;background:#fff5e6;color:#e0a92e;font-size:26px;margin:0 auto 14px;"><i class="bi bi-arrow-clockwise"></i></div>'
    + '<div style="font-size:17px;font-weight:800;color:#1b2330;">Phiên làm việc đã hết hạn</div>'
    + '<div style="font-size:14px;color:#5b6675;margin-top:8px;line-height:1.55;">Trang đã mở quá lâu nên cần tải lại. Bấm nút bên dưới để tải lại và đăng nhập lại.</div>'
    + '<button data-a="reload" style="margin-top:20px;width:100%;padding:14px;font-size:15.5px;font-weight:800;border:none;border-radius:13px;background:#2a6fdb;color:#fff;cursor:pointer;"><i class="bi bi-arrow-clockwise"></i> Tải lại trang</button>';
  ov.appendChild(card); document.body.appendChild(ov);
  card.querySelector('[data-a=reload]').onclick = function(){ window.location.reload(); };
};

/* Gọi API JSON CÓ XỬ LÝ LỖI: 4xx/5xx/mất mạng → hiện hộp lỗi rõ ràng + ném lỗi cho caller.
   Trả Promise<data> khi thành công. Dùng thay cho fetch(...).then(r=>r.json()) trong các trang. */
window.trkApi = function(method, url, body){
  var csrf = (window.__TRK||{}).csrf;
  return fetch(url, {
    method: method,
    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': csrf },
    body: body ? JSON.stringify(body) : undefined,
  }).then(function(res){
    return res.text().then(function(text){
      var data = null; try { data = text ? JSON.parse(text) : null; } catch(e){ data = null; }
      if (!res.ok){
        if (res.status === 419){ window.trkReloadPrompt(); var e419 = new Error('expired'); e419.status = 419; throw e419; }
        var msg = (data && (data.message || data.error))
          || (res.status===403 ? 'Bạn không có quyền thực hiện thao tác này.'
          :  res.status===404 ? 'Không tìm thấy dữ liệu/đường dẫn.'
          :  ('Máy chủ trả về lỗi ' + res.status + '.'));
        window.trkError({ status: res.status, message: msg, url: url });
        var err = new Error(msg); err.status = res.status; err.data = data; throw err;
      }
      return data;
    });
  }, function(netErr){
    window.trkError({ status: 0, message: 'Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại.', url: url });
    throw netErr;
  });
};

/* Upload FormData (file/ảnh) CÓ XỬ LÝ LỖI: 4xx/5xx/mạng → hộp lỗi rõ ràng + ném lỗi. */
window.trkUpload = function(method, url, formData){
  var csrf = (window.__TRK||{}).csrf;
  return fetch(url, { method: method, headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf }, body: formData }).then(function(res){
    return res.text().then(function(text){
      var data = null; try { data = text ? JSON.parse(text) : null; } catch(e){ data = null; }
      if (!res.ok){
        if (res.status === 419){ window.trkReloadPrompt(); var e419 = new Error('expired'); e419.status = 419; throw e419; }
        var msg = (data && (data.message || data.error))
          || (res.status===413 ? 'File quá lớn — vượt giới hạn cho phép.'
          :  res.status===422 ? 'File không hợp lệ (sai định dạng hoặc quá lớn).'
          :  ('Tải lên lỗi ' + res.status + '.'));
        window.trkError({ status: res.status, message: msg, url: url });
        var err = new Error(msg); err.status = res.status; err.data = data; throw err;
      }
      return data;
    });
  }, function(netErr){
    window.trkError({ status: 0, message: 'Không kết nối được máy chủ khi tải lên.', url: url });
    throw netErr;
  });
};

