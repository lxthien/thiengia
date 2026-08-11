import 'typeahead.js';
import 'bootstrap-tagsinput';

import 'bootstrap-sass/assets/javascripts/bootstrap/modal.js';

$(function() {
    // Shared HTML escape helpers
    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function escapeAttribute(value) {
        return String(value || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Resolve versioned CKEditor content CSS URL (set by Twig in layout.html.twig)
    function getCkeditorContentCss() {
        return (window.KIENTRUC_CONFIG && window.KIENTRUC_CONFIG.ckeditorContentCss)
            ? window.KIENTRUC_CONFIG.ckeditorContentCss
            : '/build/css/ckeditor-content.css';
    }

    initAdminSidebarState();

    // Build the slug for object entiry from the name
    initBuildSluggable();

    // Init CkEditor and CKfinder
    initCkeditor();

    // Update object when change the enable button toggle
    initEnableToggleButton();

    initMakePrimaryCategory();
    initAdminNotifications();
    initPageBuilder();
    initContentBlocks();
    initMediaPicker();
    initBulkActions();

    /**
     * Create sluggable from name
     * 
     **/
    function initBuildSluggable() {
        $("body.new :input.sluggable").keyup(function () {
            $(":input.url").val(remove_vietnamese_accents($(this).val()));
        });

        $(":input.url").click(function () {
            if ($(this).attr('readonly')) {
                $(":input.url").removeAttr('readonly');
            }
        });

        $(":input.url").focusout(function () {
            if (!$(this).attr('readonly')) {
                $(":input.url").attr('readonly', 'readonly');
            }
        });
    }

    /**
     * Bulk-select checkboxes for admin list pages (comment, ...): check-all toggle,
     * 2-way sync with per-row checkboxes, guard against submitting bulk forms with
     * nothing selected/no action chosen.
     */
    function initBulkActions() {
        $('[data-bulk-check-all]').on('change', function() {
            var checked = $(this).prop('checked');
            $(this).closest('table').find('[data-bulk-check]').prop('checked', checked);
        });

        $('[data-bulk-check]').on('change', function() {
            var $table = $(this).closest('table');
            var total = $table.find('[data-bulk-check]').length;
            var checked = $table.find('[data-bulk-check]:checked').length;

            $table.find('[data-bulk-check-all]').prop('checked', total > 0 && total === checked);
        });

        $('[data-bulk-form]').on('submit', function(event) {
            var $form = $(this);
            var formId = $form.attr('id');
            var action = $form.find('[name="bulk_action"]').val();
            var checkedCount = formId ? $('[data-bulk-check][form="' + formId + '"]:checked').length : $form.find('[data-bulk-check]:checked').length;

            if (!action || checkedCount === 0) {
                event.preventDefault();
                alert('Vui lòng chọn dữ liệu và thao tác.');
                return;
            }

            if (action === 'delete' && !confirm('Bạn chắc chắn muốn xóa các mục đã chọn?')) {
                event.preventDefault();
            }
        });
    }

    /**
     * @var string
     * Remove vietnamese from string
     **/
    function remove_vietnamese_accents(str) {
        var accents_arr = new Array("à", "á", "ạ", "ả", "ã", "â", "ầ", "ấ", "ậ", "ẩ", "ẫ", "ă", "ằ", "ắ", "ặ", "ẳ", "ẵ", "è", "é", "ẹ", "ẻ", "ẽ", "ê", "ề", "ế", "ệ", "ể", "ễ", "ì", "í", "ị", "ỉ", "ĩ", "ò", "ó", "ọ", "ỏ", "õ", "ô", "ồ", "ố", "ộ", "ổ", "ỗ", "ơ", "ờ", "ớ", "ợ", "ở", "ỡ", "ù", "ú", "ụ", "ủ", "ũ", "ư", "ừ", "ứ", "ự", "ử", "ữ", "ỳ", "ý", "ỵ", "ỷ", "ỹ", "đ", "À", "Á", "Ạ", "Ả", "Ã", "Â", "Ầ", "Ấ", "Ậ", "Ẩ", "Ẫ", "Ă", "Ằ", "Ắ", "Ặ", "Ẳ", "Ẵ", "È", "É", "Ẹ", "Ẻ", "Ẽ", "Ê", "Ề", "Ế", "Ệ", "Ể", "Ễ", "Ì", "Í", "Ị", "Ỉ", "Ĩ", "Ò", "Ó", "Ọ", "Ỏ", "Õ", "Ô", "Ồ", "Ố", "Ộ", "Ổ", "Ỗ", "Ơ", "Ờ", "Ớ", "Ợ", "Ở", "Ỡ", "Ù", "Ú", "Ụ", "Ủ", "Ũ", "Ư", "Ừ", "Ứ", "Ự", "Ử", "Ữ", "Ỳ", "Ý", "Ỵ", "Ỷ", "Ỹ", "Đ", " ", "\"", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", ".", ",", ";", "'", "[", "]", "{", "}", ":", "“", "”", "--", '.', '>', '<', '--', '---', '‘', '’', '/', '?', '~', "|");

        var no_accents_arr = new Array("a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "e", "e", "e", "e", "e", "e", "e", "e", "e", "e", "e", "i", "i", "i", "i", "i", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "y", "y", "y", "y", "y", "d", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "a", "e", "e", "e", "e", "e", "e", "e", "e", "e", "e", "e", "i", "i", "i", "i", "i", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "o", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "u", "y", "y", "y", "y", "y", "d", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", "-", '-', '-', '-', '-', '---', '-', '-', '-', '', '', '');

        return str_replace(accents_arr, no_accents_arr, str).toLowerCase();
    }

    /**
     * @var string
     * Replace the string
     **/
    function str_replace(search, replace, str) {
        var ra = replace instanceof Array,
            sa = str instanceof Array,
            l = (search = [].concat(search)).length,
            replace = [].concat(replace),
            i = (str = [].concat(str)).length,
            j;

        while (j = 0, i--) {
            while (str[i] = str[i].split(search[j]).join(ra ? replace[j] || "" : replace[0]), ++j < l) {}
        }return sa ? str : str[0];
    }

    /**
     * Init Ckeditor and Ckfinder.
     **/
    function initCkeditor() {
        $('.txt-ckeditor').each(function (e, elements) {
            var height = $(this).data("height") ? $(this).data("height") : "500";
            CKEDITOR.replace(this.id, {
                height: height + 'px',
                filebrowserBrowseUrl: '/assets/cksourceckfinder/ckfinder/ckfinder.html',
                filebrowserUploadUrl: '/assets/cksourceckfinder/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
                filebrowserWindowWidth: '1000',
                filebrowserWindowHeight: '700',
                contentsCss: getCkeditorContentCss(),
                bodyClass: 'ka-article-body'
            });
        });

        // Attach TOC toggle inside each CKEditor iframe
        function bindTocClick(editorDoc) {
            editorDoc.addEventListener('click', function(event) {
                var target = event.target;
                while (target && target !== editorDoc.body) {
                    if (target.id === 'main-toc') {
                        var toc = target.closest('.ka-table-of-contents');
                        if (toc) {
                            toc.classList.toggle('collapsed');
                        }
                        break;
                    }
                    target = target.parentElement;
                }
            });
        }

        function attachTocToggle(editor) {
            // contentDom fires when switching between source ↔ WYSIWYG mode
            editor.on('contentDom', function() {
                bindTocClick(editor.document.$);
            });

            // instanceReady: contentDom has already fired, bind directly now
            if (editor.document) {
                bindTocClick(editor.document.$);
            }
        }

        if (window.CKEDITOR) {
            CKEDITOR.on('instanceReady', function(event) {
                attachTocToggle(event.editor);
            });

            $.each(CKEDITOR.instances, function(id, editor) {
                if (editor.status === 'ready') {
                    attachTocToggle(editor);
                }
            });
        }
    }

    /**
     * Update object when change the enable button toggle
     **/
    function initEnableToggleButton() {
        $(document).on('change', '.switch-input[data-action]', function() {
            let $input = $(this);
            let isChecked = $input.prop('checked');
            isChecked = isChecked ? 1 : 0;
            let id = $input.data('id');
            let url = $input.data('action');
            
            $.ajax({
                type: "POST",
                url: url,
                data: 'newsId=' + id + '&enable=' + isChecked,
                success: function(data) {
                    var response = JSON.parse(data);
                }
            });
        });
    }

    // Bootstrap-tagsinput initialization
    var $input = $('input[data-toggle="tagsinput"]');
    if ($input.length && typeof Bloodhound !== 'undefined') {
        var source = new Bloodhound({
            local: $input.data('tags'),
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            datumTokenizer: Bloodhound.tokenizers.whitespace
        });
        source.initialize();

        $input.tagsinput({
            trimValue: true,
            focusClass: 'focus',
            typeaheadjs: {
                name: 'tags',
                source: source.ttAdapter()
            }
        });
    }

    function initMakePrimaryCategory() {
        var categoryPrimaryId = $('#news_categoryPrimary').val();

        $("#news_category .checkbox").each(function() {
            var categoryId = $(this).find('input[type="checkbox"]').val();

            if ($(this).find('input[type="checkbox"]').is(':checked') && categoryPrimaryId != categoryId) {
                $(this).append('<label class="label-primary"> <input type="radio" name="categoryPrimary"></input> Chọn làm danh mục chính</label>');
            }
        });

        $('#news_category .checkbox input[type="checkbox"]').change(function() {
            if (!this.checked) {
                $(this).closest('.checkbox').find('label.label-primary').remove();
            } else {
                $(this).parent().parent('.checkbox').append('<label class="label-primary"> <input type="radio" name="categoryPrimary"></input> Chọn làm danh mục chính</label>');
            }
        });

        $(document).on('change', '#news_category .checkbox .label-primary input[type="radio"]', function(e) {
            var categoryId = $(this).closest('.checkbox').find('input[type="checkbox"]').val();

            if (categoryId > 0 ) {
                $('#news_categoryPrimary').val(categoryId);
            }
        });
    }

    function initAdminNotifications() {
        var $button = $('#notificationDropdown');
        var $countNode = $('[data-notification-count]');
        var $menuNode = $('[data-notification-menu]');

        if (!$button.length || !$countNode.length || !$menuNode.length) {
            return;
        }

        var feedUrl = $button.data('feed-url');
        if (!feedUrl) {
            return;
        }

        function renderNotificationCount(total) {
            $countNode.text(total);

            if (total > 0) {
                $countNode.removeClass('d-none');
            } else {
                $countNode.addClass('d-none');
            }
        }

        function refreshNotifications() {
            $.ajax({
                url: feedUrl,
                type: 'GET',
                dataType: 'json'
            }).done(function(payload) {
                renderNotificationCount(payload.total || 0);

                if (typeof payload.html === 'string') {
                    $menuNode.html(payload.html);
                }
            });
        }

        $button.on('click', refreshNotifications);
        window.setInterval(refreshNotifications, 30000);
    }

    function initPageBuilder() {
        var $builder = $('[data-page-builder]');

        if (!$builder.length) {
            return;
        }

        var $enabled = $builder.find('input[id$="_pageBuilderEnabled"]');
        var $dataInput = $builder.find('input[id$="_pageBuilderData"]');
        var $form = $builder.closest('form');
        var $contents = $form.find('textarea[id$="_contents"]');
        var $workspace = $builder.find('[data-page-builder-workspace]');
        var $legacy = $form.find('[data-page-builder-legacy]');
        var $list = $builder.find('[data-page-builder-list]');
        var $empty = $builder.find('[data-page-builder-empty]');
        var $addButton = $builder.find('[data-page-builder-add]');
        var editorPrefix = 'page_builder_block_';

        if (!$enabled.length || !$dataInput.length || !$contents.length) {
            return;
        }
        var blockOptions = [
            { value: 'hero', label: 'Hero' },
            { value: 'rich_text', label: 'Rich text' },
            { value: 'image', label: 'Image' },
            { value: 'gallery', label: 'Gallery' },
            { value: 'faq', label: 'FAQ' },
            { value: 'video', label: 'Video' },
            { value: 'features', label: 'Features' },
            { value: 'contact_form', label: 'Contact form' },
            { value: 'cta', label: 'CTA' },
            { value: 'spacer', label: 'Spacer' }
        ];
        var blocks = parseBlocks($dataInput.val());
        var dragIndex = null;

        render();
        syncVisibility();

        $enabled.on('change', function() {
            syncVisibility();
            syncBuilderState();
        });

        $addButton.on('click', function() {
            syncFromDom();
            blocks.push(createDefaultBlock('rich_text'));
            render();
            syncBuilderState();
        });

        $list.on('click', '[data-block-clone]', function() {
            syncFromDom();
            var index = $(this).closest('[data-block-index]').data('block-index');
            var source = blocks[index];

            if (!source) {
                return;
            }

            blocks.splice(index + 1, 0, $.extend(true, {}, source, { id: createId() }));
            render();
            syncBuilderState();
        });

        $list.on('click', '[data-block-remove]', function() {
            syncFromDom();
            var index = $(this).closest('[data-block-index]').data('block-index');
            blocks.splice(index, 1);
            render();
            syncBuilderState();
        });

        $list.on('click', '[data-block-move]', function() {
            syncFromDom();
            var $block = $(this).closest('[data-block-index]');
            var index = $block.data('block-index');
            var direction = $(this).data('block-move');
            var targetIndex = direction === 'up' ? index - 1 : index + 1;

            if (targetIndex < 0 || targetIndex >= blocks.length) {
                return;
            }

            var current = blocks[index];
            blocks[index] = blocks[targetIndex];
            blocks[targetIndex] = current;
            render();
            syncBuilderState();
        });

        $list.on('change', '[data-block-type]', function() {
            syncFromDom();
            var $block = $(this).closest('[data-block-index]');
            var index = $block.data('block-index');
            blocks[index] = createDefaultBlock($(this).val(), blocks[index].id);
            render();
            syncBuilderState();
        });

        $list.on('dragstart', '[data-block-index]', function(event) {
            dragIndex = $(this).data('block-index');
            $(this).addClass('is-dragging');
            event.originalEvent.dataTransfer.effectAllowed = 'move';
            event.originalEvent.dataTransfer.setData('text/plain', String(dragIndex));
        });

        $list.on('dragend', '[data-block-index]', function() {
            dragIndex = null;
            $list.find('[data-block-index]').removeClass('is-dragging is-drop-target');
        });

        $list.on('dragover', '[data-block-index]', function(event) {
            event.preventDefault();
            $(this).addClass('is-drop-target');
            event.originalEvent.dataTransfer.dropEffect = 'move';
        });

        $list.on('dragleave', '[data-block-index]', function() {
            $(this).removeClass('is-drop-target');
        });

        $list.on('drop', '[data-block-index]', function(event) {
            event.preventDefault();
            $(this).removeClass('is-drop-target');
            syncFromDom();

            var targetIndex = $(this).data('block-index');

            if (dragIndex === null || dragIndex === targetIndex) {
                return;
            }

            var moved = blocks.splice(dragIndex, 1)[0];
            blocks.splice(targetIndex, 0, moved);
            render();
            syncBuilderState();
        });

        $list.on('input change', 'input, textarea, select', function() {
            syncFromDom();
            syncBuilderState();
            updateBlockPreview($(this).closest('[data-block-index]'));
        });

        $form.on('submit', function(event) {
            syncFromDom();

            if ($enabled.is(':checked') && !blocks.length) {
                event.preventDefault();
                window.alert('Vui long them it nhat mot block cho Page Builder.');
                return false;
            }

            syncBuilderState();
        });

        function syncVisibility() {
            var enabled = $enabled.is(':checked');
            $workspace.toggle(enabled);
            $legacy.toggle(!enabled);
        }

        function syncFromDom() {
            syncRichTextEditorsToTextareas();
            var nextBlocks = [];

            $list.find('[data-block-index]').each(function() {
                var $block = $(this);
                var index = $block.data('block-index');
                var type = $block.find('[data-block-type]').val();
                var data = {};

                $block.find('[data-field]').each(function() {
                    data[$(this).data('field')] = $(this).val();
                });

                nextBlocks.push({
                    id: blocks[index] && blocks[index].id ? blocks[index].id : createId(),
                    type: type,
                    data: data
                });
            });

            blocks = nextBlocks;
        }

        function syncBuilderState() {
            var json = blocks.length ? JSON.stringify(blocks) : '';
            $dataInput.val(json);

            if ($enabled.is(':checked')) {
                $contents.val(buildLegacyHtml(blocks));
            }
        }

        function render() {
            destroyPageBuilderEditors();
            $list.empty();
            $empty.toggle(!blocks.length);

            $.each(blocks, function(index, block) {
                $list.append(renderBlock(index, block));
            });

            initPageBuilderEditors();
        }

        function renderBlock(index, block) {
            var optionMarkup = $.map(blockOptions, function(option) {
                return '<option value="' + option.value + '"' + (option.value === block.type ? ' selected' : '') + '>' + option.label + '</option>';
            }).join('');
            var fieldsMarkup = renderBlockFields(block);

            return [
                '<div class="page-builder-admin__block card" data-block-index="' + index + '" draggable="true">',
                    '<div class="card-header page-builder-admin__block-header">',
                        '<div class="page-builder-admin__block-title">',
                            '<strong><i class="fa fa-bars" aria-hidden="true"></i> Block ' + (index + 1) + '</strong>',
                            '<select class="form-control form-control-sm" data-block-type>' + optionMarkup + '</select>',
                        '</div>',
                        '<div class="page-builder-admin__block-actions">',
                            '<button type="button" class="btn btn-light btn-sm" data-block-clone><i class="fa fa-copy" aria-hidden="true"></i></button>',
                            '<button type="button" class="btn btn-light btn-sm" data-block-move="up"><i class="fa fa-arrow-up" aria-hidden="true"></i></button>',
                            '<button type="button" class="btn btn-light btn-sm" data-block-move="down"><i class="fa fa-arrow-down" aria-hidden="true"></i></button>',
                            '<button type="button" class="btn btn-danger btn-sm" data-block-remove><i class="fa fa-trash" aria-hidden="true"></i></button>',
                        '</div>',
                    '</div>',
                    '<div class="card-body page-builder-admin__block-body">',
                        fieldsMarkup,
                        '<div class="page-builder-admin__preview">',
                            '<div class="page-builder-admin__preview-label">Preview</div>',
                            '<div class="page-builder-admin__preview-body">' + renderPreviewHtml(block) + '</div>',
                        '</div>',
                    '</div>',
                '</div>'
            ].join('');
        }

        function renderBlockFields(block) {
            var data = block.data || {};

            if (block.type === 'hero') {
                return [
                    renderInput('Eyebrow', 'eyebrow', data.eyebrow),
                    renderInput('Title', 'title', data.title),
                    renderTextarea('Body', 'body', data.body, 4),
                    renderInput('Button text', 'button_text', data.button_text),
                    renderInput('Button URL', 'button_url', data.button_url),
                    renderInput('Background image URL', 'background_image', data.background_image)
                ].join('');
            }

            if (block.type === 'image') {
                return [
                    renderInput('Image URL', 'url', data.url),
                    renderInput('Alt text', 'alt', data.alt),
                    renderInput('Caption', 'caption', data.caption),
                    renderInput('Width', 'width', data.width)
                ].join('');
            }

            if (block.type === 'cta') {
                return [
                    renderInput('Title', 'title', data.title),
                    renderTextarea('Body', 'body', data.body, 3),
                    renderInput('Button text', 'button_text', data.button_text),
                    renderInput('Button URL', 'button_url', data.button_url),
                    renderSelect('Style', 'style', data.style, [
                        { value: 'primary', label: 'Primary' },
                        { value: 'outline', label: 'Outline' }
                    ])
                ].join('');
            }

            if (block.type === 'gallery') {
                return [
                    '<div class="alert alert-light page-builder-admin__hint">Moi dong: image_url | alt text | caption</div>',
                    renderTextarea('Gallery items', 'items', data.items, 7)
                ].join('');
            }

            if (block.type === 'faq') {
                return [
                    '<div class="alert alert-light page-builder-admin__hint">Moi dong: Cau hoi | Cau tra loi</div>',
                    renderTextarea('FAQ items', 'items', data.items, 8)
                ].join('');
            }

            if (block.type === 'video') {
                return [
                    renderInput('Video URL', 'url', data.url),
                    renderInput('Title', 'title', data.title),
                    renderTextarea('Caption', 'caption', data.caption, 3)
                ].join('');
            }

            if (block.type === 'features') {
                return [
                    renderInput('Section title', 'title', data.title),
                    '<div class="alert alert-light page-builder-admin__hint">Moi dong: icon_class | title | mo ta</div>',
                    renderTextarea('Feature items', 'items', data.items, 8)
                ].join('');
            }

            if (block.type === 'contact_form') {
                return [
                    renderInput('Title', 'title', data.title),
                    renderTextarea('Body', 'body', data.body, 3),
                    renderInput('Hotline', 'hotline', data.hotline)
                ].join('');
            }

            if (block.type === 'spacer') {
                return renderInput('Height (px)', 'height', data.height || '48');
            }

            return renderRichTextField(block, data.html);
        }

        function renderInput(label, field, value) {
            return [
                '<div class="form-group page-builder-admin__field">',
                    '<label>' + escapeHtml(label) + '</label>',
                    '<input type="text" class="form-control" data-field="' + field + '" value="' + escapeHtml(value || '') + '">',
                '</div>'
            ].join('');
        }

        function renderTextarea(label, field, value, rows) {
            return [
                '<div class="form-group page-builder-admin__field">',
                    '<label>' + escapeHtml(label) + '</label>',
                    '<textarea class="form-control" rows="' + rows + '" data-field="' + field + '">' + escapeHtml(value || '') + '</textarea>',
                '</div>'
            ].join('');
        }

        function renderRichTextField(block, value) {
            var editorId = editorPrefix + (block.id || createId());

            return [
                '<div class="form-group page-builder-admin__field">',
                    '<label>HTML content</label>',
                    '<textarea id="' + editorId + '" class="form-control page-builder-richtext" rows="8" data-field="html" data-editor-id="' + editorId + '">' + escapeHtml(value || '') + '</textarea>',
                '</div>'
            ].join('');
        }

        function renderSelect(label, field, selectedValue, options) {
            var markup = $.map(options, function(option) {
                return '<option value="' + option.value + '"' + (option.value === selectedValue ? ' selected' : '') + '>' + option.label + '</option>';
            }).join('');

            return [
                '<div class="form-group page-builder-admin__field">',
                    '<label>' + escapeHtml(label) + '</label>',
                    '<select class="form-control" data-field="' + field + '">' + markup + '</select>',
                '</div>'
            ].join('');
        }

        function parseBlocks(rawValue) {
            if (!rawValue) {
                return [];
            }

            try {
                var parsed = JSON.parse(rawValue);

                if (!$.isArray(parsed)) {
                    return [];
                }

                return $.map(parsed, function(block) {
                    if (!block || !block.type) {
                        return null;
                    }

                    return {
                        id: block.id || createId(),
                        type: block.type,
                        data: block.data || {}
                    };
                });
            } catch (error) {
                return [];
            }
        }

        function createDefaultBlock(type, id) {
            var block = {
                id: id || createId(),
                type: type,
                data: {}
            };

            if (type === 'hero') {
                block.data = {
                    eyebrow: '',
                    title: '',
                    body: '',
                    button_text: '',
                    button_url: '',
                    background_image: ''
                };
            } else if (type === 'image') {
                block.data = {
                    url: '',
                    alt: '',
                    caption: '',
                    width: ''
                };
            } else if (type === 'cta') {
                block.data = {
                    title: '',
                    body: '',
                    button_text: '',
                    button_url: '',
                    style: 'primary'
                };
            } else if (type === 'gallery') {
                block.data = {
                    items: ''
                };
            } else if (type === 'faq') {
                block.data = {
                    items: ''
                };
            } else if (type === 'video') {
                block.data = {
                    url: '',
                    title: '',
                    caption: ''
                };
            } else if (type === 'features') {
                block.data = {
                    title: '',
                    items: ''
                };
            } else if (type === 'contact_form') {
                block.data = {
                    title: '',
                    body: '',
                    hotline: ''
                };
            } else if (type === 'spacer') {
                block.data = {
                    height: '48'
                };
            } else {
                block.data = {
                    html: ''
                };
            }

            return block;
        }

        function buildLegacyHtml(items) {
            return $.map(items, function(block) {
                var data = block.data || {};

                if (block.type === 'hero') {
                    var heroBody = data.body ? '<p>' + escapeHtml(data.body).replace(/\n/g, '<br>') + '</p>' : '';
                    var heroButton = data.button_text && data.button_url ? '<p><a class="ka-builder-button" href="' + escapeAttribute(data.button_url) + '">' + escapeHtml(data.button_text) + '</a></p>' : '';
                    var heroStyle = data.background_image ? ' style="background-image:url(\'' + escapeAttribute(data.background_image) + '\')"' : '';

                    return '<section class="ka-builder-hero"' + heroStyle + '><div class="ka-builder-hero__inner">' +
                        (data.eyebrow ? '<span class="ka-builder-eyebrow">' + escapeHtml(data.eyebrow) + '</span>' : '') +
                        (data.title ? '<h2>' + escapeHtml(data.title) + '</h2>' : '') +
                        heroBody +
                        heroButton +
                    '</div></section>';
                }

                if (block.type === 'image') {
                    if (!data.url) {
                        return '';
                    }

                    return '<figure class="ka-builder-image"' + (data.width ? ' style="max-width:' + escapeAttribute(data.width) + 'px"' : '') + '>' +
                        '<img loading="lazy" src="' + escapeAttribute(data.url) + '" alt="' + escapeAttribute(data.alt || '') + '">' +
                        (data.caption ? '<figcaption>' + escapeHtml(data.caption) + '</figcaption>' : '') +
                    '</figure>';
                }

                if (block.type === 'gallery') {
                    var galleryItems = parseLineItems(data.items, 3);

                    if (!galleryItems.length) {
                        return '';
                    }

                    return '<section class="ka-builder-gallery">' + $.map(galleryItems, function(item) {
                        return '<figure class="ka-builder-gallery__item">' +
                            '<img loading="lazy" src="' + escapeAttribute(item[0]) + '" alt="' + escapeAttribute(item[1] || '') + '">' +
                            (item[2] ? '<figcaption>' + escapeHtml(item[2]) + '</figcaption>' : '') +
                        '</figure>';
                    }).join('') + '</section>';
                }

                if (block.type === 'faq') {
                    var faqItems = parseLineItems(data.items, 2);

                    if (!faqItems.length) {
                        return '';
                    }

                    return '<section class="ka-builder-faq">' + $.map(faqItems, function(item) {
                        return '<details class="ka-builder-faq__item"><summary>' + escapeHtml(item[0]) + '</summary><div class="ka-builder-faq__answer"><p>' + escapeHtml(item[1] || '').replace(/\n/g, '<br>') + '</p></div></details>';
                    }).join('') + '</section>';
                }

                if (block.type === 'video') {
                    var embedUrl = buildVideoEmbedUrl(data.url || '');

                    if (!embedUrl) {
                        return '';
                    }

                    return '<section class="ka-builder-video">' +
                        (data.title ? '<h3>' + escapeHtml(data.title) + '</h3>' : '') +
                        '<div class="ka-builder-video__frame"><iframe src="' + escapeAttribute(embedUrl) + '" allowfullscreen loading="lazy"></iframe></div>' +
                        (data.caption ? '<p class="ka-builder-video__caption">' + escapeHtml(data.caption).replace(/\n/g, '<br>') + '</p>' : '') +
                    '</section>';
                }

                if (block.type === 'features') {
                    var featureItems = parseLineItems(data.items, 3);

                    if (!featureItems.length) {
                        return '';
                    }

                    return '<section class="ka-builder-features">' +
                        (data.title ? '<h3>' + escapeHtml(data.title) + '</h3>' : '') +
                        '<div class="ka-builder-features__grid">' + $.map(featureItems, function(item) {
                            return '<article class="ka-builder-features__item">' +
                                (item[0] ? '<div class="ka-builder-features__icon"><i class="' + escapeAttribute(item[0]) + '" aria-hidden="true"></i></div>' : '') +
                                (item[1] ? '<h4>' + escapeHtml(item[1]) + '</h4>' : '') +
                                (item[2] ? '<p>' + escapeHtml(item[2]).replace(/\n/g, '<br>') + '</p>' : '') +
                            '</article>';
                        }).join('') + '</div></section>';
                }

                if (block.type === 'contact_form') {
                    return '<section class="ka-builder-contact-form">' +
                        (data.title ? '<h3>' + escapeHtml(data.title) + '</h3>' : '') +
                        (data.body ? '<p>' + escapeHtml(data.body).replace(/\n/g, '<br>') + '</p>' : '') +
                        (data.hotline ? '<p><strong>' + escapeHtml(data.hotline) + '</strong></p>' : '') +
                        '<div class="ka-builder-contact-form__placeholder">Embedded contact form</div>' +
                    '</section>';
                }

                if (block.type === 'cta') {
                    return '<section class="ka-builder-cta ka-builder-cta--' + escapeAttribute(data.style || 'primary') + '">' +
                        (data.title ? '<h3>' + escapeHtml(data.title) + '</h3>' : '') +
                        (data.body ? '<p>' + escapeHtml(data.body).replace(/\n/g, '<br>') + '</p>' : '') +
                        (data.button_text && data.button_url ? '<a class="ka-builder-button" href="' + escapeAttribute(data.button_url) + '">' + escapeHtml(data.button_text) + '</a>' : '') +
                    '</section>';
                }

                if (block.type === 'spacer') {
                    return '<div class="ka-builder-spacer" style="height:' + escapeAttribute(data.height || '48') + 'px"></div>';
                }

                return '<section class="ka-builder-rich-text">' + (data.html || '') + '</section>';
            }).join('\n');
        }

        // escapeHtml and escapeAttribute are defined in the outer $(function) scope

        function createId() {
            return 'block_' + Math.random().toString(36).slice(2, 10);
        }

        function renderPreviewHtml(block) {
            var html = buildLegacyHtml([block]);

            if (!html) {
                return '<div class="page-builder-admin__preview-empty">Chua co du lieu de preview.</div>';
            }

            return html;
        }

        function parseLineItems(value, expectedParts) {
            if (!value) {
                return [];
            }

            return $.map(String(value).split(/\r?\n/), function(line) {
                var trimmed = $.trim(line);

                if (!trimmed) {
                    return null;
                }

                var parts = $.map(trimmed.split('|'), function(part) {
                    return $.trim(part);
                });

                while (parts.length < expectedParts) {
                    parts.push('');
                }

                return [parts.slice(0, expectedParts)];
            });
        }

        function buildVideoEmbedUrl(url) {
            var value = $.trim(url || '');

            if (!value) {
                return '';
            }

            var youtubeMatch = value.match(/youtube\.com\/watch\?v=([^&]+)/);
            if (youtubeMatch) {
                return 'https://www.youtube.com/embed/' + youtubeMatch[1];
            }

            var shortYoutubeMatch = value.match(/youtu\.be\/([^?&/]+)/);
            if (shortYoutubeMatch) {
                return 'https://www.youtube.com/embed/' + shortYoutubeMatch[1];
            }

            var vimeoMatch = value.match(/vimeo\.com\/(\d+)/);
            if (vimeoMatch) {
                return 'https://player.vimeo.com/video/' + vimeoMatch[1];
            }

            return value;
        }

        function initPageBuilderEditors() {
            if (!window.CKEDITOR) {
                return;
            }

            $list.find('textarea.page-builder-richtext').each(function() {
                var textareaId = $(this).attr('id');

                if (!textareaId || CKEDITOR.instances[textareaId]) {
                    return;
                }

                CKEDITOR.replace(textareaId, {
                    protectedSource: [
                        /<script[\s\S]*?<\/script>/gi,
                        /<style[\s\S]*?<\/style>/gi
                    ],
                    height: '280px',
                    filebrowserBrowseUrl: '/assets/cksourceckfinder/ckfinder/ckfinder.html',
                    filebrowserUploadUrl: '/assets/cksourceckfinder/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
                    filebrowserWindowWidth: '1000',
                    filebrowserWindowHeight: '700',
                    contentsCss: getCkeditorContentCss(),
                    bodyClass: 'ka-article-body'
                });

                CKEDITOR.instances[textareaId].on('change', function() {
                    syncRichTextEditorsToTextareas();
                    syncFromDom();
                    syncBuilderState();
                    updateBlockPreviewByTextareaId(textareaId);
                });
            });
        }

        function destroyPageBuilderEditors() {
            if (!window.CKEDITOR || !CKEDITOR.instances) {
                return;
            }

            syncRichTextEditorsToTextareas();

            $.each(CKEDITOR.instances, function(instanceId, instance) {
                if (instanceId.indexOf(editorPrefix) === 0) {
                    instance.destroy(true);
                }
            });
        }

        function syncRichTextEditorsToTextareas() {
            if (!window.CKEDITOR || !CKEDITOR.instances) {
                return;
            }

            $list.find('textarea.page-builder-richtext').each(function() {
                var textareaId = $(this).attr('id');
                var instance = textareaId ? CKEDITOR.instances[textareaId] : null;

                if (instance) {
                    $(this).val(instance.getData());
                }
            });
        }

        function updateBlockPreviewByTextareaId(textareaId) {
            var $textarea = $('#' + textareaId);
            updateBlockPreview($textarea.closest('[data-block-index]'));
        }

        function updateBlockPreview($block) {
            if (!$block || !$block.length) {
                return;
            }

            var index = $block.data('block-index');
            var block = blocks[index];

            if (!block) {
                return;
            }

            $block.find('.page-builder-admin__preview-body').html(renderPreviewHtml(block));
        }
    }

    function initContentBlocks() {
        var $modal = $('[data-cms-block-modal]');
        var $form = $('[data-cms-block-form]');
        var $preview = $('[data-cms-block-preview]');
        var activeEditorId = null;
        var activeBlockType = null;
        var activeEditorBlock = null;
        var relatedSearchUrl = null;
        var relatedCurrentId = 0;
        var relatedSearchTimer = null;
        var blockTitles = {
            hero_section: 'Hero Section',
            faq: 'FAQ block',
            cta: 'CTA block',
            pricing: 'Bảng giá',
            gallery: 'Gallery block',
            related: 'Related posts',
            lead: 'Form lead'
        };

        if (!$modal.length || !$form.length) {
            return;
        }

        $modal.appendTo('body');
        $preview = $modal.find('[data-cms-block-preview]');

        function resetBlockForm(type) {
            $form.find('input[type="text"], input[type="url"], input[type="tel"], input[type="hidden"], textarea').val('');
            $form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
            $form.find('[name="blockType"]').val(type);
            $form.find('[name="relatedSelectedItems"]').val('[]');
            $form.find('[data-cms-related-selected], [data-cms-related-results]').empty();
            $preview.empty().hide();
        }

        function showBlockForm(type, payload, editorBlock) {
            activeBlockType = type;
            activeEditorBlock = editorBlock || null;
            resetBlockForm(type);
            $modal.find('[data-cms-block-title]').text((activeEditorBlock ? 'Sửa ' : 'Chèn ') + (blockTitles[type] || 'content block'));
            $modal.find('[data-cms-block-insert]').html('<i class="fa fa-' + (activeEditorBlock ? 'save' : 'plus') + '"></i> ' + (activeEditorBlock ? 'Cập nhật block' : 'Chèn block'));
            $modal.find('[data-cms-block-form-type]').hide();
            $modal.find('[data-cms-block-form-type="' + type + '"]').show();

            if (payload) {
                populateBlockForm(type, payload);
            }

            renderPreview();
            openBlockModal();
        }

        function openBlockModal() {
            if ($.fn.modal) {
                $modal.modal('show');
                $modal.addClass('show in');
                $('.modal-backdrop').addClass('show in');
                return;
            }

            $modal.show().addClass('show in').attr('aria-hidden', 'false');
            $('body').addClass('modal-open');
        }

        function closeBlockModal() {
            if ($.fn.modal) {
                $modal.modal('hide');
                return;
            }

            $modal.hide().removeClass('show in').attr('aria-hidden', 'true');
            $('body').removeClass('modal-open');
        }

        function normalizeLines(value) {
            return String(value || '')
                .split(/\r?\n/)
                .map(function(line) {
                    return $.trim(line);
                })
                .filter(function(line) {
                    return line !== '';
                });
        }

        function parsePipeRows(value, columns) {
            return normalizeLines(value).map(function(line) {
                var parts = line.split('|').map(function(part) {
                    return $.trim(part);
                });

                while (parts.length < columns) {
                    parts.push('');
                }

                return parts.slice(0, columns);
            });
        }

        function rowsToText(rows) {
            return $.map(rows || [], function(row) {
                return row.join(' | ');
            }).join('\n');
        }

        function encodePayload(payload) {
            return encodeURIComponent(JSON.stringify(payload || {}));
        }

        function decodePayload(value) {
            try {
                return JSON.parse(decodeURIComponent(value || ''));
            } catch (error) {
                return null;
            }
        }

        function baseBlockAttrs(type, payload) {
            return ' data-cms-block="' + escapeHtml(type) + '" data-cms-payload="' + escapeHtml(encodePayload(payload)) + '"';
        }

        function getSelectedJson(name) {
            try {
                var value = JSON.parse($form.find('[name="' + name + '"]').val() || '[]');
                return $.isArray(value) ? value : [];
            } catch (error) {
                return [];
            }
        }

        function setSelectedJson(name, items) {
            $form.find('[name="' + name + '"]').val(JSON.stringify(items || []));
        }

        function collectPayload(type) {
            var selected;
            var manualRows;

            if (type === 'hero_section') {
                return {
                    h1: $form.find('[name="heroSectionH1"]').val(),
                    subtext: $form.find('[name="heroSectionSubtext"]').val(),
                    bullets: $form.find('[name="heroSectionBullets"]').val(),
                    cta_label: $form.find('[name="heroSectionCtaLabel"]').val(),
                    cta_url: $form.find('[name="heroSectionCtaUrl"]').val() || '#',
                    hotline: $form.find('[name="heroSectionHotline"]').val(),
                    image: $form.find('[name="heroSectionImage"]').val(),
                    image_alt: $form.find('[name="heroSectionImageAlt"]').val()
                };
            }

            if (type === 'faq') {
                return {items: parsePipeRows($form.find('[name="faqItems"]').val(), 2)};
            }

            if (type === 'cta') {
                return {
                    title: $form.find('[name="ctaTitle"]').val(),
                    description: $form.find('[name="ctaDescription"]').val(),
                    button: $form.find('[name="ctaButton"]').val(),
                    url: $form.find('[name="ctaUrl"]').val() || '#'
                };
            }

            if (type === 'pricing') {
                return {items: parsePipeRows($form.find('[name="pricingItems"]').val(), 3)};
            }

            if (type === 'gallery') {
                manualRows = parsePipeRows($form.find('[name="galleryItems"]').val(), 3).map(function(row) {
                    return {url: row[0], alt: row[1], caption: row[2]};
                });
                return {items: manualRows};
            }

            if (type === 'related') {
                selected = getSelectedJson('relatedSelectedItems');
                manualRows = parsePipeRows($form.find('[name="relatedItems"]').val(), 3).map(function(row) {
                    return {title: row[0], url: row[1], description: row[2]};
                });
                return {items: selected.concat(manualRows)};
            }

            if (type === 'lead') {
                return {
                    title: $form.find('[name="leadTitle"]').val() || 'Nhận tư vấn miễn phí',
                    description: $form.find('[name="leadDescription"]').val()
                };
            }

            return {};
        }

        function validatePayload(type, payload) {
            if (type === 'hero_section' && !payload.h1) {
                return 'Hero Section cần tiêu đề H1.';
            }
            if (type === 'faq' && (!payload.items || !payload.items.length)) {
                return 'FAQ cần ít nhất một câu hỏi.';
            }
            if (type === 'cta' && !payload.title && !payload.description) {
                return 'CTA cần tiêu đề hoặc mô tả.';
            }
            if (type === 'pricing' && (!payload.items || !payload.items.length)) {
                return 'Bảng giá cần ít nhất một dòng.';
            }
            if (type === 'gallery' && (!payload.items || !payload.items.length)) {
                return 'Gallery cần ít nhất một ảnh.';
            }
            if (type === 'related' && (!payload.items || !payload.items.length)) {
                return 'Related posts cần ít nhất một bài.';
            }
            if (type === 'lead' && !payload.title) {
                return 'Form lead cần tiêu đề.';
            }
            return '';
        }

        function buildFaqSchema(payload) {
            var questions = $.map(payload.items || [], function(row) {
                if (!row[0] || !row[1]) { return null; }
                return {
                    '@type': 'Question',
                    name: row[0],
                    acceptedAnswer: {'@type': 'Answer', text: row[1]}
                };
            });
            if (!questions.length) { return ''; }
            return '<script type="application/ld+json">' + JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'FAQPage',
                mainEntity: questions
            }) + '<\/script>';
        }

        function buildBlockHtml(type, payload) {
            payload = payload || collectPayload(type);

            if (type === 'hero_section') {
                var bulletLines = String(payload.bullets || '').split(/\r?\n/).filter(function(l) { return $.trim(l); });
                var bulletsHtml = bulletLines.length
                    ? '<ul class="cms-hero-bullets">' + $.map(bulletLines, function(line) {
                        return '<li>' + escapeHtml($.trim(line)) + '</li>';
                    }).join('') + '</ul>'
                    : '';
                var hotlineHtml = payload.hotline
                    ? '<p class="cms-hero-hotline"><a href="tel:' + escapeHtml(payload.hotline.replace(/[^\d+]/g, '')) + '">&#128241; Hotline: ' + escapeHtml(payload.hotline) + '</a></p>'
                    : '';
                var imageHtml = payload.image
                    ? '<div class="cms-hero-image"><img src="' + escapeHtml(payload.image) + '" alt="' + escapeHtml(payload.image_alt || '') + '" loading="lazy"></div>'
                    : '';
                var ctaHtml = payload.cta_label
                    ? '<a class="cms-block-button" href="' + escapeHtml(payload.cta_url || '#') + '">' + escapeHtml(payload.cta_label) + '</a>'
                    : '';

                return '<section class="cms-block cms-block-hero-section"' + baseBlockAttrs(type, payload) + '>' +
                    '<div class="cms-hero-content">' +
                    (payload.h1 ? '<p class="cms-hero-title">' + escapeHtml(payload.h1) + '</p>' : '') +
                    (payload.subtext ? '<p class="cms-hero-subtext">' + escapeHtml(payload.subtext) + '</p>' : '') +
                    bulletsHtml +
                    ctaHtml +
                    hotlineHtml +
                    '</div>' +
                    imageHtml +
                    '</section>';
            }

            if (type === 'faq') {
                return '<section class="cms-block cms-block-faq"' + baseBlockAttrs(type, payload) + '>' +
                    '<h2>Câu hỏi thường gặp</h2>' +
                    $.map(payload.items || [], function(row) {
                        return '<details class="cms-faq-item"><summary>' + escapeHtml(row[0]) + '</summary><p>' + escapeHtml(row[1]) + '</p></details>';
                    }).join('') +
                    buildFaqSchema(payload) +
                    '</section>';
            }

            if (type === 'cta') {
                return '<section class="cms-block cms-block-cta"' + baseBlockAttrs(type, payload) + '>' +
                    '<div class="cms-block-cta-content">' +
                    (payload.title ? '<h2>' + escapeHtml(payload.title) + '</h2>' : '') +
                    (payload.description ? '<p>' + escapeHtml(payload.description) + '</p>' : '') +
                    '</div>' +
                    (payload.button ? '<a class="cms-block-button" href="' + escapeHtml(payload.url || '#') + '">' + escapeHtml(payload.button) + '</a>' : '') +
                    '</section>';
            }

            if (type === 'pricing') {
                return '<section class="cms-block cms-block-pricing"' + baseBlockAttrs(type, payload) + '>' +
                    '<h2>Bảng giá tham khảo</h2>' +
                    '<table><thead><tr><th>Hạng mục</th><th>Đơn giá</th><th>Ghi chú</th></tr></thead><tbody>' +
                    $.map(payload.items || [], function(row) {
                        return '<tr><td>' + escapeHtml(row[0]) + '</td><td>' + escapeHtml(row[1]) + '</td><td>' + escapeHtml(row[2]) + '</td></tr>';
                    }).join('') +
                    '</tbody></table>' +
                    '</section>';
            }

            if (type === 'gallery') {
                return '<section class="cms-block cms-block-gallery"' + baseBlockAttrs(type, payload) + '>' +
                    '<h2>Hình ảnh thực tế</h2>' +
                    '<div class="cms-block-gallery-grid">' +
                    $.map(payload.items || [], function(item) {
                        return '<figure><img src="' + escapeHtml(item.url || '') + '" alt="' + escapeHtml(item.alt || '') + '">' +
                            (item.caption ? '<figcaption>' + escapeHtml(item.caption) + '</figcaption>' : '') +
                            '</figure>';
                    }).join('') +
                    '</div>' +
                    '</section>';
            }

            if (type === 'related') {
                return '<section class="cms-block cms-block-related" data-cms-block="related" data-cms-payload="' + escapeHtml(encodePayload(payload)) + '">' +
                    '<h2>Bài viết liên quan</h2>' +
                    '<div class="cms-block-related-list">' +
                    $.map(payload.items || [], function(item) {
                        return '<a class="cms-block-related-item" href="' + escapeHtml(item.url || '#') + '">' +
                            '<strong>' + escapeHtml(item.title || '') + '</strong>' +
                            (item.description ? '<span>' + escapeHtml(item.description) + '</span>' : '') +
                            '</a>';
                    }).join('') +
                    '</div>' +
                    '</section>';
            }

            if (type === 'lead') {
                return '<section class="cms-block cms-block-lead"' + baseBlockAttrs(type, payload) + '>' +
                    '<div><h2>' + escapeHtml(payload.title || 'Nhận tư vấn miễn phí') + '</h2>' +
                    (payload.description ? '<p>' + escapeHtml(payload.description) + '</p>' : '') +
                    '</div>' +
                    '<form class="cms-block-lead-form" action="/lien-he" method="get">' +
                    '<input type="text" name="name" placeholder="Họ tên">' +
                    '<input type="tel" name="phone" placeholder="Số điện thoại">' +
                    '<button type="submit">Gửi thông tin</button>' +
                    '</form>' +
                    '</section>';
            }

            return '';
        }

        function populateBlockForm(type, payload) {
            if (!payload) { return; }

            if (type === 'hero_section') {
                $form.find('[name="heroSectionH1"]').val(payload.h1 || '');
                $form.find('[name="heroSectionSubtext"]').val(payload.subtext || '');
                $form.find('[name="heroSectionBullets"]').val(payload.bullets || '');
                $form.find('[name="heroSectionCtaLabel"]').val(payload.cta_label || '');
                $form.find('[name="heroSectionCtaUrl"]').val(payload.cta_url || '');
                $form.find('[name="heroSectionHotline"]').val(payload.hotline || '');
                $form.find('[name="heroSectionImage"]').val(payload.image || '');
                $form.find('[name="heroSectionImageAlt"]').val(payload.image_alt || '');
            } else if (type === 'faq') {
                $form.find('[name="faqItems"]').val(rowsToText(payload.items || []));
            } else if (type === 'cta') {
                $form.find('[name="ctaTitle"]').val(payload.title || '');
                $form.find('[name="ctaDescription"]').val(payload.description || '');
                $form.find('[name="ctaButton"]').val(payload.button || '');
                $form.find('[name="ctaUrl"]').val(payload.url || '');
            } else if (type === 'pricing') {
                $form.find('[name="pricingItems"]').val(rowsToText(payload.items || []));
            } else if (type === 'gallery') {
                var manualItems = (payload.items || []).map(function(item) {
                    return [item.url || '', item.alt || '', item.caption || ''];
                });
                $form.find('[name="galleryItems"]').val(rowsToText(manualItems));
            } else if (type === 'related') {
                var backendItems = [];
                var manualRelated = [];
                $.each(payload.items || [], function(index, item) {
                    if (item.id) {
                        backendItems.push(item);
                    } else {
                        manualRelated.push([item.title || '', item.url || '', item.description || '']);
                    }
                });
                setSelectedJson('relatedSelectedItems', backendItems);
                renderRelatedSelected(backendItems);
                $form.find('[name="relatedItems"]').val(rowsToText(manualRelated));
            } else if (type === 'lead') {
                $form.find('[name="leadTitle"]').val(payload.title || '');
                $form.find('[name="leadDescription"]').val(payload.description || '');
            }
        }

        function renderPreview() {
            var payload = collectPayload(activeBlockType);
            var html = buildBlockHtml(activeBlockType, payload);
            $preview.html(html || '<div class="text-muted">Chưa có dữ liệu preview.</div>');
        }

        function insertIntoEditor(editorId, html) {
            if (window.CKEDITOR && CKEDITOR.instances[editorId]) {
                CKEDITOR.instances[editorId].insertHtml(html);
                CKEDITOR.instances[editorId].updateElement();
                return;
            }
            var $textarea = $('#' + editorId);
            $textarea.val(($textarea.val() || '') + '\n' + html);
        }

        function updateEditorBlock(editorId, html) {
            if (activeEditorBlock && activeEditorBlock.setHtml) {
                var $replacement = $('<div>').html(html).children().first();
                activeEditorBlock.setHtml($replacement.html());
                $.each($replacement[0].attributes, function(index, attr) {
                    activeEditorBlock.setAttribute(attr.name, attr.value);
                });
                CKEDITOR.instances[editorId].updateElement();
                return;
            }
            insertIntoEditor(editorId, html);
        }

        function getBlockPayloadFromElement(element) {
            var type = element.getAttribute('data-cms-block');
            var payload = decodePayload(element.getAttribute('data-cms-payload')) || extractPayloadFromBlock(element, type);
            return {
                type: type === 'related-posts' ? 'related' : type,
                payload: payload
            };
        }

        function findCmsBlockElement(element) {
            if (element && element.type !== CKEDITOR.NODE_ELEMENT && element.getParent) {
                element = element.getParent();
            }
            while (element && element.type === CKEDITOR.NODE_ELEMENT) {
                if (element.hasAttribute && element.hasAttribute('data-cms-block')) {
                    return element;
                }
                element = element.getParent();
            }
            return null;
        }

        function extractPayloadFromBlock(element, type) {
            var $block = $(element.$);
            var payloadType = type === 'related-posts' ? 'related' : type;

            if (payloadType === 'hero_section') {
                var bulletsText = $block.find('.cms-hero-bullets li').map(function() {
                    return $.trim($(this).text());
                }).get().join('\n');
                var hotlineEl = $block.find('.cms-hero-hotline a').first();
                return {
                    h1: $.trim($block.find('.cms-hero-title').first().text()),
                    subtext: $.trim($block.find('.cms-hero-subtext').first().text()),
                    bullets: bulletsText,
                    cta_label: $.trim($block.find('.cms-block-button').first().text()),
                    cta_url: $block.find('.cms-block-button').first().attr('href') || '#',
                    hotline: $.trim(hotlineEl.text()).replace(/^Hotline:\s*/, ''),
                    image: $block.find('.cms-hero-image img').first().attr('src') || '',
                    image_alt: $block.find('.cms-hero-image img').first().attr('alt') || ''
                };
            }

            if (payloadType === 'faq') {
                return {items: $block.find('details').map(function() {
                    return [[$.trim($(this).find('summary').first().text()), $.trim($(this).find('p').first().text())]];
                }).get()};
            }
            if (payloadType === 'cta') {
                return {
                    title: $.trim($block.find('h2').first().text()),
                    description: $.trim($block.find('p').first().text()),
                    button: $.trim($block.find('a').first().text()),
                    url: $block.find('a').first().attr('href') || '#'
                };
            }
            if (payloadType === 'pricing') {
                return {items: $block.find('tbody tr').map(function() {
                    var $td = $(this).find('td');
                    return [[$.trim($td.eq(0).text()), $.trim($td.eq(1).text()), $.trim($td.eq(2).text())]];
                }).get()};
            }
            if (payloadType === 'gallery') {
                return {items: $block.find('figure').map(function() {
                    var $figure = $(this);
                    var $img = $figure.find('img').first();
                    return {url: $img.attr('src') || '', alt: $img.attr('alt') || '', caption: $.trim($figure.find('figcaption').first().text())};
                }).get()};
            }
            if (payloadType === 'related') {
                return {items: $block.find('a').map(function() {
                    var $item = $(this);
                    return {title: $.trim($item.find('strong').first().text()) || $.trim($item.text()), url: $item.attr('href') || '', description: $.trim($item.find('span').first().text())};
                }).get()};
            }
            if (payloadType === 'lead') {
                return {
                    title: $.trim($block.find('h2').first().text()),
                    description: $.trim($block.find('p').first().text())
                };
            }
            return null;
        }

        function attachEditorBlockClicks(editor) {
            if (editor._cmsBlockClickAttached) { return; }
            editor._cmsBlockClickAttached = true;

            editor.on('contentDom', function() {
                editor.document.on('click', function(event) {
                    var element = event.data.getTarget();
                    var block = findCmsBlockElement(element);
                    if (!block) { return; }
                    var data = getBlockPayloadFromElement(block);
                    if (!data.type || !data.payload) { return; }
                    activeEditorId = editor.name;
                    relatedSearchUrl = $('[data-cms-block-toolbar][data-target-editor="' + activeEditorId + '"]').data('related-search-url');
                    relatedCurrentId = $('[data-cms-block-toolbar][data-target-editor="' + activeEditorId + '"]').data('current-id') || 0;
                    event.data.preventDefault();
                    showBlockForm(data.type, data.payload, block);
                });
            });

            editor.on('doubleclick', function(event) {
                var element = event.data.element;
                var block = findCmsBlockElement(element);
                if (!block) { return; }
                var data = getBlockPayloadFromElement(block);
                if (!data.type || !data.payload) { return; }
                activeEditorId = editor.name;
                relatedSearchUrl = $('[data-cms-block-toolbar][data-target-editor="' + activeEditorId + '"]').data('related-search-url');
                relatedCurrentId = $('[data-cms-block-toolbar][data-target-editor="' + activeEditorId + '"]').data('current-id') || 0;
                showBlockForm(data.type, data.payload, block);
            });
        }

        function renderRelatedSelected(items) {
            var $selected = $form.find('[data-cms-related-selected]');
            $selected.empty();
            $.each(items || [], function(index, item) {
                var $item = $('<div>').addClass('cms-related-selected-item').attr('data-related-index', index);
                $('<strong>').text(item.title || '').appendTo($item);
                $('<span>').text(item.url || '').appendTo($item);
                $('<button type="button" class="btn btn-link btn-sm" data-cms-related-remove>&times;</button>').appendTo($item);
                $selected.append($item);
            });
        }

        function addRelatedItem(item) {
            var items = getSelectedJson('relatedSelectedItems');
            var exists = false;
            $.each(items, function(index, selected) {
                if (String(selected.url) === String(item.url)) { exists = true; }
            });
            if (!exists) {
                items.push(item);
                setSelectedJson('relatedSelectedItems', items);
                renderRelatedSelected(items);
                renderPreview();
            }
        }

        function searchRelatedPosts(query) {
            var $results = $form.find('[data-cms-related-results]');
            if (!relatedSearchUrl || query.length < 2) {
                $results.empty();
                return;
            }
            $results.html('<div class="text-muted">Đang tìm...</div>');
            $.ajax({
                type: 'GET',
                url: relatedSearchUrl,
                data: {q: query, currentId: relatedCurrentId || 0},
                success: function(response) {
                    var items = response.items || [];
                    if (!items.length) {
                        $results.html('<div class="text-muted">Không tìm thấy bài phù hợp.</div>');
                        return;
                    }
                    $results.empty();
                    $.each(items, function(index, item) {
                        var $item = $('<button type="button" class="cms-related-result" data-cms-related-add></button>');
                        $item.data('related-item', item);
                        $('<strong>').text(item.title || '').appendTo($item);
                        $('<span>').text(item.url || '').appendTo($item);
                        $results.append($item);
                    });
                },
                error: function() {
                    $results.html('<div class="text-danger">Không tìm được bài viết.</div>');
                }
            });
        }

        $(document).on('click', '[data-cms-block-open]', function(event) {
            event.preventDefault();
            var $toolbar = $(this).closest('[data-cms-block-toolbar]');
            activeEditorId = $toolbar.data('target-editor');
            relatedSearchUrl = $toolbar.data('related-search-url');
            relatedCurrentId = $toolbar.data('current-id') || 0;
            showBlockForm($(this).data('cms-block-open'));
        });

        $(document).on('input', '[data-cms-block-form] input, [data-cms-block-form] textarea', function() {
            renderPreview();
        });

        $(document).on('change', '[name="relatedSelectedItems"]', function() {
            renderPreview();
        });

        $(document).on('input', '[name="relatedSearch"]', function() {
            var query = $(this).val();
            window.clearTimeout(relatedSearchTimer);
            relatedSearchTimer = window.setTimeout(function() {
                searchRelatedPosts(query);
            }, 250);
        });

        $(document).on('click', '[data-cms-related-add]', function() {
            addRelatedItem($(this).data('related-item'));
        });

        $(document).on('click', '[data-cms-related-remove]', function() {
            var index = $(this).closest('[data-related-index]').data('related-index');
            var items = getSelectedJson('relatedSelectedItems');
            items.splice(index, 1);
            setSelectedJson('relatedSelectedItems', items);
            renderRelatedSelected(items);
            renderPreview();
        });

        $(document).on('click', '[data-cms-block-preview-toggle]', function(event) {
            event.preventDefault();
            renderPreview();
            $preview.toggle();
        });

        $(document).on('click', '[data-cms-block-insert]', function(event) {
            event.preventDefault();
            var payload = collectPayload(activeBlockType);
            var validationError = validatePayload(activeBlockType, payload);
            if (validationError) {
                alert(validationError);
                return;
            }
            var html = buildBlockHtml(activeBlockType, payload);
            updateEditorBlock(activeEditorId, html);
            closeBlockModal();
        });

        if (window.CKEDITOR) {
            CKEDITOR.on('instanceReady', function(event) {
                attachEditorBlockClicks(event.editor);
            });
            $.each(CKEDITOR.instances, function(id, editor) {
                if (editor.status === 'ready') {
                    attachEditorBlockClicks(editor);
                }
            });
        }
    }

    function initAdminSidebarState() {
        var storageKey = 'kientruc_admin_sidebar_open';

        function setSessionCookie(value) {
            document.cookie = storageKey + '=' + value + '; path=/; SameSite=Lax';
        }

        function persistSidebarState() {
            var isCollapsed = $('body').hasClass('open');
            var value = isCollapsed ? '1' : '0';

            try {
                sessionStorage.setItem(storageKey, value);
            } catch (error) {}

            setSessionCookie(value);
        }

        try {
            if (sessionStorage.getItem(storageKey) === '1') {
                $('body').addClass('open');
                setSessionCookie('1');
            }
        } catch (error) {}

        var menuToggle = document.getElementById('menuToggle');

        if (!menuToggle) {
            return;
        }

        menuToggle.addEventListener('click', function() {
            setTimeout(persistSidebarState, 0);
        });
    }

    /**
     * Media Picker — allows selecting an image from the Media Library
     * inside any form that has a [data-media-picker] wrapper.
     * The picker URL is read from data-picker-url on the trigger button.
     */
    function initMediaPicker() {

        // ── Legacy single-picker (NewsCategory) ─────────────────────────────
        // Kept for backward compatibility with #mediaPickerModal / #mediaPicker_open
        var $legacyModal = $('#mediaPickerModal');
        if ($legacyModal.length) {
            initSinglePicker($legacyModal);
        }

        // ── New data-attribute-driven multi-picker ────────────────────────────
        // Each [data-media-picker-block] is an independent picker instance
        $('[data-media-picker-block]').each(function () {
            var pickerId    = $(this).data('picker-id');
            var uploadPath  = $(this).data('upload-path') || '';
            var $modal      = $('#' + pickerId + 'Modal');

            if (!$modal.length || !pickerId) return;

            initPickerInstance(pickerId, uploadPath, $modal);
        });

        // ── Legacy picker initializer ────────────────────────────────────────
        function initSinglePicker($modal) {
            var $body     = $('#mediaPickerModalBody');
            var $confirm  = $('#mediaPickerConfirm');
            var $selName  = $('#mediaPickerSelectedName');
            var loaded    = false;
            var selectedUrl = null;

            $modal.appendTo('body');
            var pickerUrl = $modal.data('picker-url') || '';

            function cleanupModalBackdrop() {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
            }

            function hideModal() {
                $modal.removeClass('in show').attr('aria-hidden', 'true').hide();
                cleanupModalBackdrop();
            }

            function showModal() {
                hideModal();
                $('<div class="modal-backdrop fade in show media-picker-backdrop"></div>').appendTo('body');
                $('body').addClass('modal-open');
                $modal.show().addClass('in show').attr('aria-hidden', 'false').focus();
            }

            $(document).on('click', '#mediaPicker_close, #mediaPicker_cancel, .media-picker-backdrop', function (e) {
                e.preventDefault(); hideModal();
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) hideModal();
            });
            $(document).on('click', '#mediaPicker_open', function (e) {
                e.preventDefault(); e.stopPropagation();
                selectedUrl = null;
                $confirm.prop('disabled', true);
                $selName.text('');
                $body.find('.media-picker-item').removeClass('is-selected');
                showModal();
                if (loaded) return;
                loaded = true;
                $.get(pickerUrl)
                    .done(function (html) { $body.html(html); bindLegacyItems(); })
                    .fail(function () { $body.html('<div class="alert alert-danger">Không thể tải thư viện ảnh.</div>'); });
            });

            function bindLegacyItems() {
                $body.find('.media-picker-item').off('click').on('click', function () {
                    $body.find('.media-picker-item').removeClass('is-selected');
                    $(this).addClass('is-selected');
                    selectedUrl = $(this).data('url');
                    $selName.text($(this).data('filename'));
                    $confirm.prop('disabled', false);
                });
                $body.find('#mediaPickerSearch').off('input').on('input', function () {
                    var q = $(this).val().toLowerCase();
                    $body.find('.media-picker-item').each(function () {
                        $(this).toggle(($(this).data('filename') || '').toLowerCase().indexOf(q) !== -1);
                    });
                });
                $body.find('#mediaPickerFilterFolderSelect').off('change').on('change', function () {
                    var folder = $(this).val();
                    var url = pickerUrl + (pickerUrl.indexOf('?') !== -1 ? '&' : '?') + 'folder=' + encodeURIComponent(folder);
                    $.get(url)
                        .done(function (html) { $body.html(html); bindLegacyItems(); })
                        .fail(function () { $body.html('<div class="alert alert-danger">Không thể tải thư viện ảnh.</div>'); });
                });
                bindUpload($body, pickerUrl, bindLegacyItems);
            }

            $(document).on('click', '#mediaPickerConfirm', function () {
                if (!selectedUrl) return;
                $('.media-picker-input').val(selectedUrl);
                $('#mediaPicker_display').val(selectedUrl);
                $('#mediaPicker_preview_img').attr('src', selectedUrl);
                $('#mediaPicker_preview_wrap').show();
                hideModal();
            });
            $(document).on('click', '#mediaPicker_clear', function () {
                $('.media-picker-input').val('');
                $('#mediaPicker_display').val('');
                $('#mediaPicker_preview_img').attr('src', '');
                $('#mediaPicker_preview_wrap').hide();
            });
        }

        // ── Per-instance picker initializer ─────────────────────────────────
        function initPickerInstance(pickerId, uploadPath, $modal) {
            var $body        = $('#' + pickerId + '_body');
            var $confirm     = $('#' + pickerId + '_confirm');
            var $selName     = $('#' + pickerId + '_selName');
            var $display     = $('#' + pickerId + '_display');
            var $previewImg  = $('#' + pickerId + '_preview_img');
            var $placeholder = $('#' + pickerId + '_placeholder');
            var $clearBtn    = $('#' + pickerId + '_clear_btn');
            var loaded       = false;
            var selectedUrl  = null;

            $modal.appendTo('body');
            var pickerUrl = $modal.data('picker-url') || '';

            function cleanupBackdrop() {
                $('.media-picker-backdrop-' + pickerId).remove();
                if (!$('.modal:visible').length) {
                    $('body').removeClass('modal-open').css('padding-right', '');
                }
            }

            function hideModal() {
                $modal.removeClass('in show').attr('aria-hidden', 'true').hide();
                cleanupBackdrop();
            }

            function showModal() {
                hideModal();
                $('<div class="modal-backdrop fade in show media-picker-backdrop media-picker-backdrop-' + pickerId + '"></div>').appendTo('body');
                $('body').addClass('modal-open');
                $modal.show().addClass('in show').attr('aria-hidden', 'false').focus();
            }

            // Open button
            $(document).on('click', '[data-media-picker-open][data-picker-id="' + pickerId + '"]', function (e) {
                e.preventDefault(); e.stopPropagation();
                selectedUrl = null;
                $confirm.prop('disabled', true);
                $selName.text('');
                $body.find('.media-picker-item').removeClass('is-selected');
                showModal();
                if (loaded) return;
                loaded = true;
                $.get(pickerUrl)
                    .done(function (html) { $body.html(html); bindItems(); })
                    .fail(function () { $body.html('<div class="alert alert-danger">Không thể tải thư viện ảnh.</div>'); });
            });

            // Close buttons
            $(document).on('click', '[data-media-picker-close][data-picker-id="' + pickerId + '"]', function (e) {
                e.preventDefault(); hideModal();
            });

            // ESC key
            $(document).on('keydown.mediapicker_' + pickerId, function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) hideModal();
            });

            function bindItems() {
                $body.find('.media-picker-item').off('click').on('click', function () {
                    $body.find('.media-picker-item').removeClass('is-selected');
                    $(this).addClass('is-selected');
                    selectedUrl = $(this).data('url');
                    $selName.text($(this).data('filename'));
                    $confirm.prop('disabled', false);
                });
                $body.find('#mediaPickerSearch').off('input').on('input', function () {
                    var q = $(this).val().toLowerCase();
                    $body.find('.media-picker-item').each(function () {
                        $(this).toggle(($(this).data('filename') || '').toLowerCase().indexOf(q) !== -1);
                    });
                });
                $body.find('#mediaPickerFilterFolderSelect').off('change').on('change', function () {
                    var folder = $(this).val();
                    var url = pickerUrl + (pickerUrl.indexOf('?') !== -1 ? '&' : '?') + 'folder=' + encodeURIComponent(folder);
                    $.get(url)
                        .done(function (html) { $body.html(html); bindItems(); })
                        .fail(function () { $body.html('<div class="alert alert-danger">Không thể tải thư viện ảnh.</div>'); });
                });
                bindUpload($body, pickerUrl, bindItems);
            }

            // Confirm selection — ghi full URL vào POST param riêng (_media_picker_url)
            $(document).on('click', '[data-media-picker-confirm][data-picker-id="' + pickerId + '"]', function () {
                if (!selectedUrl) return;
                $('#' + pickerId + '_url_input').val(selectedUrl);
                $display.val(selectedUrl);
                $previewImg.attr('src', selectedUrl).show();
                $placeholder.hide();
                $clearBtn.show();
                hideModal();
            });

            // Clear button
            $(document).on('click', '[data-media-picker-clear][data-picker-id="' + pickerId + '"]', function () {
                $('#' + pickerId + '_url_input').val('');
                $display.val('');
                $previewImg.attr('src', '').hide();
                $placeholder.show();
                $clearBtn.hide();
            });
        }

        // Shared upload helper - Dropzone multi-file queue
        function bindUpload($body, pickerUrl, afterUploadCallback) {
            var $dropzone   = $body.find('#mediaPickerDropzone');
            var $fileInput  = $body.find('#mediaPickerFileInput');
            var $uploadBtn  = $body.find('#mediaPickerUploadBtn');
            var $queue      = $body.find('#mediaPickerUploadQueue');
            var $queueCount = $body.find('#mediaPickerQueueCount');
            var fileQueue   = [];

            $dropzone.on('dragover dragenter', function (e) {
                e.preventDefault(); e.stopPropagation();
                $dropzone.css({ background: '#dceeff', 'border-color': '#4a90d9' });
            }).on('dragleave dragend', function (e) {
                e.preventDefault(); e.stopPropagation();
                $dropzone.css({ background: '#f0f7ff', 'border-color': '#90bce8' });
            }).on('drop', function (e) {
                e.preventDefault(); e.stopPropagation();
                $dropzone.css({ background: '#f0f7ff', 'border-color': '#90bce8' });
                var dt = e.originalEvent.dataTransfer;
                if (dt && dt.files.length) { addFilesToQueue(dt.files); }
            });

            $fileInput.off('change').on('change', function () {
                if (this.files && this.files.length) { addFilesToQueue(this.files); $(this).val(''); }
            });

            function addFilesToQueue(files) {
                for (var i = 0; i < files.length; i++) {
                    var f = files[i];
                    if (!f.type.startsWith('image/')) { continue; }
                    var idx = fileQueue.length;
                    fileQueue.push(f);
                    var $item = $('<div class="mp-queue-item" style="display:flex;align-items:center;gap:8px;margin-bottom:5px;" data-queue-idx="' + idx + '">' +
                        '<span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + f.name + '</span>' +
                        '<div class="progress" style="flex:2;height:7px;margin:0;min-width:80px;"><div class="progress-bar" style="width:0%;transition:width .3s;"></div></div>' +
                        '<span class="mp-queue-status" style="font-size:11px;color:#888;min-width:45px;text-align:right;">Chờ</span>' +
                        '<button type="button" class="mp-queue-remove" title="Xóa" style="background:none;border:none;color:#e74c3c;cursor:pointer;padding:0 4px;font-size:14px;line-height:1;">&#10005;</button>' +
                        '</div>');
                    (function($row, qIdx) {
                        $row.find('.mp-queue-remove').on('click', function() {
                            fileQueue[qIdx] = null;
                            $row.remove();
                            updateCount();
                        });
                    })($item, idx);
                    $queue.append($item);
                }
                updateCount();
            }

            function updateCount() {
                var n = fileQueue.filter(function(f) { return f !== null; }).length;
                $queueCount.text(n > 0 ? n + ' ảnh trong hàng chờ' : '');
                $uploadBtn.prop('disabled', n === 0);
            }

            $uploadBtn.off('click').on('click', function () {
                var active = fileQueue.map(function(f, i) { return {f: f, i: i}; }).filter(function(x) { return x.f !== null; });
                if (active.length === 0) { return; }
                $uploadBtn.prop('disabled', true);

                var folder = $body.find('#mediaPickerUploadFolderSelect').val() || '';
                var newFolder = $body.find('#mediaPickerUploadNewFolder').val() || '';

                var pos = 0;
                function uploadNext() {
                    if (pos >= active.length) {
                        fileQueue = []; $queue.empty(); updateCount();
                        var reloadUrl = pickerUrl;
                        var targetFolder = newFolder !== '' ? newFolder : folder;
                        if (targetFolder !== '') {
                            reloadUrl += (reloadUrl.indexOf('?') !== -1 ? '&' : '?') + 'folder=' + encodeURIComponent(targetFolder);
                        }
                        $.get(reloadUrl).done(function (html) { $body.html(html); afterUploadCallback(); });
                        return;
                    }
                    var entry  = active[pos];
                    var f      = entry.f;
                    var $item  = $queue.find('[data-queue-idx="' + entry.i + '"]');
                    var $bar   = $item.find('.progress-bar');
                    var $stat  = $item.find('.mp-queue-status');
                    $item.find('.mp-queue-remove').hide();
                    $stat.text('Đang tải...').css('color', '#5b9bd5');
                    var formData = new FormData();
                    formData.append('file', f);
                    formData.append('folder', folder);
                    formData.append('newFolder', newFolder);
                    $.ajax({
                        url: '/admin/media/upload', type: 'POST',
                        data: formData, processData: false, contentType: false,
                        xhr: function () {
                            var xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function (evt) {
                                if (evt.lengthComputable) { $bar.css('width', parseInt((evt.loaded / evt.total) * 100) + '%'); }
                            }, false);
                            return xhr;
                        },
                        success: function (res) {
                            if (res.status === 'success') {
                                $bar.css('width', '100%').addClass('progress-bar-success');
                                $stat.text('Xong').css('color', '#27ae60');
                            } else { $stat.text('Lỗi').css('color', '#e74c3c'); $bar.addClass('progress-bar-danger'); }
                            pos++; uploadNext();
                        },
                        error: function () { $stat.text('Lỗi').css('color', '#e74c3c'); pos++; uploadNext(); }
                    });
                }
                uploadNext();
            });
        }
    }
});

// Handling the modal confirmation message.
$(document).on('submit', 'form[data-confirmation]', function (event) {
    var $form = $(this),
        $confirm = $($form.find('button').data('target'));

    if ($confirm.data('result') !== 'yes') {
        //cancel submit event
        event.preventDefault();

        $confirm
            .off('click', '#btnYes')
            .on('click', '#btnYes', function () {
                $confirm.data('result', 'yes');
                $form.find('input[type="submit"]').attr('disabled', 'disabled');
                $form.submit();
            })
            .modal('show');
    }
});
