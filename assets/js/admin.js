(function ($) {
    'use strict';

    // Resend button.
    $(document.body).on('click', '.sts-ac-resend-btn', function () {
        var btn = $(this);
        var cartId = btn.data('cart-id');

        if (!confirm('לשלוח תזכורת מחדש ללקוח זה?')) {
            return;
        }

        btn.prop('disabled', true).text('שולח…');

        $.ajax({
            url: stsAcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sts_ac_resend',
                nonce: stsAcAdmin.nonce,
                cart_id: cartId
            },
            success: function (res) {
                if (res.success) {
                    btn.text('נשלח!').addClass('sts-ac-btn--success');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    alert('שגיאה: ' + (res.data && res.data.message ? res.data.message : 'שגיאה לא ידועה'));
                    btn.prop('disabled', false).text('שלח שוב');
                }
            },
            error: function () {
                alert('שגיאת רשת, אנא נסה שוב');
                btn.prop('disabled', false).text('שלח שוב');
            }
        });
    });

    // Test-send button.
    $('#sts-ac-test-send-btn').on('click', function () {
        var btn = $(this);
        var result = $('#sts-ac-test-send-result');

        btn.prop('disabled', true).text('שולח…');
        result.text('');

        $.ajax({
            url: stsAcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sts_ac_test_send',
                nonce: stsAcAdmin.nonce
            },
            success: function (res) {
                btn.prop('disabled', false).text('שלח אימייל בדיקה לכתובת המנהל');
                if (res.success) {
                    result.css('color', 'green').text(res.data.message);
                } else {
                    result.css('color', 'red').text(res.data && res.data.message ? res.data.message : 'שגיאה');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('שלח אימייל בדיקה לכתובת המנהל');
                result.css('color', 'red').text('שגיאת רשת');
            }
        });
    });

    // Compliance prefix button.
    $('#sts-ac-compliance-btn').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: stsAcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sts_ac_set_compliance_prefix',
                nonce: stsAcAdmin.nonce
            },
            success: function (res) {
                if (res.success) {
                    $('#sts_ac_subject_prefix').val(res.data.prefix);
                    btn.text('בוצע — שמור את הטופס כדי לשמור');
                }
                btn.prop('disabled', false);
            },
            error: function () {
                btn.prop('disabled', false);
            }
        });
    });

})(jQuery);
