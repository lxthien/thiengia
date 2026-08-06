import 'nestable2/dist/jquery.nestable.min.js';

const MenuEditor = {
    config: {},
    currentItemId: null,
    saveTimer: null,

    init: function(options) {
        this.config = $.extend({
            menuId: null,
            reorderUrl: '',
            addItemUrl: '',
            deleteUrlBase: '',
            updateUrlBase: '',
            getUrlBase: '',
            csrfToken: '',
            maxDepth: 3
        }, options);

        const self = this;

        // Initialize Nestable
        if ($.fn.nestable) {
            $('#nestable').nestable({
                maxDepth: self.config.maxDepth,
                callback: function(l, e) {
                    self.autoSaveOrder();
                }
            });
        } else {
            console.error('Nestable plugin not found');
        }

        this.bindEvents();
    },

    bindEvents: function() {
        const self = this;

        // Sidebar accordion toggles
        $(document).on('click', '.me-card-header[data-toggle-card]', function() {
            const bodyId = $(this).data('toggle-card');
            const $body = $('#' + bodyId);
            const $icon = $(this).find('.me-toggle-icon');
            
            if ($body.length) {
                const isHidden = $body.is(':hidden');
                $body.toggle(isHidden);
                $icon.toggleClass('rotated', !isHidden);
            }
        });

        // Keyboard shortcut
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') self.closeItemPanel();
        });

        $(document).on('click', '#save-menu-button', function(e) {
            e.preventDefault();
            self.saveOrder();
        });
    },

    // ─── Auto-save order ───
    autoSaveOrder: function() {
        const self = this;
        clearTimeout(this.saveTimer);
        this.showStatus('saving');
        this.saveTimer = setTimeout(function() {
            self.saveOrder();
        }, 800);
    },

    saveOrder: function() {
        const self = this;
        const data = this.serializeMenuTree();
        this.showStatus('saving');

        $.ajax({
            url: self.config.reorderUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ items: data }),
            success: function(d) {
                if (d.success) {
                    self.showStatus('saved');
                    setTimeout(function() { self.showStatus(''); }, 3000);
                } else {
                    self.showStatus('error');
                    alert(d.message || 'Không thể lưu menu.');
                }
            },
            error: function(xhr) {
                self.showStatus('error');
                let message = 'Lỗi kết nối khi lưu menu.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr && xhr.responseText) {
                    message = xhr.responseText;
                }
                alert(message);
            }
        });
    },

    serializeMenuTree: function() {
        const self = this;

        function serializeList($list) {
            const items = [];

            $list.children('.dd-item').each(function() {
                const $item = $(this);
                const data = {
                    id: $item.data('id')
                };

                const $childrenList = $item.children('.dd-list').first();
                if ($childrenList.length) {
                    data.children = serializeList($childrenList);
                }

                items.push(data);
            });

            return items;
        }

        return serializeList($('#menu-items-root'));
    },

    showStatus: function(state) {
        const $el = $('#save-status');
        $el.removeClass('me-save-status--saving me-save-status--saved me-save-status--error');
        
        if (state === 'saving') {
            $el.addClass('me-save-status--saving').text('⏳ Đang lưu...');
        } else if (state === 'saved') {
            $el.addClass('me-save-status--saved').text('✓ Đã lưu');
        } else if (state === 'error') {
            $el.addClass('me-save-status--error').text('✗ Lỗi lưu');
        } else {
            $el.text('');
        }
    },

    // ─── Add items ───
    addCustomLink: function() {
        const url = $('#cl-url').val().trim();
        const title = $('#cl-title').val().trim();
        if (!url || !title) {
            alert('Vui lòng nhập cả URL và tiêu đề.');
            return;
        }
        this.addItemToMenu({ type: 'url', url: url, title: title });
    },

    addChecked: function(type, listId) {
        const self = this;
        const checked = $('#' + listId + ' .me-check-item-cb:checked');
        if (checked.length === 0) {
            alert('Chọn ít nhất một mục.');
            return;
        }
        
        const items = [];
        checked.each(function() {
            items.push({
                type: type,
                item_id: $(this).data('id'),
                title: $(this).data('title')
            });
        });

        this.addItemsSequentially(items);
    },

    addItemsSequentially: function(items) {
        const self = this;
        if (!items || items.length === 0) return;
        
        const item = items.shift();
        this.addItemToMenu(item).then(function() {
            self.addItemsSequentially(items);
        });
    },

    addItemToMenu: function(payload) {
        const self = this;
        return $.ajax({
            url: self.config.addItemUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(d) {
                if (d.success) {
                    self.appendItemToList(d.item);
                    if (payload.item_id && payload.type !== 'url') {
                        self.markSourceItemAsAdded(payload.type, payload.item_id);
                    }
                    if (payload.type === 'url') {
                        $('#cl-url').val('');
                        $('#cl-title').val('');
                    }
                    $('#empty-state').hide();
                } else {
                    alert('Lỗi: ' + d.message);
                }
            },
            error: function() {
                alert('Lỗi kết nối khi thêm mục.');
            }
        });
    },

    markSourceItemAsAdded: function(type, itemId) {
        const $checkbox = $(`.me-check-item-cb[data-type="${type}"][data-id="${itemId}"]`);
        if (!$checkbox.length) {
            return;
        }

        $checkbox.prop('checked', false);
        $checkbox.prop('disabled', true);

        const $label = $checkbox.closest('.me-check-item');
        if ($label.length && $label.find('small').filter(function() {
            return $(this).text().trim() === '(Đã thêm)';
        }).length === 0) {
            $label.append(' <small>(Đã thêm)</small>');
        }
    },

    appendItemToList: function(item) {
        const $rootList = $('#menu-items-root');
        const typeLabel = { url: 'URL', category: 'Cat', news: 'Post', page: 'Page' }[item.type] || item.type;
        const typeClass = 'dd-type-' + item.type;
        
        const html = `
            <li class="dd-item" data-id="${item.id}"
                data-title="${this.escapeHtml(item.title)}"
                data-url="${this.escapeHtml(item.url || '')}"
                data-type="${item.type}"
                data-target="${item.targetAttr || '_self'}"
                data-css="${item.cssClass || ''}"
                data-titleattr="${item.titleAttr || ''}"
                data-enable="${item.enable ? '1' : '0'}">
                <div class="dd-handle">
                    <span class="dd-grip"><i class="fa fa-grip-vertical"></i></span>
                    <span class="dd-item-type-badge ${typeClass}">${typeLabel}</span>
                    <span class="dd-item-label">
                        ${this.escapeHtml(item.title)}
                        ${item.url ? `<small>${this.escapeHtml(item.url)}</small>` : ''}
                    </span>
                    <span class="dd-item-badges"></span>
                </div>
                <div class="dd-item-actions">
                    <button type="button" class="dd-btn-edit" onclick="event.stopPropagation(); MenuEditor.openItemPanel(${item.id}, this)" title="Chỉnh sửa">
                        <i class="fa fa-pencil"></i> Sửa
                    </button>
                    <form method="POST" action="${this.config.deleteUrlBase}${item.id}" style="display:inline;" onsubmit="return confirm('Xóa mục này?');">
                        <input type="hidden" name="token" value="${this.config.csrfToken}">
                        <button type="submit" class="dd-btn-delete" title="Xóa">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </div>
            </li>`;
        
        $rootList.append(html);
        
        // Refresh Nestable to recognize new item
        $('#nestable').nestable();
        
        this.autoSaveOrder();
    },

    escapeHtml: function(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    },

    // ─── Item Edit Panel ───
    openItemPanel: function(itemId, btn) {
        const $li = btn ? $(btn).closest('.dd-item') : $(`.dd-item[data-id="${itemId}"]`);
        if ($li.length) {
            $('#ip-title').val($li.data('title') || '');
            $('#ip-url').val($li.data('url') || '');
            $('#ip-target').val($li.data('target') || '_self');
            $('#ip-css').val($li.data('css') || '');
            $('#ip-title-attr').val($li.data('titleattr') || '');
            $('#ip-enable').prop('checked', $li.data('enable') === 1 || $li.data('enable') === '1');
        }
        this.currentItemId = itemId;

        // Delete button setup
        const self = this;
        $('#ip-delete-btn').off('click').on('click', function() {
            if (confirm('Xóa mục này và tất cả mục con?')) {
                const deleteUrl = self.config.deleteUrlBase.replace('__ID__', itemId);
                const $form = $(`<form method="POST" action="${deleteUrl}"><input type="hidden" name="token" value="${self.config.csrfToken}"></form>`);
                $('body').append($form);
                self.closeItemPanel();
                $form.submit();
            }
        });

        $('#item-edit-panel').addClass('open');
        $('#panel-overlay').addClass('active');
        $('#ip-title').focus();
    },

    closeItemPanel: function() {
        $('#item-edit-panel').removeClass('open');
        $('#panel-overlay').removeClass('active');
        this.currentItemId = null;
    },

    saveItemPanel: function() {
        const self = this;
        if (!this.currentItemId) return;

        const payload = {
            title: $('#ip-title').val().trim(),
            url: $('#ip-url').val().trim(),
            targetAttr: $('#ip-target').val(),
            cssClass: $('#ip-css').val().trim(),
            titleAttr: $('#ip-title-attr').val().trim(),
            enable: $('#ip-enable').is(':checked')
        };

        if (!payload.title) {
            alert('Tiêu đề không được để trống.');
            return;
        }

        const updateUrl = self.config.updateUrlBase.replace('__ID__', self.currentItemId);

        $.ajax({
            url: updateUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(d) {
                if (d.success) {
                    const $li = $(`.dd-item[data-id="${self.currentItemId}"]`);
                    if ($li.length) {
                        $li.data('title', payload.title);
                        $li.attr('data-title', payload.title);
                        $li.data('url', payload.url);
                        $li.attr('data-url', payload.url);
                        $li.data('target', payload.targetAttr);
                        $li.attr('data-target', payload.targetAttr);
                        $li.data('css', payload.cssClass);
                        $li.attr('data-css', payload.cssClass);
                        $li.data('titleattr', payload.titleAttr);
                        $li.attr('data-titleattr', payload.titleAttr);
                        $li.data('enable', payload.enable ? '1' : '0');
                        $li.attr('data-enable', payload.enable ? '1' : '0');

                        // Only update the current item's own UI, not nested children.
                        const $handle = $li.children('.dd-handle').first();

                        // Update visible label
                        const labelHtml = self.escapeHtml(payload.title) + (payload.url ? `<small>${self.escapeHtml(payload.url)}</small>` : '');
                        $handle.children('.dd-item-label').html(labelHtml);

                        // Update badges
                        const $badges = $handle.children('.dd-item-badges');
                        $badges.find('.dd-badge--disabled').remove();
                        if (!payload.enable) {
                            $badges.prepend('<span class="dd-badge dd-badge--disabled">Tắt</span>');
                        }
                    }
                    self.closeItemPanel();
                    self.showStatus('saved');
                    setTimeout(function() { self.showStatus(''); }, 3000);
                } else {
                    alert('Lỗi: ' + d.message);
                }
            },
            error: function() {
                alert('Lỗi kết nối khi cập nhật mục.');
            }
        });
    },

    // ─── Helpers ───
    filterList: function(input, listId) {
        const q = $(input).val().toLowerCase();
        $('#' + listId + ' .me-check-item').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggleClass('hidden', !text.includes(q));
        });
    },

    selectAll: function(listId) {
        $('#' + listId + ' .me-check-item-cb').prop('checked', true);
    },

    deselectAll: function(listId) {
        $('#' + listId + ' .me-check-item-cb').prop('checked', false);
    },

    expandAll: function() {
        $('#nestable').nestable('expandAll');
    },

    collapseAll: function() {
        $('#nestable').nestable('collapseAll');
    }
};

window.MenuEditor = MenuEditor;

// Auto-initialize if the container exists
$(document).ready(function() {
    const $nestable = $('#nestable');
    if ($nestable.length && $nestable.data('menu-config')) {
        const config = $nestable.data('menu-config');
        MenuEditor.init(config);
        console.log('MenuEditor: Auto-initialized from data-menu-config');
    }
});
