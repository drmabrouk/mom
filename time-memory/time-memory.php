<?php
/*
Plugin Name: ذاكرة الزمن
Plugin URI: 
Description: إضافة إحترافية بمثابة ذاكرة رقمية لحفظ المواقف، الشخصيات، كلمات السر، والبيانات الهامة بتباين بصري عالي (أبيض وأسود).
Version: 1.0
Author: الذكاء الاصطناعي (Gemini)
*/

// منع الوصول المباشر
if (!defined('ABSPATH')) exit;

// 1. إنشاء جدول قاعدة البيانات عند تفعيل الإضافة
register_activation_hook(__FILE__, 'tm_create_database_table');
function tm_create_database_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category varchar(100) NOT NULL,
        record_title varchar(255) NOT NULL,
        record_details text NOT NULL,
        record_date date NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 2. إضافة القائمة في لوحة تحكم ووردبريس
add_action('admin_menu', 'tm_setup_menu');
function tm_setup_menu() {
    add_menu_page('ذاكرة الزمن', 'ذاكرة الزمن', 'manage_options', 'time-memory', 'tm_admin_page_content', 'dashicons-book', 6);
}

// 3. إدارة تسجيل الدخول (الكوكيز)
add_action('admin_init', 'tm_handle_login');
function tm_handle_login() {
    if (isset($_POST['tm_login_submit'])) {
        $username = sanitize_text_field($_POST['tm_username']);
        $password = sanitize_text_field($_POST['tm_password']);
        
        if ($username === 'ahmed' && $password === '10111996') {
            // تعيين كوكي لمدة 24 ساعة
            setcookie('tm_authenticated', 'yes', time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        } else {
            add_settings_error('tm_messages', 'tm_error', 'بيانات الدخول غير صحيحة.', 'error');
        }
    }

    if (isset($_GET['tm_logout'])) {
        setcookie('tm_authenticated', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        wp_redirect(remove_query_arg('tm_logout'));
        exit;
    }
}

// 4. معالجة إضافة البيانات وحذفها
add_action('admin_init', 'tm_handle_records');
function tm_handle_records() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';

    // إضافة سجل جديد
    if (isset($_POST['tm_add_record']) && isset($_COOKIE['tm_authenticated'])) {
        $wpdb->insert(
            $table_name,
            array(
                'category' => sanitize_text_field($_POST['tm_category']),
                'record_title' => sanitize_text_field($_POST['tm_title']),
                'record_details' => sanitize_textarea_field($_POST['tm_details']),
                'record_date' => sanitize_text_field($_POST['tm_date'])
            )
        );
        add_settings_error('tm_messages', 'tm_success', 'تم حفظ السجل بنجاح.', 'updated');
    }

    // حذف سجل
    if (isset($_GET['delete_record']) && isset($_COOKIE['tm_authenticated'])) {
        $wpdb->delete($table_name, array('id' => intval($_GET['delete_record'])));
        add_settings_error('tm_messages', 'tm_success', 'تم حذف السجل.', 'updated');
    }
}

// 5. محتوى صفحة الإضافة
function tm_admin_page_content() {
    // عرض الإشعارات
    settings_errors('tm_messages');

    // ستايل أبيض وأسود إحترافي
    echo '<style>
        .tm-wrap { font-family: Tahoma, sans-serif; max-width: 1000px; margin: 20px auto; background: #fff; border: 3px solid #000; padding: 30px; box-shadow: 5px 5px 0px #000; color: #000; direction: rtl; }
        .tm-wrap h1, .tm-wrap h2, .tm-wrap h3 { color: #000; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .tm-form-group { margin-bottom: 15px; }
        .tm-form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .tm-form-group input, .tm-form-group select, .tm-form-group textarea { width: 100%; padding: 10px; border: 2px solid #000; background: #fff; color: #000; font-size: 16px; }
        .tm-btn { background: #000; color: #fff; padding: 10px 20px; border: none; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; }
        .tm-btn:hover { background: #fff; color: #000; border: 2px solid #000; }
        .tm-btn-danger { background: #fff; color: #000; border: 2px solid #000; font-weight:bold; }
        .tm-btn-danger:hover { background: #000; color: #fff; }
        .tm-nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tm-nav a { border: 2px solid #000; padding: 10px; text-decoration: none; color: #000; font-weight: bold; }
        .tm-nav a.active, .tm-nav a:hover { background: #000; color: #fff; }
        .tm-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tm-table th, .tm-table td { border: 2px solid #000; padding: 12px; text-align: right; }
        .tm-table th { background: #000; color: #fff; }
        .tm-login-box { max-width: 400px; margin: 100px auto; text-align: center; border: 3px solid #000; padding: 30px; background: #fff; box-shadow: 5px 5px 0px #000; }
    </style>';

    // التحقق من تسجيل الدخول
    if (!isset($_COOKIE['tm_authenticated']) || $_COOKIE['tm_authenticated'] !== 'yes') {
        echo '<div class="tm-login-box">
                <h2>بوابة الدخول السري</h2>
                <form method="post">
                    <div class="tm-form-group">
                        <input type="text" name="tm_username" placeholder="اسم المستخدم" required autocomplete="off">
                    </div>
                    <div class="tm-form-group">
                        <input type="password" name="tm_password" placeholder="كلمة المرور" required autocomplete="off">
                    </div>
                    <button type="submit" name="tm_login_submit" class="tm-btn">تأكيد الدخول</button>
                </form>
              </div>';
        return;
    }

    // الأقسام المتوفرة
    $categories = [
        'situations' => 'المواقف والأحداث',
        'characters' => 'الشخصيات والتعارف',
        'passwords' => 'كلمات السر',
        'emails' => 'البريد الإلكتروني',
        'usernames' => 'أسماء المستخدمين',
        'phones' => 'سجلات الهاتف',
        'calculations' => 'حسابات وتفاصيل',
        'others' => 'أخرى'
    ];

    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'situations';

    echo '<div class="tm-wrap">';
    
    // الترويسة وتسجيل الخروج
    echo '<div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>ذاكرة الزمن</h1>
            <a href="?page=time-memory&tm_logout=1" class="tm-btn tm-btn-danger">تسجيل الخروج</a>
          </div>';

    // شريط التنقل للأقسام
    echo '<div class="tm-nav">';
    foreach ($categories as $slug => $name) {
        $active = ($current_tab === $slug) ? 'active' : '';
        echo '<a href="?page=time-memory&tab=' . $slug . '" class="' . $active . '">' . $name . '</a>';
    }
    echo '</div>';

    // نموذج إضافة سجل جديد
    echo '<h3>إضافة سجل جديد في قسم: ' . $categories[$current_tab] . '</h3>
          <form method="post" style="border: 2px dashed #000; padding: 20px; background: #fafafa;">
              <input type="hidden" name="tm_category" value="' . $current_tab . '">
              
              <div class="tm-form-group">
                  <label>العنوان / اسم الشخص / الحساب:</label>
                  <input type="text" name="tm_title" required>
              </div>
              
              <div class="tm-form-group">
                  <label>التاريخ المرتبط بالحدث:</label>
                  <input type="date" name="tm_date" value="' . date('Y-m-d') . '" required>
              </div>
              
              <div class="tm-form-group">
                  <label>التفاصيل / الموقف / كلمة السر:</label>
                  <textarea name="tm_details" rows="5" required></textarea>
              </div>
              
              <button type="submit" name="tm_add_record" class="tm-btn">حفظ في الذاكرة</button>
          </form>';

    // عرض السجلات السابقة للقسم المختار
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';
    $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category = %s ORDER BY record_date DESC", $current_tab));

    echo '<h3>السجلات المحفوظة</h3>';
    if ($records) {
        echo '<table class="tm-table">
                <thead>
                    <tr>
                        <th style="width:20%">التاريخ</th>
                        <th style="width:25%">العنوان</th>
                        <th style="width:45%">التفاصيل</th>
                        <th style="width:10%">إجراء</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($records as $record) {
            echo '<tr>
                    <td>' . esc_html($record->record_date) . '</td>
                    <td><strong>' . esc_html($record->record_title) . '</strong></td>
                    <td>' . nl2br(esc_html($record->record_details)) . '</td>
                    <td>
                        <a href="?page=time-memory&tab=' . $current_tab . '&delete_record=' . $record->id . '" class="tm-btn tm-btn-danger" style="padding:5px 10px; font-size:12px;" onclick="return confirm(\'هل أنت متأكد من حذف هذا السجل نهائياً؟\');">حذف</a>
                    </td>
                  </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="text-align:center; padding: 20px; border: 2px solid #000;">لا توجد سجلات محفوظة في هذا القسم حتى الآن.</p>';
    }

    echo '</div>'; // نهاية الحاوية
}
?>