const form = document.querySelector('[data-cost-form]');
if (form) {
    const result = document.querySelector('[data-cost-result]');
    const stale = document.querySelector('[data-cost-stale]');
    const typeNote = document.querySelector('[data-cost-type-note]');
    form.addEventListener('input', () => {
        if (stale) stale.hidden = false;
    });
    form.addEventListener('change', () => {
        if (stale) stale.hidden = false;
        typeNote.hidden = form.elements['form[type]'].value !== '3';
    });
    form.querySelectorAll('[data-cost-dimension]').forEach(input => {
        const validate = () => {
            const value = input.value.trim();
            input.setCustomValidity(value && (!/^\d+(\.\d+)?$/.test(value) || !Number.isFinite(Number(value)) || Number(value) <= 0)
                ? 'Vui lòng nhập số lớn hơn 0, ví dụ 4.5.' : '');
        };
        input.addEventListener('input', validate);
        validate();
    });
    form.querySelectorAll('[data-cost-optional-area]').forEach(input => {
        const validate = () => {
            const value = input.value.trim();
            input.setCustomValidity(value && !/^\d+(\.\d+)?$/.test(value)
                ? 'Vui lòng nhập diện tích (m²) là số không âm, hoặc bỏ trống.' : '');
        };
        input.addEventListener('input', validate);
        validate();
    });
    form.addEventListener('reset', event => {
        event.preventDefault();
        window.location.assign(form.dataset.resetUrl);
    });
    if (result?.dataset.costResult === 'ready') {
        result.focus({ preventScroll: true });
        result.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
}

// Popup nhận đơn giá chi tiết: tự mở 5 giây sau mỗi lần có kết quả dự toán.
// Khách đã gửi thông tin thì không tự mở lại trong phiên này; vẫn mở được
// bằng nút [data-cost-lead-open] trong khung kết quả.
const lead = document.querySelector('[data-cost-lead]');
if (lead && typeof lead.showModal === 'function') {
    const storage = {
        get: key => { try { return sessionStorage.getItem(key); } catch { return null; } },
        set: key => { try { sessionStorage.setItem(key, '1'); } catch { /* bỏ qua */ } },
    };
    // Chỉ đóng khi khách bấm nút Đóng (×): chặn Esc và bấm ra ngoài.
    let closingByButton = false;
    const open = () => { if (!lead.open) lead.showModal(); };
    const close = () => { closingByButton = true; lead.close(); };

    if (!storage.get('cost-lead-sent')) {
        setTimeout(() => {
            // Không chen ngang nếu khách đang sửa form để tính lại
            if (!document.querySelector('[data-cost-stale]')?.hidden) return;
            open();
        }, 5000);
    }
    document.querySelectorAll('[data-cost-lead-open]').forEach(btn => btn.addEventListener('click', open));
    lead.querySelector('[data-cost-lead-close]').addEventListener('click', close);
    lead.addEventListener('cancel', event => event.preventDefault());
    // Chrome vẫn cho Esc đóng dialog nếu bấm liên tiếp — mở lại nếu không phải do nút Đóng
    lead.addEventListener('close', () => {
        if (!closingByButton) lead.showModal();
        closingByButton = false;
    });

    const leadForm = lead.querySelector('[data-cost-lead-form]');
    const phone = leadForm.querySelector('[data-cost-lead-phone]');
    const validatePhone = () => {
        const value = phone.value.replace(/[\s.\-()]/g, '');
        // Giữ đồng bộ với CostLeadType::PHONE_PATTERN
        phone.setCustomValidity(value && !/^(0|\+84)[35789]\d{8}$/.test(value)
            ? 'Số điện thoại không hợp lệ, ví dụ 0901 234 567.' : '');
    };
    phone.addEventListener('input', validatePhone);
    // Gửi thành công: giữ popup, thay form bằng lời cảm ơn; khách tự bấm Đóng
    leadForm.addEventListener('ajax-form:success', () => {
        storage.set('cost-lead-sent');
        leadForm.hidden = true;
        lead.querySelector('[data-cost-lead-thanks]').hidden = false;
    });
}
