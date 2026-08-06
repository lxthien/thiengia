'use strict';

require('jquery-validation');

function intHandleFormComment() {
    var $formComment = $('#form-comment');

    $formComment.on('click', '#form_send', function (e) {
        if ($formComment.valid()) {
            $.ajax({
                type: "POST",
                url: $formComment.attr('action'),
                data: $formComment.serialize(),
                success: function (data) {
                    var response = JSON.parse(data);
                    if (response.status === 'success') {
                        $('p#comment-response').html(response.message);

                        window.dataLayer = window.dataLayer || [];
                        window.dataLayer.push({
                            'event': 'form_submit_baogia',
                            'form_location': window.location.pathname
                        });

                        // Clear form comment
                        $formComment[0].reset();
                    } else {
                        $('p#comment-response').html(response.message);
                    }
                }
            });
        }
    })
}

function intHandleFormReplyComment() {
    var $commentReply = $('.comment-reply-link');
    var $formComment = $('#form-comment');

    $commentReply.click(function (e) {
        e.preventDefault();

        var postID = $(this).data('postId');

        if (postID) {
            $formComment.find('input#form_comment_id').val(postID);

            $('html, body').animate({
                scrollTop: $formComment.offset().top
            }, 1000);
        }
    });
}

// Table of Contents - Expand/Collapse functionality
function initTableOfContents() {
    var $toc = $('.ka-table-of-contents');

    if ($toc.length) {
        // Default state: collapsed
        $toc.addClass('collapsed');

        // Click on header to toggle
        $toc.find('#main-toc').on('click', function () {
            $toc.toggleClass('collapsed');
        });
    }
}

exports.init = function () {

    $('#form-comment').validate();

    intHandleFormComment();
    intHandleFormReplyComment();
    initTableOfContents();
};