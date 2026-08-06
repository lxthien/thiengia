'use strict';

var $ = require('jquery');
require('@fancyapps/fancybox');

var homepageContact = {
    init: function () {
        var $form = $('#ka-homepage-contact-form');
        var $msg = $('#ka-contact-form-message');

        if ($form.length === 0) {
            return;
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            var $submitBtn = $form.find('button[type="submit"]');
            var originalBtnText = $submitBtn.text();

            $submitBtn.prop('disabled', true).text('Đang gửi...');
            if ($msg.length > 0) {
                $msg.hide().removeClass('alert alert-success alert-danger').empty();
            }

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                success: function (response) {
                    var title = response.success ? 'Thành công!' : 'Lỗi!';
                    var color = response.success ? '#059669' : '#dc4c04';
                    var message = response.message || (response.success ? 'Đã gửi thành công.' : 'Có lỗi xảy ra, vui lòng thử lại.');

                    $.fancybox.open({
                        src: '<div style="padding: 40px; text-align: center; border-radius: 8px; max-width: 400px; width: 100%;">' +
                            '<h3 style="color: ' + color + '; font-weight: bold; margin-top: 0; font-family: Roboto, sans-serif;">' + title + '</h3>' +
                            '<p style="font-size: 16px; margin-bottom: 0; font-family: Roboto, sans-serif;">' + message + '</p>' +
                            '</div>',
                        type: 'html',
                        smallBtn: true
                    });

                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push({
                        'event': 'form_submit_baogia',
                        'form_location': window.location.pathname
                    });

                    if (response.success) {
                        $form[0].reset();
                    }
                },
                error: function () {
                    $.fancybox.open({
                        src: '<div style="padding: 40px; text-align: center; border-radius: 8px; max-width: 400px; width: 100%;">' +
                            '<h3 style="color: #dc4c04; font-weight: bold; margin-top: 0; font-family: Roboto, sans-serif;">Lỗi kết nối!</h3>' +
                            '<p style="font-size: 16px; margin-bottom: 0; font-family: Roboto, sans-serif;">Lỗi kết nối máy chủ. Vui lòng thử lại.</p>' +
                            '</div>',
                        type: 'html',
                        smallBtn: true
                    });
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text(originalBtnText);
                }
            });
        });
    }
};

module.exports = homepageContact;
