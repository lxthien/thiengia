// Kéo-thả sắp xếp + bật/tắt cho các màn hình quản lý dùng chung.
// Hợp đồng DOM xem templates/admin/components/_reorder_manager.html.twig.
(function () {
    var manager = function () { return document.querySelector('[data-manager]'); };
    if (!manager()) return;

    var $n = window.NestableJQuery;
    var mutationBusy = false;
    var updateControls = function () {};

    async function request(url, options) {
        options = options || {};
        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, 20000);
        try {
            var headers = Object.assign({'X-Requested-With': 'XMLHttpRequest'}, options.headers || {});
            var response = await fetch(url, Object.assign({}, options, {signal: controller.signal, headers: headers}));
            if (response.redirected && new URL(response.url).pathname === '/login') {
                throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang.');
            }
            return response;
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Chưa nhận được phản hồi. Hãy tải lại trang để kiểm tra trước khi thử lại.');
            }
            throw error;
        } finally {
            clearTimeout(timeout);
        }
    }

    async function jsonRequest(url, options) {
        var response = await request(url, options);
        if ((response.headers.get('content-type') || '').indexOf('application/json') === -1) {
            throw new Error('Không xác nhận được kết quả. Vui lòng tải lại trang để kiểm tra.');
        }
        var result = await response.json();
        if (!response.ok || result.success !== true) {
            throw new Error(result.message || 'Không lưu được thay đổi.');
        }
        return result;
    }

    function notice(message, isError) {
        var box = manager().querySelector('[data-manager-notice]');
        box.textContent = message;
        box.hidden = false;
        box.dataset.state = isError ? 'error' : 'success';
    }

    function busy(value) {
        mutationBusy = value;
        manager().setAttribute('aria-busy', String(value));
        updateControls();
    }

    async function refresh(message) {
        var response = await request(location.href);
        if (!response.ok) throw new Error('Đã xử lý yêu cầu nhưng chưa tải lại được danh sách. Vui lòng tải lại trang.');
        var next = new DOMParser()
            .parseFromString(await response.text(), 'text/html')
            .querySelector('[data-manager]');
        if (!next) throw new Error('Không tải được danh sách. Vui lòng tải lại trang.');
        var scroll = window.scrollY;
        var board = manager().querySelector('#manager-nestable');
        if ($n && $n(board).data('nestable')) $n(board).nestable('destroy');
        manager().replaceWith(next);
        mount();
        window.scrollTo(0, scroll);
        if (message) notice(message);
    }

    async function visibility(button) {
        busy(true);
        try {
            var body = new URLSearchParams({token: button.dataset.token, enable: button.dataset.enable});
            var result = await jsonRequest(button.dataset.managerVisibility, {method: 'POST', body: body});
            await refresh(result.message);
        } catch (error) {
            notice(error.message, true);
        } finally {
            busy(false);
        }
    }

    function mount() {
        var board = manager().querySelector('#manager-nestable');
        var list = board ? board.querySelector('#manager-list') : null;
        if (!list) return;

        var rows = function () {
            return Array.prototype.filter.call(list.children, function (row) {
                return row.classList.contains('dd-item');
            });
        };
        var ids = function () { return rows().map(function (row) { return Number(row.dataset.id); }); };
        var status = manager().querySelector('[data-manager-status]');
        var saved = ids();

        updateControls = function () {
            var all = rows();
            all.forEach(function (row, index) {
                row.querySelector('.banner-position').textContent = '#' + (index + 1);
                row.querySelector('[data-manager-move=up]').disabled = mutationBusy || index === 0;
                row.querySelector('[data-manager-move=down]').disabled = mutationBusy || index === all.length - 1;
            });
            board.dataset.saving = String(mutationBusy);
            board.setAttribute('aria-busy', String(mutationBusy));
        };

        async function persistOrder() {
            if (mutationBusy) return;
            var next = ids();
            if (JSON.stringify(saved) === JSON.stringify(next)) {
                updateControls();
                return;
            }
            busy(true);
            status.textContent = 'Đang lưu thứ tự…';
            status.dataset.state = 'saving';
            try {
                await jsonRequest(board.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({items: next, expected: saved, token: board.dataset.reorderToken})
                });
                saved = next;
                status.textContent = 'Đã lưu thứ tự.';
                status.dataset.state = 'success';
            } catch (error) {
                // Trả lại thứ tự cũ trên màn hình để không ai tưởng là đã lưu.
                var current = new Map(rows().map(function (row) { return [Number(row.dataset.id), row]; }));
                saved.forEach(function (id) { if (current.has(id)) list.appendChild(current.get(id)); });
                status.textContent = error.message + ' Đã khôi phục thứ tự trên màn hình; tải lại để kiểm tra.';
                status.dataset.state = 'error';
            } finally {
                busy(false);
            }
        }

        if (saved.length > 1 && $n && $n.fn && $n.fn.nestable) {
            $n(board).nestable({maxDepth: 1}).on('change', persistOrder);
        }

        board.addEventListener('click', function (event) {
            var button = event.target.closest('[data-manager-move]');
            if (!button || button.disabled || mutationBusy) return;
            var row = button.closest('.dd-item');
            if (button.dataset.managerMove === 'up' && row.previousElementSibling) {
                list.insertBefore(row, row.previousElementSibling);
            } else if (button.dataset.managerMove === 'down' && row.nextElementSibling) {
                list.insertBefore(row.nextElementSibling, row);
            } else {
                return;
            }
            persistOrder();
        });

        updateControls();
    }

    document.addEventListener('click', function (event) {
        if (mutationBusy) return;
        var toggle = event.target.closest('[data-manager-visibility]');
        if (toggle) visibility(toggle);
    });

    mount();
})();
