'use strict';

var $ = require('jquery');
require('@fancyapps/fancybox');

var videoPopup = {
    init: function () {
        this.initFancybox();
    },

    initFancybox: function () {
        $('[data-fancybox="gallery-yt"]').fancybox({
            youtube : {
                controls : 0,
                showinfo : 0
            },
            touch: false,
            backFocus: false
        });
    }
};

module.exports = videoPopup;
