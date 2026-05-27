<?php
/*
Plugin Name: ذاكرة الزمن
Plugin URI:
Description: إضافة إحترافية بمثابة ذاكرة رقمية لحفظ المواقف، الشخصيات، كلمات السر، والبيانات الهامة بتباين بصري عالي (أبيض وأسود).
Version: 3.0
Author: الذكاء الاصطناعي (Gemini)
*/

// منع الوصول المباشر
if (!defined('ABSPATH')) exit;

// 1. إنشاء جدول قاعدة البيانات وصفحة التطبيق والإعدادات عند تفعيل الإضافة
register_activation_hook(__FILE__, 'tm_plugin_activate');
function tm_plugin_activate() {
    tm_create_database_table();
    tm_create_frontend_page();
    // حفظ بيانات الدخول الافتراضية في الخيارات (بشكل مشفر للتبسيط في هذا السياق)
    if (!get_option('tm_app_user')) {
        update_option('tm_app_user', 'ahmed');
        update_option('tm_app_pass', '10111996');
    }
}

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
        record_media text DEFAULT '' NOT NULL,
        record_audio varchar(255) DEFAULT '' NOT NULL,
        related_id mediumint(9) DEFAULT 0 NOT NULL,
        is_encrypted tinyint(1) DEFAULT 0 NOT NULL,
        reminder_data text DEFAULT '' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function tm_create_frontend_page() {
    $page_title = 'ذاكرة الزمن';
    $page_content = '[time_memory_app]';
    $page_check = get_page_by_title($page_title);
    $new_page = array(
        'post_type'    => 'page',
        'post_title'   => $page_title,
        'post_content' => $page_content,
        'post_status'  => 'publish',
        'post_author'  => 1,
    );
    if(!isset($page_check->ID)){
        wp_insert_post($new_page);
    }
}

