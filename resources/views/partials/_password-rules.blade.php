{{--
    Gợi ý mật khẩu mạnh: tích từng điều kiện khi gõ + chặn submit khi chưa đạt / nhập lại không khớp.
    Điều kiện phải khớp Password::defaults() ở AppServiceProvider (server vẫn kiểm tra lại).
    @include('partials._password-rules', ['input' => 'id ô mật khẩu', 'confirm' => 'id ô nhập lại'])
    Ô mật khẩu để trống được bỏ qua (form sửa thành viên: trống = không đổi), muốn bắt buộc thì gắn `required`.
--}}
<ul class="pw-rules" data-pw-input="{{ $input }}" data-pw-confirm="{{ $confirm ?? '' }}">
    <li data-rule="len">Tối thiểu 8 ký tự</li>
    <li data-rule="lower">Chữ thường (a-z)</li>
    <li data-rule="upper">Chữ hoa (A-Z)</li>
    <li data-rule="digit">Chữ số (0-9)</li>
    <li data-rule="symbol">Ký tự đặc biệt (!@#$…)</li>
    @if(! empty($confirm))
        <li data-rule="match">Nhập lại khớp</li>
    @endif
</ul>

@once
    @push('styles')
    <style>
        .pw-rules { list-style: none; padding: 0; margin: 8px 0 0; display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 12.5px; color: #64748b; }
        .pw-rules li::before { content: "○"; display: inline-block; width: 14px; }
        .pw-rules li.ok { color: #15803d; }
        .pw-rules li.ok::before { content: "✓"; font-weight: 700; }
    </style>
    @endpush
    @push('scripts')
    <script>
        (function () {
            const RULES = {
                len:    v => [...v].length >= 8,
                lower:  v => /\p{Ll}/u.test(v),
                upper:  v => /\p{Lu}/u.test(v),
                digit:  v => /\p{N}/u.test(v),
                symbol: v => /[\p{Z}\p{S}\p{P}]/u.test(v),
            };
            document.querySelectorAll('.pw-rules').forEach(list => {
                const pw = document.getElementById(list.dataset.pwInput);
                const cf = list.dataset.pwConfirm ? document.getElementById(list.dataset.pwConfirm) : null;
                if (! pw) return;
                const refresh = () => {
                    const v = pw.value;
                    let strong = true;
                    list.querySelectorAll('li').forEach(li => {
                        const rule = li.dataset.rule;
                        const ok = rule === 'match' ? (v !== '' && cf.value === v) : RULES[rule](v);
                        li.classList.toggle('ok', ok);
                        if (rule !== 'match' && ! ok) strong = false;
                    });
                    pw.setCustomValidity(v !== '' && ! strong ? 'Mật khẩu chưa đủ mạnh — xem các điều kiện bên dưới.' : '');
                    if (cf) cf.setCustomValidity(cf.value !== v ? 'Mật khẩu nhập lại không khớp.' : '');
                };
                pw.addEventListener('input', refresh);
                cf?.addEventListener('input', refresh);
                // Gán value bằng code (vd mở lại modal) không phát 'input' → nơi gọi phát 'pw:reset' để làm mới.
                pw.addEventListener('pw:reset', refresh);
                refresh();
            });
        })();
    </script>
    @endpush
@endonce
