export default function initAdminSidebarState() {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggle = document.getElementById('menuToggle');
    if (!sidebar || !toggle) return;
    // Compatibility contract: 1/open = collapsed, 0 = expanded.
    const key = 'kientruc_admin_sidebar_open';
    const mobile = window.matchMedia('(max-width: 575.99px)');
    const mobileToggle = sidebar.querySelector('.admin-sidebar__mobile-toggle');
    const groups = [...sidebar.querySelectorAll('.admin-nav__toggle')];
    let collapsed = document.body.dataset.sidebarCollapsed === '1' || document.body.classList.contains('open');
    try {
        const saved = sessionStorage.getItem(key);
        if (saved === '1' || saved === '0') collapsed = saved === '1';
    } catch (_) {}
    const compact = () => !mobile.matches && collapsed;
    const tooltip = document.createElement('div');
    tooltip.id = 'admin-sidebar-tooltip';
    tooltip.className = 'admin-sidebar-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);
    let tooltipTarget = null;
    const hideTooltip = () => {
        tooltip.hidden = true;
        if (tooltipTarget) tooltipTarget.removeAttribute('aria-describedby');
        tooltipTarget = null;
    };
    const showTooltip = target => {
        hideTooltip();
        const link = target.closest('.admin-nav__link');
        if (!compact() || !link || link.closest('.admin-nav__children')) return;
        tooltipTarget = link;
        tooltip.textContent = link.querySelector('.admin-nav__label').textContent;
        tooltip.hidden = false;
        tooltip.style.top = Math.max(8, Math.min(link.getBoundingClientRect().top, innerHeight - tooltip.offsetHeight - 8)) + 'px';
        link.setAttribute('aria-describedby', tooltip.id);
    };
    sidebar.addEventListener('focusin', e => showTooltip(e.target));
    sidebar.addEventListener('focusout', hideTooltip);
    sidebar.addEventListener('mouseover', e => showTooltip(e.target));
    sidebar.addEventListener('mouseout', hideTooltip);
    sidebar.addEventListener('click', hideTooltip);
    const closeGroup = button => {
        button.setAttribute('aria-expanded', 'false');
        document.getElementById(button.getAttribute('aria-controls')).hidden = true;
    };
    const place = button => {
        const list = document.getElementById(button.getAttribute('aria-controls'));
        list.style.top = Math.max(8, Math.min(button.getBoundingClientRect().top, innerHeight - list.offsetHeight - 8)) + 'px';
    };
    const apply = () => {
        hideTooltip();
        document.body.classList.toggle('open', compact());
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Mở rộng thanh điều hướng' : 'Thu nhỏ thanh điều hướng');
        toggle.title = toggle.getAttribute('aria-label');
        groups.forEach(button => {
            const list = document.getElementById(button.getAttribute('aria-controls'));
            list.style.top = '';
            const open = !compact() && !!list.querySelector('[aria-current="page"]');
            button.setAttribute('aria-expanded', String(open));
            list.hidden = !open;
        });
    };
    toggle.addEventListener('click', () => {
        if (mobile.matches) return;
        collapsed = !collapsed;
        try { sessionStorage.setItem(key, collapsed ? '1' : '0'); } catch (_) {}
        document.cookie = key + '=' + (collapsed ? '1' : '0') + '; path=/; SameSite=Lax';
        apply();
    });
    groups.forEach(button => button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        if (compact()) groups.forEach(other => { if (other !== button) closeGroup(other); });
        button.setAttribute('aria-expanded', String(open));
        document.getElementById(button.getAttribute('aria-controls')).hidden = !open;
        if (open && compact()) place(button);
    }));
    const closeMobile = () => {
        sidebar.classList.remove('is-mobile-open');
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileToggle.setAttribute('aria-label', 'Mở menu quản trị');
    };
    mobileToggle.addEventListener('click', () => {
        const open = sidebar.classList.toggle('is-mobile-open');
        mobileToggle.setAttribute('aria-expanded', String(open));
        mobileToggle.setAttribute('aria-label', open ? 'Đóng menu quản trị' : 'Mở menu quản trị');
    });
    document.addEventListener('click', e => {
        if (!sidebar.contains(e.target) && compact()) groups.forEach(closeGroup);
        if (!sidebar.contains(e.target) && mobile.matches) closeMobile();
    });
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        hideTooltip();
        const open = groups.find(button => button.getAttribute('aria-expanded') === 'true' && button.parentElement.contains(document.activeElement));
        if (open) { closeGroup(open); open.focus(); }
        else if (mobile.matches && sidebar.classList.contains('is-mobile-open')) { closeMobile(); mobileToggle.focus(); }
    });
    sidebar.querySelector('.admin-sidebar__inner').addEventListener('scroll', () => {
        hideTooltip();
        if (compact()) groups.filter(b => b.getAttribute('aria-expanded') === 'true').forEach(place);
    });
    mobile.addEventListener('change', () => { closeMobile(); apply(); });
    window.addEventListener('resize', () => {
        if (compact()) groups.filter(b => b.getAttribute('aria-expanded') === 'true').forEach(place);
    });
    window.addEventListener('pageshow', apply);
    apply();
}
