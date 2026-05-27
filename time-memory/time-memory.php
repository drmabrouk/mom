<?php
/*
Plugin Name: ذاكرة الزمن
Plugin URI:
Description: إضافة إحترافية بمثابة ذاكرة رقمية لحفظ المواقف، الشخصيات، كلمات السر، والبيانات الهامة بتباين بصري عالي (أبيض وأسود).
Version: 2.0
Author: الذكاء الاصطناعي (Gemini)
*/

// منع الوصول المباشر
if (!defined('ABSPATH')) exit;

// 1. إنشاء جدول قاعدة البيانات وصفحة التطبيق عند تفعيل الإضافة
register_activation_hook(__FILE__, 'tm_plugin_activate');
function tm_plugin_activate() {
    tm_create_database_table();
    tm_create_frontend_page();
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

// 2. معالجة الإجراءات (Login, CRUD) قبل إرسال الرؤوس
add_action('template_redirect', 'tm_handle_frontend_actions');
function tm_handle_frontend_actions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';

    // 1. تسجيل الدخول
    if (isset($_POST['tm_frontend_login'])) {
        if (!isset($_POST['tm_login_nonce']) || !wp_verify_nonce($_POST['tm_login_nonce'], 'tm_login_action')) return;
        $username = sanitize_text_field($_POST['tm_username']);
        $password = sanitize_text_field($_POST['tm_password']);
        if ($username === 'ahmed' && $password === '10111996') {
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
            $filename = 'voice-' . time() . '.wav';
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

    ob_start();
    ?>
    <style>
        :root { --tm-base-font-size: 16px; }
        .tm-app-wrap { font-family: Tahoma, sans-serif; max-width: 1000px; margin: 20px auto; background: #fff; border: 3px solid #000; padding: 30px; box-shadow: 10px 10px 0px #000; color: #000; direction: rtl; font-size: var(--tm-base-font-size); }
        .tm-app-wrap h1, .tm-app-wrap h2, .tm-app-wrap h3 { color: #000; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .tm-form-group { margin-bottom: 15px; }
        .tm-form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .tm-form-group input, .tm-form-group select, .tm-form-group textarea { width: 100%; padding: 10px; border: 2px solid #000; background: #fff; color: #000; font-size: inherit; }
        .tm-btn { background: #000; color: #fff; padding: 10px 20px; border: 2px solid #000; font-size: inherit; cursor: pointer; text-decoration: none; display: inline-block; }
        .tm-btn:hover { background: #fff; color: #000; }
        .tm-btn-danger { background: #fff; color: #000; border: 2px solid #000; font-weight:bold; }
        .tm-btn-danger:hover { background: #000; color: #fff; }
        .tm-nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tm-nav a { border: 2px solid #000; padding: 10px; text-decoration: none; color: #000; font-weight: bold; }
        .tm-nav a.active, .tm-nav a:hover { background: #000; color: #fff; }
        .tm-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tm-table th, .tm-table td { border: 2px solid #000; padding: 12px; text-align: right; }
        .tm-table th { background: #000; color: #fff; }
        .tm-alert { border: 3px solid #000; padding: 15px; margin-bottom: 20px; background: #ffff00; font-weight: bold; }
        .tm-media-preview img { width: 60px; height: 60px; margin: 5px; border: 1px solid #000; object-fit: cover; }
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

        // عرض التذكيرات (مع منطق التكرار البسيط)
        $today = date('Y-m-d');
        $all_reminders = $wpdb->get_results("SELECT * FROM $table_name WHERE reminder_data != ''");
        foreach ($all_reminders as $rem) {
            $rdata = json_decode($rem->reminder_data, true);
            if ($rdata && isset($rdata['date'])) {
                $show_alert = false;
                $rem_date = $rdata['date'];
                $freq = isset($rdata['freq']) ? $rdata['freq'] : 'once';

                if ($rem_date === $today) {
                    $show_alert = true;
                } elseif ($rem_date < $today) {
                    if ($freq === 'daily') {
                        $show_alert = true;
                    } elseif ($freq === 'weekly') {
                        $diff = (strtotime($today) - strtotime($rem_date)) / (60 * 60 * 24);
                        if ($diff % 7 === 0) $show_alert = true;
                    } elseif ($freq === 'monthly') {
                        if (date('d', strtotime($rem_date)) === date('d', strtotime($today))) $show_alert = true;
                    }
                }

                if ($show_alert) {
                    $rem_title = $rem->is_encrypted ? tm_decrypt($rem->record_title) : $rem->record_title;
                    echo '<div class="tm-alert">تنبيه: تذكير بـ "' . esc_html($rem_title) . '"</div>';
                }
            }
        }

        if (isset($_GET['tm_msg'])) {
            if ($_GET['tm_msg'] === 'saved') echo '<div class="tm-alert">تم حفظ السجل بنجاح.</div>';
            if ($_GET['tm_msg'] === 'deleted') echo '<div class="tm-alert">تم حذف السجل.</div>';
        }

        $categories = ['situations'=>'المواقف', 'characters'=>'الشخصيات', 'passwords'=>'كلمات السر', 'timeline'=>'الجدول الزمني', 'reminders'=>'التذكيرات'];
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'situations';
        ?>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>ذاكرة الزمن</h1>
            <div>
                <button class="tm-btn" onclick="tmApp.zoom(1.2)">A+</button>
                <button class="tm-btn" onclick="tmApp.zoom(1.0)">A</button>
                <button class="tm-btn" onclick="tmApp.zoom(0.8)">A-</button>
                <a href="<?php echo add_query_arg('tm_action', 'logout'); ?>" class="tm-btn tm-btn-danger">خروج</a>
            </div>
        </div>

        <div class="tm-nav">
            <?php foreach ($categories as $slug => $name): ?>
                <a href="<?php echo add_query_arg(['tab' => $slug, 'tm_filter_char' => false]); ?>" class="<?php echo ($current_tab===$slug)?'active':''; ?>"><?php echo $name; ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($current_tab !== 'timeline' && $current_tab !== 'reminders'): ?>
            <h3>إضافة إلى <?php echo $categories[$current_tab]; ?></h3>
            <form method="post" enctype="multipart/form-data" style="border:2px dashed #000; padding:20px; background:#fafafa; margin-bottom:20px;">
                <?php wp_nonce_field('tm_add_record_action', 'tm_record_nonce'); ?>
                <input type="hidden" name="tm_category" value="<?php echo $current_tab; ?>">
                <div class="tm-form-group"><label>العنوان:</label><input type="text" name="tm_title" required></div>
                <div class="tm-form-group"><label>التاريخ:</label><input type="date" name="tm_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="tm-form-group"><label>التفاصيل:</label><textarea name="tm_details" rows="3" required></textarea></div>
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
                    $chars = $wpdb->get_results("SELECT id, record_title FROM $table_name WHERE category='characters'");
                    if ($chars): ?>
                        <div class="tm-form-group"><label>ربط بشخصية:</label><select name="tm_related_id"><option value="0">-- لا يوجد --</option>
                        <?php foreach($chars as $c) echo '<option value="'.$c->id.'">'. esc_html($c->record_title) .'</option>'; ?>
                        </select></div>
                    <?php endif;
                endif; ?>
                <div style="border:1px solid #000; padding:10px; margin-bottom:10px; background:#eee;">
                    <strong>تذكير:</strong> <input type="date" name="tm_reminder_date">
                    تكرار: <select name="tm_reminder_freq"><option value="once">مرة واحدة</option><option value="daily">يومي</option></select>
                </div>
                <button type="submit" name="tm_add_record_frontend" class="tm-btn">حفظ في الذاكرة</button>
            </form>
        <?php endif; ?>

        <div id="tm-records-list">
            <?php
            $filter_char = isset($_GET['tm_filter_char']) ? intval($_GET['tm_filter_char']) : 0;
            if ($current_tab === 'timeline') $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY record_date DESC");
            elseif ($current_tab === 'reminders') $records = $wpdb->get_results("SELECT * FROM $table_name WHERE reminder_data!='' ORDER BY record_date DESC");
            elseif ($current_tab === 'situations' && $filter_char) $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category='situations' AND related_id=%d ORDER BY record_date DESC", $filter_char));
            else $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category=%s ORDER BY record_date DESC", $current_tab));

            if ($filter_char) echo '<div class="tm-alert">تصفية حسب الشخصية. <a href="'.add_query_arg('tm_filter_char', false).'">إلغاء التصفية</a></div>';

            if ($records): ?>
                <table class="tm-table">
                    <thead><tr><th>التاريخ</th><th>العنوان</th><th>التفاصيل والوسائط</th><th>إجراء</th></tr></thead>
                    <tbody>
                        <?php foreach ($records as $r):
                            $t = $r->record_title; $d = $r->record_details;
                            if ($r->is_encrypted) { $t = tm_decrypt($t).' (مشفر)'; $d = tm_decrypt($d); }

                            $display_title = esc_html($t);
                            if ($r->category === 'characters') {
                                $display_title = '<a href="'.add_query_arg(['tab'=>'situations', 'tm_filter_char'=>$r->id]).'" style="text-decoration:underline;">'.esc_html($t).'</a>';
                            }
                            ?>
                            <tr>
                                <td><?php echo $r->record_date; ?></td>
                                <td><strong><?php echo $display_title; ?></strong></td>
                                <td><?php echo nl2br(esc_html($d)); ?>
                                    <div class="tm-media-preview">
                                        <?php $imgs = json_decode($r->record_media,true); if($imgs) foreach($imgs as $img) echo '<a href="'.esc_url($img).'" target="_blank"><img src="'.esc_url($img).'"></a>'; ?>
                                        <?php if($r->record_audio) echo '<div style="margin-top:10px;"><audio controls src="'.esc_url($r->record_audio).'"></audio></div>'; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="tm-btn" style="padding:5px; margin-bottom:5px;" onclick="tmApp.speak('<?php echo esc_js($d); ?>')">🔊</button>
                                    <a href="<?php echo wp_nonce_url(add_query_arg('tm_delete_id', $r->id), 'tm_delete_' . $r->id); ?>" class="tm-btn tm-btn-danger" style="padding:5px;" onclick="return confirm('هل أنت متأكد؟');">X</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: echo '<p style="text-align:center; padding:20px; border:1px solid #000;">لا توجد سجلات.</p>'; endif; ?>
        </div>
    </div>

    <script>
        const tmApp = {
            inactivity: 0,
            zoom: (factor) => { document.querySelector('.tm-app-wrap').style.fontSize = (16 * factor) + 'px'; },
            speak: (text) => { window.speechSynthesis.cancel(); const u = new SpeechSynthesisUtterance(text); u.lang = 'ar-SA'; window.speechSynthesis.speak(u); },
            init: function() {
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
                                document.getElementById('tm-rec-status').innerText = 'تم تسجيل الصوت بنجاح';
                            };
                            startBtn.style.display='none'; stopBtn.style.display='inline-block';
                            document.getElementById('tm-rec-status').innerText = 'جاري التسجيل...';
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
