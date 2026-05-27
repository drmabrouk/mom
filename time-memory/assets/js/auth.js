jQuery(document).ready(function($) {
    // Check auth status on load
    $.post(tm_ajax_obj.ajax_url, { action: 'tm_check_auth', security: tm_ajax_obj.nonce }, function(res) {
        if (res.success && res.data.authenticated) {
            $(document).trigger('tm_auth_success');
        } else {
            showLogin();
        }
    });

    function showLogin() {
        $('body').html(`
            <div class="tm-modal-overlay" style="display:flex;">
                <div class="tm-modal-box">
                    <h2 style="font-size: 40px; font-weight:900; margin-bottom: 40px;">بوابة الذاكرة العميقة</h2>
                    <form id="tm-login-form">
                        <input type="text" id="tm_user" placeholder="المعرف" required style="margin-bottom:20px;">
                        <input type="password" id="tm_pass" placeholder="كلمة المرور" required style="margin-bottom:30px;">
                        <button type="submit" class="tm-btn-main">فتح التشفير 🔓</button>
                    </form>
                </div>
            </div>
        `);
    }

    $(document).on('submit', '#tm-login-form', function(e) {
        e.preventDefault();
        $.post(tm_ajax_obj.ajax_url, {
            action: 'tm_login',
            security: tm_ajax_obj.nonce,
            username: $('#tm_user').val(),
            password: $('#tm_pass').val()
        }, function(res) {
            if (res.success) location.reload();
            else alert('المعرف غير مصرح له.');
        });
    });
});
