import 'nestable2/dist/jquery.nestable.min.js';

// Quản lý ảnh trong 1 GalleryAlbum: thêm (từ Media Library picker, AJAX),
// sửa caption/alt (debounce AJAX), xóa (AJAX), sắp xếp (nestable + AJAX).
// Picker (#albumImagePicker) chỉ "staging" — admin.js đổ URL đã chọn vào
// #albumImagePicker_url_input, nút "Thêm ảnh vào album" mới thực sự AJAX add.
const GalleryAlbumEditor = {
    config: {},
    saveTimers: {},

    init: function (options) {
        this.config = $.extend({
            addImageUrl: '',
            updateImageUrlBase: '',
            deleteImageUrlBase: '',
            reorderUrl: '',
            deleteToken: '',
        }, options);

        if ($.fn.nestable) {
            $('#album-image-nestable').nestable({ maxDepth: 1 });
        }

        this.bindEvents();
    },

    bindEvents: function () {
        const self = this;

        // Bật/tắt nút "Thêm ảnh vào album" theo trạng thái staging input
        $(document).on('mediaPickerStaged.gallery', function () {
            self.syncAddButtonState();
        });
        // admin.js không phát sự kiện riêng khi confirm — theo dõi thay đổi giá trị input
        var $stagingInput = $('#albumImagePicker_url_input');
        var lastVal = $stagingInput.val();
        setInterval(function () {
            var val = $('#albumImagePicker_url_input').val();
            if (val !== lastVal) {
                lastVal = val;
                self.syncAddButtonState();
            }
        }, 300);

        $(document).on('click', '#albumImageAddBtn', function () {
            self.addImage();
        });

        $(document).on('click', '.album-image-delete', function () {
            self.deleteImage($(this).closest('.album-image-item'));
        });

        $(document).on('input', '.album-image-caption, .album-image-alt', function () {
            self.scheduleUpdate($(this).closest('.album-image-item'));
        });

        $('#album-image-nestable').on('change', function () {
            self.saveOrder();
        });
    },

    syncAddButtonState: function () {
        var val = $('#albumImagePicker_url_input').val();
        $('#albumImageAddBtn').prop('disabled', !val);
    },

    addImage: function () {
        const self = this;
        var imageUrl = $('#albumImagePicker_url_input').val();
        if (!imageUrl) return;

        $.ajax({
            url: this.config.addImageUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ imageUrl: imageUrl }),
            success: function (res) {
                if (!res.success) {
                    alert(res.message || 'Không thể thêm ảnh.');
                    return;
                }
                self.appendTile(res.image);
                self.resetStaging();
            },
            error: function () {
                alert('Lỗi kết nối khi thêm ảnh.');
            }
        });
    },

    resetStaging: function () {
        $('#albumImagePicker_url_input').val('');
        $('#albumImagePicker_display').val('');
        $('#albumImagePicker_preview_img').attr('src', '').hide();
        $('#albumImagePicker_placeholder').show();
        $('#albumImagePicker_clear_btn').hide();
        $('#albumImageAddBtn').prop('disabled', true);
    },

    appendTile: function (image) {
        $('#album-image-empty').remove();

        var $tile = $(
            '<li class="dd-item album-image-item" data-id="' + image.id + '" style="border:1px solid #eee;border-radius:4px;margin-bottom:8px;background:#fff;">' +
                '<div class="dd-handle" style="cursor:move;display:flex;align-items:center;gap:14px;padding:10px 14px;">' +
                    '<i class="fa fa-arrows" style="color:#bbb;"></i>' +
                    '<img src="' + image.thumb + '" alt="" style="width:70px;height:50px;object-fit:cover;border-radius:3px;background:#f5f5f5;">' +
                    '<div style="flex:1;display:flex;gap:10px;">' +
                        '<input type="text" class="form-control input-sm album-image-caption" placeholder="Chú thích ảnh" value="' + (image.caption || '') + '">' +
                        '<input type="text" class="form-control input-sm album-image-alt" placeholder="Alt text" value="' + (image.alt || '') + '">' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-danger album-image-delete" title="Xóa ảnh khỏi album">' +
                        '<i class="fa fa-trash"></i>' +
                    '</button>' +
                '</div>' +
            '</li>'
        );

        $('#album-image-list').append($tile);
    },

    deleteImage: function ($tile) {
        const self = this;
        if (!window.confirm('Xóa ảnh này khỏi album?')) return;

        var id = $tile.data('id');
        var url = this.config.deleteImageUrlBase.replace('__ID__', id);

        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ token: self.config.deleteToken }),
            success: function (res) {
                if (res.success) {
                    $tile.remove();
                    if (!$('#album-image-list').children().length) {
                        $('#album-image-nestable').after('<p class="text-muted" id="album-image-empty">Album chưa có ảnh nào.</p>');
                    }
                } else {
                    alert(res.message || 'Không thể xóa ảnh.');
                }
            },
            error: function () {
                alert('Lỗi kết nối khi xóa ảnh.');
            }
        });
    },

    scheduleUpdate: function ($tile) {
        const self = this;
        var id = $tile.data('id');
        clearTimeout(this.saveTimers[id]);
        this.saveTimers[id] = setTimeout(function () {
            self.updateImage($tile);
        }, 600);
    },

    updateImage: function ($tile) {
        var id = $tile.data('id');
        var url = this.config.updateImageUrlBase.replace('__ID__', id);

        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                caption: $tile.find('.album-image-caption').val(),
                alt: $tile.find('.album-image-alt').val(),
            })
        });
    },

    saveOrder: function () {
        var ids = $('#album-image-list').children('.dd-item').map(function () {
            return $(this).data('id');
        }).get();

        $.ajax({
            url: this.config.reorderUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ items: ids })
        });
    }
};

window.GalleryAlbumEditor = GalleryAlbumEditor;
