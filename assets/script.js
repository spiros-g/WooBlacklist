jQuery(document).ready(function($) {
    $('.order-blacklist-popup .button').click(function(e) {
        e.preventDefault();
        $('.order-blacklist-popup').remove();
    });
});