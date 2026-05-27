jQuery(document).ready(function($) {
    let inactivityTimer;
    const INACTIVITY_LIMIT = 15 * 60 * 1000; // 15 minutes

    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(logout, INACTIVITY_LIMIT);
    }

    function logout() {
        $.post(tm_ajax_obj.ajax_url, { action: 'tm_logout', security: tm_ajax_obj.nonce }, function() {
            location.reload();
        });
    }

    // Monitor events for activity
    $(document).on('mousemove keypress click scroll touchstart', resetInactivityTimer);
    resetInactivityTimer();

    // Login Form Submission
    $(document).on('submit', '#tm-login-form', function(e) {
        e.preventDefault();
        const data = {
            action: 'tm_login',
            security: tm_ajax_obj.nonce,
            username: $('#tm_username').val(),
            password: $('#tm_password').val()
        };
        $.post(tm_ajax_obj.ajax_url, data, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    // Logout Button
    $(document).on('click', '#tm-logout-btn', function(e) {
        e.preventDefault();
        logout();
    });

    // Check auth status on load
    $.post(tm_ajax_obj.ajax_url, { action: 'tm_check_auth', security: tm_ajax_obj.nonce }, function(response) {
        if (response.success) {
            if (response.data.authenticated) {
                $('#tm-auth-status').html('<button id="tm-logout-btn" class="tm-btn">تسجيل الخروج</button>');
                // Trigger UI load
                $(document).trigger('tm_auth_success');
            } else {
                showLoginForm();
            }
        }
    });

    function showLoginForm() {
        $('#tm-content').html(`
            <div class="tm-login-box" style="max-width:400px; margin: 50px auto; text-align:center;">
                <h2>بوابة الدخول السري</h2>
                <form id="tm-login-form">
                    <input type="text" id="tm_username" placeholder="اسم المستخدم" required autocomplete="off">
                    <input type="password" id="tm_password" placeholder="كلمة المرور" required autocomplete="off">
                    <button type="submit" class="tm-btn">تأكيد الدخول</button>
                </form>
            </div>
        `);
    }
});
