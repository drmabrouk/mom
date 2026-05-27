jQuery(document).ready(function($) {
    const categories = {
        'situations': 'المواقف والأحداث',
        'characters': 'الشخصيات والتعارف',
        'passwords': 'كلمات السر',
        'emails': 'البريد الإلكتروني',
        'usernames': 'أسماء المستخدمين',
        'phones' : 'سجلات الهاتف',
        'calculations': 'حسابات وتفاصيل',
        'others' : 'أخرى'
    };

    let currentCategory = 'situations';

    $(document).on('tm_auth_success', function() {
        showDashboard();
        startReminderPolling();
    });

    function showDashboard() {
        let navHtml = '<div class="tm-nav" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">';
        for (let slug in categories) {
            navHtml += `<button class="tm-nav-btn tm-btn ${currentCategory === slug ? 'active' : ''}" data-slug="${slug}">${categories[slug]}</button>`;
        }
        navHtml += '<button class="tm-nav-btn tm-btn" data-slug="timeline">الجدول الزمني</button>';
        navHtml += '</div>';

        let zoomControls = `
            <div style="margin-bottom:20px;">
                <button id="tm-zoom-in" class="tm-btn">تكبير الخط +</button>
                <button id="tm-zoom-out" class="tm-btn">تصغير الخط -</button>
            </div>
        `;

        $('#tm-content').html(navHtml + zoomControls + '<div id="tm-main-area"></div>');
        loadCategory(currentCategory);
    }

    $(document).on('click', '.tm-nav-btn', function() {
        const slug = $(this).data('slug');
        $('.tm-nav-btn').removeClass('active');
        $(this).addClass('active');
        if (slug === 'timeline') {
            loadTimeline();
        } else {
            currentCategory = slug;
            loadCategory(slug);
        }
    });

    async function loadCategory(slug) {
        let formHtml = `
            <h3>إضافة جديد في ${categories[slug]}</h3>
            <form id="tm-add-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tm_add_record">
                <input type="hidden" name="category" value="${slug}">
                <input type="text" name="title" placeholder="العنوان / الاسم" required>
                <textarea name="details" placeholder="التفاصيل" required></textarea>
                <input type="date" name="date" value="${new Date().toISOString().split('T')[0]}" required>

                <div style="margin-bottom:15px;">
                    <label><input type="checkbox" name="is_encrypted" value="true"> تشفير هذا السجل</label>
                </div>

                <div style="margin-bottom:15px;">
                    <label>تنبيه في تاريخ معين:</label>
                    <input type="datetime-local" name="reminder_date">
                </div>

                <div style="margin-bottom:15px;">
                    <label>تكرار التنبيه:</label>
                    <select name="reminder_frequency">
                        <option value="">لا يوجد تكرار</option>
                        <option value="daily">يومي</option>
                        <option value="weekly">أسبوعي</option>
                        <option value="monthly">شهري</option>
                    </select>
                </div>

                ${slug === 'situations' ? await getCharactersDropdown() : ''}

                <div style="margin-bottom:15px;">
                    <label>إرفاق صورة:</label>
                    <input type="file" name="image" id="tm-image-input" accept="image/*">
                    <img id="tm-image-preview" style="max-width:200px; display:none; margin-top:10px;">
                </div>

                <div style="margin-bottom:15px;">
                    <label>تسجيل صوتي:</label>
                    <button type="button" id="tm-record-btn" class="tm-btn">ابدأ التسجيل</button>
                    <button type="button" id="tm-stop-btn" class="tm-btn" style="display:none;">إيقاف</button>
                    <audio id="tm-audio-playback" controls style="display:none; margin-top:10px;"></audio>
                </div>

                <button type="submit" class="tm-btn">حفظ في الذاكرة</button>
            </form>
            <hr>
            <div id="tm-records-list"></div>
        `;
        $('#tm-main-area').html(formHtml);
        initAudioRecorder();
        fetchRecords(slug);
    }

    async function getCharactersDropdown() {
        return new Promise((resolve) => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: 'characters' }, function(response) {
                let html = '<div style="margin-bottom:15px;"><label>ارتباط بشخصية:</label><select name="related_id"><option value="">-- اختر شخصية --</option>';
                if (response.success && response.data.records) {
                    response.data.records.forEach(r => {
                        html += `<option value="${r.id}">${r.record_title}</option>`;
                    });
                }
                html += '</select></div>';
                resolve(html);
            });
        });
    }

    function fetchRecords(category, related_id = null) {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category, related_id }, function(response) {
            if (response.success) {
                displayRecords(response.data.records);
            }
        });
    }

    function displayRecords(records) {
        let html = '<h3>السجلات المحفوظة</h3>';
        if (records.length === 0) {
            html += '<p>لا توجد سجلات.</p>';
        } else {
            records.forEach(r => {
                html += `
                    <div class="tm-record-item" style="border:2px solid #000; padding:15px; margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between;">
                            <strong>${r.record_date} - ${r.record_title}</strong>
                            <div>
                                <button class="tm-tts-btn tm-btn" style="padding:5px 10px; font-size:12px;">قراءة</button>
                                <button class="tm-delete-btn tm-btn" data-id="${r.id}" style="padding:5px 10px; font-size:12px; background:red;">حذف</button>
                            </div>
                        </div>
                        <p class="tm-record-details" style="white-space: pre-wrap;">${r.record_details}</p>
                        ${r.image_url ? `<img src="${r.image_url}" style="max-width:100%; border:1px solid #000;">` : ''}
                        ${r.audio_url ? `<audio src="${r.audio_url}" controls style="width:100%; margin-top:10px;"></audio>` : ''}
                        ${r.category === 'characters' ? `<button class="tm-view-related tm-btn" data-id="${r.id}" style="font-size:12px; margin-top:10px;">عرض المواقف المرتبطة</button>` : ''}
                    </div>
                `;
            });
        }
        $('#tm-records-list').html(html);
    }

    $(document).on('submit', '#tm-add-form', function(e) {
        e.preventDefault();
        let formData = new FormData(this);

        if (window.recordedAudioBlob) {
            formData.append('audio', window.recordedAudioBlob, 'recording.webm');
        }

        $.ajax({
            url: tm_ajax_obj.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadCategory(currentCategory);
                    window.recordedAudioBlob = null;
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    $(document).on('click', '.tm-delete-btn', function() {
        if (confirm('هل أنت متأكد من الحذف؟')) {
            const id = $(this).data('id');
            $.post(tm_ajax_obj.ajax_url, { action: 'tm_delete_record', security: tm_ajax_obj.nonce, id }, function(response) {
                if (response.success) {
                    loadCategory(currentCategory);
                }
            });
        }
    });

    $(document).on('click', '.tm-view-related', function() {
        const id = $(this).data('id');
        fetchRecords('situations', id);
        $('html, body').animate({ scrollTop: $("#tm-records-list").offset().top }, 500);
    });

    function loadTimeline() {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce }, function(response) {
            if (response.success) {
                let html = '<h3>الجدول الزمني للأحداث</h3>';
                const sorted = response.data.records.sort((a, b) => new Date(a.record_date) - new Date(b.record_date));
                displayRecords(sorted);
            }
        });
    }

    function startReminderPolling() {
        setInterval(() => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_reminders', security: tm_ajax_obj.nonce }, function(response) {
                if (response.success && response.data.reminders.length > 0) {
                    response.data.reminders.forEach(r => {
                        // In a real app, we'd track shown reminders. For now, just alert.
                        // alert(`تذكير: ${r.record_title}\n${r.record_date}`);
                    });
                }
            });
        }, 60000);
    }
});
