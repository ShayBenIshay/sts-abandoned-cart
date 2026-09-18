(function ($) {
    'use strict';

    var SESSION_KEY = 'sts_ac_session_' + window.location.pathname;

    function uuid() {
        return 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/x/g, function () {
            return (Math.random() * 16 | 0).toString(16);
        });
    }

    function getOrCreateToken() {
        var token = localStorage.getItem(SESSION_KEY);
        if (!token || token.length !== 32) {
            token = uuid();
            localStorage.setItem(SESSION_KEY, token);
        }
        return token;
    }

    function capture() {
        var email = $('#billing_email').val() || '';
        var phone = $('#billing_phone').val() || '';

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return;
        }

        $.ajax({
            url: stsAcData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sts_ac_capture',
                nonce: stsAcData.nonce,
                email: email,
                phone: phone,
                session_token: getOrCreateToken()
            }
        });
    }

    var debounceTimer = null;
    function debounce(fn, delay) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fn, delay);
    }

    $(function () {
        // Capture on blur (most reliable) and debounced change.
        $(document.body).on('blur', '#billing_email', function () {
            capture();
        });

        $(document.body).on('change keyup', '#billing_email', function () {
            debounce(capture, 800);
        });

        // Re-capture if phone updates after email was already typed.
        $(document.body).on('blur change', '#billing_phone', function () {
            if ($('#billing_email').val()) {
                capture();
            }
        });
    });

})(jQuery);
