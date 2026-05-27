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
        initDarkMode();
        showDashboard();
        startReminderPolling();
    });

    // Dark Mode Logic
    function initDarkMode() {
        const manualDark = localStorage.getItem('tm-dark-mode');
        const hour = new Date().getHours();
        const isNight = hour >= 18 || hour < 6;

        if (manualDark === 'enabled' || (manualDark === null && isNight)) {
            $('body').addClass('tm-dark-mode');
        }

        $(document).on('click', '#tm-dark-toggle', function() {
            if ($('body').hasClass('tm-dark-mode')) {
                $('body').removeClass('tm-dark-mode');
                localStorage.setItem('tm-dark-mode', 'disabled');
            } else {
                $('body').addClass('tm-dark-mode');
                localStorage.setItem('tm-dark-mode', 'enabled');
            }
        });
    }

    function showDashboard() {
        let navHtml = '<div class="tm-nav">';
        for (let slug in categories) {
            navHtml += `<button class="tm-nav-btn ${currentCategory === slug ? 'active' : ''}" data-slug="${slug}">${categories[slug]}</button>`;
        }
        navHtml += '<button class="tm-nav-btn" data-slug="timeline">الجدول الزمني</button>';
        navHtml += '</div>';

        $('#tm-content').html(navHtml + '<div id="tm-main-area"></div>');
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
            <div id="tm-autosave-indicator"></div>
            <form id="tm-add-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tm_add_record">
                <input type="hidden" name="category" value="${slug}">
                <input type="text" name="title" id="tm-form-title" placeholder="العنوان / الاسم" required>
                <textarea name="details" id="tm-form-details" placeholder="التفاصيل" required></textarea>
                <input type="date" name="date" id="tm-form-date" value="${new Date().toISOString().split('T')[0]}" required>

                <div style="margin-bottom:15px;">
                    <label><input type="checkbox" name="is_encrypted" id="tm-form-encrypt" value="true"> تشفير هذا السجل (سيتم تطبيق غطاء الخصوصية)</label>
                </div>

                <div style="margin-bottom:15px;">
                    <label>تنبيه في تاريخ معين:</label>
                    <input type="datetime-local" name="reminder_date" id="tm-form-reminder-date">
                </div>

                <div style="margin-bottom:15px;">
                    <label>تكرار التنبيه:</label>
                    <select name="reminder_frequency" id="tm-form-reminder-freq">
                        <option value="">لا يوجد تكرار</option>
                        <option value="daily">يومي</option>
                        <option value="weekly">أسبوعي</option>
                        <option value="monthly">شهري</option>
                    </select>
                </div>

                <div id="tm-related-wrapper">
                    ${slug === 'situations' ? await getCharactersDropdown() : ''}
                </div>

                <div style="margin-bottom:15px;">
                    <label>إرفاق صورة:</label>
                    <input type="file" name="image" id="tm-image-input" accept="image/*">
                    <img id="tm-image-preview" style="max-width:200px; display:none; margin-top:10px; border:2px solid var(--border-color);">
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
            <div id="tm-search-box">
                <input type="text" id="tm-search-input" placeholder="بحث سريع في السجلات...">
            </div>
            <div id="tm-records-list"></div>
        `;
        $('#tm-main-area').html(formHtml);
        $('#tm-recall-sidebar').hide();
        initAudioRecorder();
        initAutoSave(slug);
        fetchRecords(slug);
    }

    // Auto-Save Logic
    function initAutoSave(slug) {
        const savedData = JSON.parse(localStorage.getItem('tm-autosave-' + slug));
        if (savedData) {
            $('#tm-form-title').val(savedData.title);
            $('#tm-form-details').val(savedData.details);
            $('#tm-form-date').val(savedData.date);
            if (savedData.encrypt) $('#tm-form-encrypt').prop('checked', true);
            $('#tm-form-reminder-date').val(savedData.reminder_date);
            $('#tm-form-reminder-freq').val(savedData.reminder_freq);
            $('#tm-autosave-indicator').text('تم استعادة المسودة المحفوظة.');
        }

        $(document).on('keyup change', '#tm-add-form input, #tm-add-form textarea, #tm-add-form select', function() {
            const data = {
                title: $('#tm-form-title').val(),
                details: $('#tm-form-details').val(),
                date: $('#tm-form-date').val(),
                encrypt: $('#tm-form-encrypt').is(':checked'),
                reminder_date: $('#tm-form-reminder-date').val(),
                reminder_freq: $('#tm-form-reminder-freq').val()
            };
            localStorage.setItem('tm-autosave-' + currentCategory, JSON.stringify(data));
            $('#tm-autosave-indicator').text('تم الحفظ تلقائياً: ' + new Date().toLocaleTimeString());
        });
    }

    // Live Search Logic
    $(document).on('keyup', '#tm-search-input', function() {
        const val = $(this).val().toLowerCase();
        $('.tm-record-item').each(function() {
            const title = $(this).data('title');
            const details = $(this).data('details');
            if (title.indexOf(val) > -1 || details.indexOf(val) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    async function getCharactersDropdown() {
        return new Promise((resolve) => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: 'characters' }, function(response) {
                let html = '<div style="margin-bottom:15px;"><label>ارتباط بشخصية:</label><select name="related_id" id="tm-form-related"><option value="">-- اختر شخصية --</option>';
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
                const privacyClass = r.is_encrypted == 1 ? 'tm-privacy-blind' : '';
                html += `
                    <div class="tm-record-item" data-title="${r.record_title.toLowerCase()}" data-details="${r.record_details.toLowerCase()}">
                        <div style="display:flex; justify-content:space-between; align-items: flex-start; margin-bottom: 15px;">
                            <strong>${r.record_date} - ${r.record_title}</strong>
                            <div style="display:flex; gap:10px;">
                                <button class="tm-tts-btn tm-btn" style="padding:10px; font-size:14px;">قراءة</button>
                                <button class="tm-delete-btn tm-btn" data-id="${r.id}" style="padding:10px; font-size:14px; background:red; color:white;">حذف</button>
                            </div>
                        </div>
                        <p class="tm-record-details ${privacyClass}" style="white-space: pre-wrap;">${r.record_details}</p>
                        ${r.image_url ? `<img src="${r.image_url}" style="max-width:100%; border:3px solid var(--border-color); margin-top:15px;">` : ''}
                        ${r.audio_url ? `<audio src="${r.audio_url}" controls style="width:100%; margin-top:15px;"></audio>` : ''}
                        ${r.category === 'characters' ? `<button class="tm-view-related tm-btn" data-id="${r.id}" data-name="${r.record_title}" style="font-size:14px; margin-top:15px;">عرض المواقف المرتبطة</button>` : ''}
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
                    localStorage.removeItem('tm-autosave-' + currentCategory);
                    loadCategory(currentCategory);
                    window.recordedAudioBlob = null;
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Smart Deletion Confirmation
    $(document).on('click', '.tm-delete-btn', function() {
        const btn = $(this);
        if (btn.text() === 'حذف') {
            btn.text('تأكيد الحذف؟').css('background', 'orange');
            setTimeout(() => {
                btn.text('حذف').css('background', 'red');
            }, 3000);
        } else {
            const id = btn.data('id');
            $.post(tm_ajax_obj.ajax_url, { action: 'tm_delete_record', security: tm_ajax_obj.nonce, id }, function(response) {
                if (response.success) {
                    loadCategory(currentCategory);
                }
            });
        }
    });

    $(document).on('click', '.tm-view-related', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        currentCategory = 'situations';
        $('.tm-nav-btn').removeClass('active');
        $('[data-slug="situations"]').addClass('active');

        loadCategory('situations').then(() => {
            // Intelligent Form Pre-Filling
            $('#tm-form-related').val(id);
            $('#tm-form-title').val(`موقف مع ${name}`);
            $('#tm-form-date').val(new Date().toISOString().split('T')[0]);

            fetchRecords('situations', id);
            showRecallSidebar(id);
        });

        $('html, body').animate({ scrollTop: 0 }, 500);
    });

    // Contextual Recall Sidebar
    function showRecallSidebar(characterId) {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: 'situations', related_id: characterId }, function(response) {
            if (response.success && response.data.records.length > 0) {
                const lastEvent = response.data.records[0];
                let sidebarHtml = `
                    <div style="font-size: 16px;">
                        <p><strong>آخر حدث:</strong> ${lastEvent.record_date}</p>
                        <p><strong>العنوان:</strong> ${lastEvent.record_title}</p>
                        <p style="font-style: italic;">"${lastEvent.record_details.substring(0, 100)}..."</p>
                    </div>
                `;
                $('#tm-sidebar-content').html(sidebarHtml);
                $('#tm-recall-sidebar').show();
            } else {
                $('#tm-sidebar-content').html('<p>لا توجد أحداث سابقة مسجلة.</p>');
                $('#tm-recall-sidebar').show();
            }
        });
    }

    function loadTimeline() {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce }, function(response) {
            if (response.success) {
                $('#tm-main-area').html('<h3>الجدول الزمني للأحداث</h3><div id="tm-records-list"></div>');
                $('#tm-recall-sidebar').hide();
                const sorted = response.data.records.sort((a, b) => new Date(a.record_date) - new Date(b.record_date));
                displayRecords(sorted);
            }
        });
    }

    function startReminderPolling() {
        setInterval(() => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_reminders', security: tm_ajax_obj.nonce }, function(response) {
                if (response.success && response.data.reminders.length > 0) {
                    // Logic for alerts can go here
                }
            });
        }, 60000);
    }
});
