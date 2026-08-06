'use strict';

var $ = require('jquery');

require('bootstrap-sass');

var news = require('./pages/news');
var global = require('./global/global');
var videoPopup = require('./video_popup');
var homepageContact = require('./pages/homepage_contact');

var app = {
    init: function () {
        news.init();
        global.init();
        videoPopup.init();
        homepageContact.init();
    }
};

// initialize app
$(document).ready(function () {
    app.init();

    $('#ka-main-nav').css('overflow', 'visible');
});