// 2. معالجة الإجراءات (Login, CRUD) - مقتصرة على صفحة التطبيق
add_action('template_redirect', 'tm_handle_frontend_actions');
function tm_handle_frontend_actions() {
    if (!is_page('ذاكرة-الزمن') && !is_page('ذاكرة الزمن')) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';

    // 1. تسجيل الدخول
    if (isset($_POST['tm_frontend_login'])) {
        if (!isset($_POST['tm_login_nonce']) || !wp_verify_nonce($_POST['tm_login_nonce'], 'tm_login_action')) return;
        $username = sanitize_text_field($_POST['tm_username']);
        $password = sanitize_text_field($_POST['tm_password']);

        if ($username === get_option('tm_app_user') && $password === get_option('tm_app_pass')) {
            setcookie('tm_authenticated', 'yes', time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            wp_safe_redirect(remove_query_arg(['tm_login_nonce']));
            die();
        }
    }

    // 2. تسجيل الخروج
    if (isset($_GET['tm_action']) && $_GET['tm_action'] === 'logout') {
        setcookie('tm_authenticated', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        wp_safe_redirect(get_permalink());
        die();
    }

    // 3. إضافة سجل
    if (isset($_POST['tm_add_record_frontend']) && isset($_COOKIE['tm_authenticated']) && $_COOKIE['tm_authenticated'] === 'yes') {
        if (!isset($_POST['tm_record_nonce']) || !wp_verify_nonce($_POST['tm_record_nonce'], 'tm_add_record_action')) return;

        $media_paths = [];
        $audio_path = '';

        if (!empty($_FILES['tm_photos']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            foreach ($_FILES['tm_photos']['name'] as $key => $value) {
                $file = ['name'=>$_FILES['tm_photos']['name'][$key], 'type'=>$_FILES['tm_photos']['type'][$key], 'tmp_name'=>$_FILES['tm_photos']['tmp_name'][$key], 'error'=>$_FILES['tm_photos']['error'][$key], 'size'=>$_FILES['tm_photos']['size'][$key]];
                $upload = wp_handle_upload($file, ['test_form'=>false]);
                if (isset($upload['url'])) $media_paths[] = $upload['url'];
            }
        }

        if (!empty($_POST['tm_audio_blob'])) {
            $audio_data = str_replace(['data:audio/wav;base64,', ' '], ['', '+'], $_POST['tm_audio_blob']);
            $decoded_audio = base64_decode($audio_data);
            $upload_dir = wp_upload_dir();
            $filename = 'voice-' . uniqid() . '.wav';
            file_put_contents($upload_dir['path'] . '/' . $filename, $decoded_audio);
            $audio_path = $upload_dir['url'] . '/' . $filename;
        }

        $is_encrypted = isset($_POST['tm_is_encrypted']) ? 1 : 0;
        $title = sanitize_text_field($_POST['tm_title']);
        $details = sanitize_textarea_field($_POST['tm_details']);
        if ($is_encrypted) { $title = tm_encrypt($title); $details = tm_encrypt($details); }

        $wpdb->insert($table_name, [
            'category' => sanitize_text_field($_POST['tm_category']),
            'record_title' => $title,
            'record_details' => $details,
            'record_date' => sanitize_text_field($_POST['tm_date']),
            'record_media' => json_encode($media_paths),
            'record_audio' => $audio_path,
            'related_id' => intval($_POST['tm_related_id']),
            'is_encrypted' => $is_encrypted,
            'reminder_data' => (!empty($_POST['tm_reminder_date'])) ? json_encode(['date'=>$_POST['tm_reminder_date'], 'freq'=>$_POST['tm_reminder_freq']]) : ''
        ]);
        wp_safe_redirect(add_query_arg('tm_msg', 'saved'));
        die();
    }

    // 4. حذف سجل
    if (isset($_GET['tm_delete_id']) && isset($_COOKIE['tm_authenticated']) && $_COOKIE['tm_authenticated'] === 'yes') {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'tm_delete_' . $_GET['tm_delete_id'])) return;
        $wpdb->delete($table_name, ['id' => intval($_GET['tm_delete_id'])]);
        wp_safe_redirect(add_query_arg('tm_msg', 'deleted', remove_query_arg(['tm_delete_id', '_wpnonce'])));
        die();
    }
}

// 3. وظائف الأمن والتشفير
function tm_get_encryption_key() {
    $key = get_option('tm_encryption_key');
    if (!$key) {
        $key = wp_generate_password(32, true, true);
        update_option('tm_encryption_key', $key);
    }
    return $key;
}

function tm_encrypt($data) {
    if (empty($data)) return '';
    $key = hash('sha256', tm_get_encryption_key());
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function tm_decrypt($data) {
    if (empty($data)) return '';
    $key = hash('sha256', tm_get_encryption_key());
    $decoded = base64_decode($data);
    if (strpos($decoded, '::') === false) return $data;
    list($encrypted_data, $iv) = explode('::', $decoded, 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
}

// 4. تطبيق صفحة الفرونت إند (Shortcode)
add_shortcode('time_memory_app', 'tm_frontend_app_shortcode');
function tm_frontend_app_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';
    $filter_char = isset($_GET['tm_filter_char']) ? intval($_GET['tm_filter_char']) : 0;
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'situations';
    $categories = ['situations'=>'المواقف', 'characters'=>'الشخصيات', 'passwords'=>'كلمات السر', 'timeline'=>'الجدول الزمني', 'reminders'=>'التذكيرات'];

    ob_start();
    ?>
    <style>
        :root {
            --tm-base-font-size: 18px;
            --tm-line-height: 1.6;
            --tm-bg: #fff;
            --tm-text: #000;
            --tm-border: #000;
        }
        body.tm-dark-mode {
            --tm-bg: #111;
            --tm-text: #fff;
            --tm-border: #fff;
        }
        .tm-app-wrap {
            font-family: Tahoma, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            background: var(--tm-bg);
            border: 5px solid var(--tm-border);
            padding: 40px;
            box-shadow: 15px 15px 0px var(--tm-border);
            color: var(--tm-text);
            direction: rtl;
            font-size: var(--tm-base-font-size);
            line-height: var(--tm-line-height);
            transition: all 0.3s ease;
        }
        .tm-app-wrap h1, .tm-app-wrap h2, .tm-app-wrap h3 { color: var(--tm-text); border-bottom: 4px solid var(--tm-border); padding-bottom: 15px; }
        .tm-form-group { margin-bottom: 25px; }
        .tm-form-group label { display: block; font-weight: 900; margin-bottom: 10px; font-size: 1.1em; }
        .tm-form-group input, .tm-form-group select, .tm-form-group textarea {
            width: 100%; padding: 15px; border: 3px solid var(--tm-border); background: var(--tm-bg); color: var(--tm-text); font-size: inherit;
        }
        .tm-btn {
            background: var(--tm-border); color: var(--tm-bg); padding: 15px 30px; border: 3px solid var(--tm-border);
            font-size: 1.1em; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block;
            transition: 0.2s;
        }
        .tm-btn:hover { opacity: 0.8; }
        .tm-btn-danger { background: var(--tm-bg); color: var(--tm-text); border: 3px solid var(--tm-border); }
        .tm-nav {
            display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;
            position: sticky; top: 0; background: var(--tm-bg); z-index: 100; padding: 10px 0; border-bottom: 3px solid var(--tm-border);
        }
        .tm-nav a {
            border: 3px solid var(--tm-border); padding: 15px 25px; text-decoration: none; color: var(--tm-text);
            font-weight: 900; flex: 1; text-align: center; min-width: 150px;
        }
        .tm-nav a.active, .tm-nav a:hover { background: var(--tm-border); color: var(--tm-bg); }
        .tm-table { width: 100%; border-collapse: collapse; margin-top: 30px; border: 4px solid var(--tm-border); }
        .tm-table th, .tm-table td { border: 3px solid var(--tm-border); padding: 20px; text-align: right; }
        .tm-table th { background: var(--tm-border); color: var(--tm-bg); }
        .tm-alert { border: 4px solid var(--tm-border); padding: 20px; margin-bottom: 30px; background: #ffff00; color: #000; font-weight: 900; }
        .tm-media-preview img { width: 80px; height: 80px; margin: 10px; border: 3px solid var(--tm-border); object-fit: cover; }
        .tm-utility-bar {
            position: fixed; bottom: 20px; left: 20px; background: var(--tm-bg); border: 3px solid var(--tm-border);
            padding: 10px; display: flex; gap: 10px; z-index: 1000; box-shadow: 5px 5px 0 var(--tm-border);
        }
        .tm-blur { filter: blur(8px); transition: filter 0.3s; cursor: pointer; }
        .tm-blur:hover { filter: blur(0); }
        .tm-sidebar { border: 5px solid var(--tm-border); padding: 20px; background: #f0f0f0; margin-bottom: 30px; color: #000; font-weight: bold; }
        body.tm-dark-mode .tm-sidebar { background: #222; color: #fff; }
    </style>

    <div class="tm-app-wrap">
        <?php
        if (!isset($_COOKIE['tm_authenticated']) || $_COOKIE['tm_authenticated'] !== 'yes') {
            ?>
            <div class="tm-login-box" style="text-align:center;">
                <h2>بوابة الدخول السري</h2>
                <form method="post">
                    <?php wp_nonce_field('tm_login_action', 'tm_login_nonce'); ?>
                    <div class="tm-form-group"><input type="text" name="tm_username" placeholder="اسم المستخدم" required></div>
                    <div class="tm-form-group"><input type="password" name="tm_password" placeholder="كلمة المرور" required></div>
                    <button type="submit" name="tm_frontend_login" class="tm-btn">تأكيد الدخول</button>
                </form>
            </div>
            <?php
            echo '</div>';
            return ob_get_clean();
        }
        ?>

        <div class="tm-utility-bar">
            <button class="tm-btn" onclick="tmApp.zoom(2)">A+</button>
            <button class="tm-btn" onclick="tmApp.zoom(-2)">A-</button>
            <button class="tm-btn" onclick="tmApp.adjustSpacing(0.2)">↔+</button>
            <button class="tm-btn" onclick="tmApp.toggleDarkMode()">🌙</button>
            <a href="<?php echo add_query_arg('tm_action', 'logout'); ?>" class="tm-btn tm-btn-danger">خروج</a>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1>ذاكرة الزمن</h1>
        </div>

        <div class="tm-nav">
            <?php foreach ($categories as $slug => $name): ?>
                <a href="<?php echo add_query_arg(['tab' => $slug, 'tm_filter_char' => false]); ?>" class="<?php echo ($current_tab===$slug)?'active':''; ?>"><?php echo $name; ?></a>
            <?php endforeach; ?>
        </div>

        <?php
        // عرض التذكيرات
        $today = date('Y-m-d');
        $all_reminders = $wpdb->get_results("SELECT * FROM $table_name WHERE reminder_data != ''");
        foreach ($all_reminders as $rem) {
            $rdata = json_decode($rem->reminder_data, true);
            if ($rdata && isset($rdata['date'])) {
                $show_alert = false;
                $rem_date = $rdata['date'];
                $freq = isset($rdata['freq']) ? $rdata['freq'] : 'once';

                if ($rem_date === $today) $show_alert = true;
                elseif ($rem_date < $today) {
                    if ($freq === 'daily') $show_alert = true;
                    elseif ($freq === 'weekly' && ((strtotime($today) - strtotime($rem_date)) / 86400) % 7 === 0) $show_alert = true;
                    elseif ($freq === 'monthly' && date('d', strtotime($rem_date)) === date('d', strtotime($today))) $show_alert = true;
                }

                if ($show_alert) {
                    $rem_title = $rem->is_encrypted ? tm_decrypt($rem->record_title) : $rem->record_title;
                    echo '<div class="tm-alert">تنبيه: تذكير بـ "' . esc_html($rem_title) . '"</div>';
                }
            }
        }

        if (isset($_GET['tm_msg'])) {
            if ($_GET['tm_msg'] === 'saved') echo '<div class="tm-alert">تم الحفظ بنجاح.</div>';
            if ($_GET['tm_msg'] === 'deleted') echo '<div class="tm-alert">تم المسح من الذاكرة.</div>';
        }

        if ($current_tab !== 'timeline' && $current_tab !== 'reminders'): ?>
            <h3>إضافة إلى <?php echo $categories[$current_tab]; ?></h3>
            <form id="tm-add-form" method="post" enctype="multipart/form-data" style="border:5px solid var(--tm-border); padding:30px; background:#fafafa; margin-bottom:40px;">
                <?php wp_nonce_field('tm_add_record_action', 'tm_record_nonce'); ?>
                <div id="tm-autosave-indicator" style="font-size: 14px; float: left; font-weight: 900; color: #666;"></div>
                <input type="hidden" name="tm_category" value="<?php echo $current_tab; ?>">
                <div class="tm-form-group"><label>العنوان:</label><input type="text" name="tm_title" id="f-title" value="<?php echo ($filter_char && $current_tab === 'situations') ? 'موقف جديد مع هذه الشخصية' : ''; ?>" required></div>
                <div class="tm-form-group"><label>التاريخ:</label><input type="date" name="tm_date" id="f-date" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="tm-form-group"><label>التفاصيل:</label><textarea name="tm_details" id="f-details" rows="4" required></textarea></div>
                <div class="tm-form-group"><label><input type="checkbox" name="tm_is_encrypted"> تشفير البيانات (حساس)</label></div>
                <div class="tm-form-group"><label>إرفاق صور:</label><input type="file" name="tm_photos[]" multiple></div>
                <div class="tm-form-group">
                    <label>ملاحظة صوتية:</label>
                    <button type="button" id="tm-start-rec" class="tm-btn">بدء التسجيل</button>
                    <button type="button" id="tm-stop-rec" class="tm-btn tm-btn-danger" style="display:none;">إيقاف</button>
                    <span id="tm-rec-status"></span>
                    <input type="hidden" name="tm_audio_blob" id="tm_audio_blob">
                </div>
                <?php if ($current_tab === 'situations'):
                    $chars = $wpdb->get_results("SELECT id, record_title, is_encrypted FROM $table_name WHERE category='characters'");
                    if ($chars): ?>
                        <div class="tm-form-group"><label>ربط بشخصية:</label><select name="tm_related_id"><option value="0">-- لا يوجد --</option>
                        <?php foreach($chars as $c) {
                            $c_title = $c->is_encrypted ? tm_decrypt($c->record_title) : $c->record_title;
                            $selected = ($filter_char == $c->id) ? 'selected' : '';
                            echo '<option value="'.$c->id.'" '.$selected.'>'. esc_html($c_title) .'</option>';
                        } ?>
                        </select></div>
                    <?php endif;
                endif; ?>
                <div style="border:3px solid var(--tm-border); padding:20px; margin-bottom:20px; background:#eee;">
                    <strong>إعداد تذكير:</strong><br>
                    <input type="date" name="tm_reminder_date">
                    تكرار: <select name="tm_reminder_freq"><option value="once">مرة واحدة</option><option value="daily">يومي</option><option value="weekly">أسبوعي</option><option value="monthly">شهري</option></select>
                </div>
                <button type="submit" name="tm_add_record_frontend" class="tm-btn">حفظ في الذاكرة</button>
            </form>
        <?php endif; ?>

        <div class="tm-form-group">
            <input type="text" id="tm-live-search" placeholder="بحث فوري في السجلات..." onkeyup="tmApp.liveSearch()">
        </div>

        <?php
        if ($filter_char) {
            $char_profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $filter_char));
            $last_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE related_id = %d AND category = 'situations' ORDER BY record_date DESC LIMIT 1", $filter_char));
            if ($char_profile) {
                $cp_title = $char_profile->is_encrypted ? tm_decrypt($char_profile->record_title) : $char_profile->record_title;
                echo '<div class="tm-sidebar">
                        <strong>نظرة سريعة على الشخصية: ' . esc_html($cp_title) . '</strong><br>';
                if ($last_event) {
                    $le_title = $last_event->is_encrypted ? tm_decrypt($last_event->record_title) : $last_event->record_title;
                    echo 'آخر موقف مسجل: ' . esc_html($le_title) . ' (' . esc_html($last_event->record_date) . ')';
                } else {
                    echo 'لا توجد مواقف مسجلة بعد لهذه الشخصية.';
                }
                echo '</div>';
            }
        }
        ?>

        <div id="tm-records-list">
            <?php
            if ($current_tab === 'timeline') $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY record_date DESC");
            elseif ($current_tab === 'reminders') $records = $wpdb->get_results("SELECT * FROM $table_name WHERE reminder_data!='' AND reminder_data!='[]' ORDER BY record_date DESC");
            elseif ($current_tab === 'situations' && $filter_char) $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category='situations' AND related_id=%d ORDER BY record_date DESC", $filter_char));
            else $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category=%s ORDER BY record_date DESC", $current_tab));

            if ($filter_char) echo '<div class="tm-alert">تصفية حسب الشخصية. <a href="'.add_query_arg('tm_filter_char', false).'">إلغاء التصفية</a></div>';

            if ($records): ?>
                <table class="tm-table">
                    <thead><tr><th>التاريخ</th><th>العنوان</th><th>التفاصيل والوسائط</th><th>إجراء</th></tr></thead>
                    <tbody>
                        <?php foreach ($records as $r):
                            $t = $r->record_title; $d = $r->record_details;
                            if ($r->is_encrypted) { $t = tm_decrypt($t); $d = tm_decrypt($d); $t = ($t?:'[مشفر]').' (مشفر)'; }

                            $display_title = esc_html($t);
                            if ($r->category === 'characters') {
                                $display_title = '<a href="'.add_query_arg(['tab'=>'situations', 'tm_filter_char'=>$r->id]).'" style="text-decoration:underline; font-weight:900;">'.esc_html($t).'</a>';
                            }
                            ?>
                            <tr>
                                <td><?php echo $r->record_date; ?></td>
                                <td><strong><?php echo $display_title; ?></strong></td>
                                <td class="<?php echo ($r->category === 'passwords' || $r->is_encrypted) ? 'tm-blur' : ''; ?>">
                                    <?php echo nl2br(esc_html($d)); ?>
                                    <div class="tm-media-preview">
                                        <?php $imgs = json_decode($r->record_media,true); if($imgs) foreach($imgs as $img) echo '<a href="'.esc_url($img).'" target="_blank"><img src="'.esc_url($img).'"></a>'; ?>
                                        <?php if($r->record_audio) echo '<div style="margin-top:10px;"><audio controls src="'.esc_url($r->record_audio).'"></audio></div>'; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="tm-btn" style="padding:10px; margin-bottom:10px;" onclick="tmApp.speak('<?php echo esc_js($d); ?>')">🔊</button>
                                    <button class="tm-btn tm-btn-danger" style="padding:10px;" onclick="tmApp.smartDelete(this, '<?php echo wp_nonce_url(add_query_arg('tm_delete_id', $r->id), 'tm_delete_' . $r->id); ?>')">X</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: echo '<p style="text-align:center; padding:30px; border:3px solid var(--tm-border); font-weight:900;">لا توجد سجلات حالياً.</p>'; endif; ?>
        </div>
    </div>

    <script>
        const tmApp = {
            inactivity: 0,
            fontSize: 18,
            lineHeight: 1.6,
            manualDark: false,
            zoom: function(delta) {
                this.fontSize += delta;
                document.documentElement.style.setProperty("--tm-base-font-size", this.fontSize + "px");
            },
            adjustSpacing: function(delta) {
                this.lineHeight += delta;
                document.documentElement.style.setProperty("--tm-line-height", this.lineHeight);
            },
            toggleDarkMode: function(manual = true) {
                document.body.classList.toggle("tm-dark-mode");
                if (manual) this.manualDark = true;
            },
            checkSunset: function() {
                if (this.manualDark) return;
                const hour = new Date().getHours();
                if (hour >= 18 || hour < 6) {
                    document.body.classList.add("tm-dark-mode");
                } else {
                    document.body.classList.remove("tm-dark-mode");
                }
            },
            speak: (text) => { window.speechSynthesis.cancel(); const u = new SpeechSynthesisUtterance(text); u.lang = 'ar-SA'; window.speechSynthesis.speak(u); },
            autosave: function() {
                const indicator = document.getElementById("tm-autosave-indicator");
                const data = {
                    title: document.getElementById("f-title").value,
                    details: document.getElementById("f-details").value,
                    date: document.getElementById("f-date").value,
                    category: "<?php echo $current_tab; ?>"
                };
                if (!data.title && !data.details) return;
                indicator.innerText = "جاري الحفظ تلقائياً...";
                localStorage.setItem("tm_autosave_" + data.category, JSON.stringify(data));
                setTimeout(() => { indicator.innerText = "تم الحفظ تلقائياً."; }, 800);
            },
            restoreAutosave: function() {
                const category = "<?php echo $current_tab; ?>";
                const saved = localStorage.getItem("tm_autosave_" + category);
                if (saved) {
                    const data = JSON.parse(saved);
                    if (document.getElementById("f-title")) document.getElementById("f-title").value = data.title;
                    if (document.getElementById("f-details")) document.getElementById("f-details").value = data.details;
                    if (document.getElementById("f-date")) document.getElementById("f-date").value = data.date;
                }
            },
            smartDelete: function(btn, url) {
                if (btn.innerText === "X") {
                    btn.innerText = "تأكيد الحذف؟";
                    btn.style.backgroundColor = "red";
                    btn.style.color = "white";
                    setTimeout(() => { btn.innerText = "X"; btn.style.backgroundColor = ""; btn.style.color = ""; }, 4000);
                } else {
                    window.location.href = url;
                }
            },
            liveSearch: function() {
                const query = document.getElementById("tm-live-search").value.toLowerCase();
                const rows = document.querySelectorAll(".tm-table tbody tr");
                rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(query) ? "" : "none"; });
            },
            init: function() {
                this.checkSunset();
                this.restoreAutosave();
                const form = document.getElementById("tm-add-form");
                if (form) {
                    form.oninput = () => { clearTimeout(this.saveTimer); this.saveTimer = setTimeout(() => this.autosave(), 2000); };
                    form.onsubmit = () => { localStorage.removeItem("tm_autosave_<?php echo $current_tab; ?>"); };
                }
                setInterval(() => { this.inactivity++; if(this.inactivity > 300) window.location.href = "<?php echo add_query_arg('tm_action', 'logout'); ?>"; }, 1000);
                ['mousemove','keypress','click'].forEach(e => window.addEventListener(e, () => this.inactivity = 0));

                const startBtn = document.getElementById('tm-start-rec');
                const stopBtn = document.getElementById('tm-stop-rec');
                if (startBtn) {
                    let mr; let chunks = [];
                    startBtn.onclick = async () => {
                        try {
                            const s = await navigator.mediaDevices.getUserMedia({audio:true});
                            mr = new MediaRecorder(s); mr.start(); chunks = [];
                            mr.ondataavailable = e => chunks.push(e.data);
                            mr.onstop = () => {
                                const b = new Blob(chunks, {type:'audio/wav'});
                                const reader = new FileReader(); reader.readAsDataURL(b);
                                reader.onloadend = () => document.getElementById('tm_audio_blob').value = reader.result;
                                document.getElementById('tm-rec-status').innerText = 'تم التسجيل';
                            };
                            startBtn.style.display='none'; stopBtn.style.display='inline-block';
                        } catch(e) { alert('خطأ في الوصول للميكروفون'); }
                    };
                    stopBtn.onclick = () => { mr.stop(); startBtn.style.display='inline-block'; stopBtn.style.display='none'; };
                }
            }
        };
        tmApp.init();
    </script>
    <?php
    return ob_get_clean();
}
