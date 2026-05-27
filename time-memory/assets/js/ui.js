jQuery(document).ready(function($) {
    const categories = {
        'situations': 'المواقف والأحداث',
        'characters': 'الشخصيات والتعارف',
        'passwords': 'كلمات السر',
        'emails': 'البريد الإلكتروني',
        'usernames': 'أسماء المستخدمين',
        'phones' : 'سجلات الهاتف',
        'calculations': 'حسابات وتفاصيل',
        'financials': 'السجل المالي',
        'notes' : 'ملاحظات سريعة',
        'others' : 'أخرى'
    };

    let currentCategory = 'situations';
    let editModeId = null;
    let autoSaveTimer = null;

    $(document).on('tm_auth_success', function() {
        initDarkMode();
        showDashboard();
        startReminderPolling();
        updateStats();
        initGlobalListeners();
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

    function initGlobalListeners() {
        // Tab switching
        $(document).on('click', '.tm-nav-btn', function() {
            const slug = $(this).data('slug');
            $('.tm-nav-btn').removeClass('active');
            $(this).addClass('active');
            editModeId = null;
            if (slug === 'timeline') {
                loadTimeline();
            } else {
                currentCategory = slug;
                loadCategory(slug);
            }
        });

        // Federated Search
        let searchTimeout;
        $(document).on('keyup', '#tm-federated-search', function() {
            const query = $(this).val();
            clearTimeout(searchTimeout);
            if (query.length < 2) {
                if (query.length === 0) showDashboard();
                return;
            }
            searchTimeout = setTimeout(() => {
                $.get(tm_ajax_obj.ajax_url, { action: 'tm_federated_search', security: tm_ajax_obj.nonce, query }, function(response) {
                    if (response.success) displayFederatedResults(response.data.results);
                });
            }, 300);
        });

        // Form Submission
        $(document).on('submit', '#tm-add-form', function(e) {
            e.preventDefault();
            saveRecord(true);
        });

        // Auto-Save Trigger
        $(document).on('keyup change', '#tm-add-form input, #tm-add-form textarea, #tm-add-form select', function() {
            clearTimeout(autoSaveTimer);
            $('#tm-autosave-indicator').html('⌛ جاري التوثيق التلقائي...');
            autoSaveTimer = setTimeout(() => {
                saveRecord(false);
            }, 3000);
        });

        // Edit Button
        $(document).on('click', '.tm-edit-btn', function() {
            const id = $(this).data('id');
            editModeId = id;
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, id: id }, function(response) {
                if (response.success && response.data.records.length > 0) {
                    const r = response.data.records[0];
                    $('#tm-form-title').val(r.record_title);
                    $('#tm-form-details').val(r.record_details);
                    if (r.amount) $('#tm-form-amount').val(r.amount);
                    $('#tm-form-date').val(r.record_date.replace(' ', 'T').slice(0, 16));
                    if (r.is_encrypted == 1) $('#tm-form-encrypt').prop('checked', true);
                    if (r.related_id) $('#tm-form-related').val(r.related_id);
                    $('html, body').animate({ scrollTop: 0 }, 500);
                    $('#tm-autosave-indicator').html('⚠️ <strong>وضع التعديل نشط.</strong>');
                }
            });
        });

        // Smart Morphing Deletion
        let pendingDeleteId = null;
        $(document).on('click', '.tm-delete-btn', function() {
            const btn = $(this);
            if (btn.text() === '🗑️') {
                btn.text('⚠️ تأكيد المسح؟').css({'background': 'orange', 'color': 'black'});
                setTimeout(() => { btn.text('🗑️').css({'background': 'white', 'color': 'red'}); }, 3000);
            } else {
                pendingDeleteId = btn.data('id');
                $('#tm-passcode-input').val('');
                $('#tm-passcode-overlay').fadeIn();
            }
        });

        $(document).on('click', '#tm-passcode-confirm', function() {
            const passcode = $('#tm-passcode-input').val();
            if (passcode === '10111996') {
                $.post(tm_ajax_obj.ajax_url, { action: 'tm_delete_record', security: tm_ajax_obj.nonce, id: pendingDeleteId, passcode }, function(response) {
                    if (response.success) {
                        $('#tm-passcode-overlay').fadeOut();
                        loadCategory(currentCategory);
                        updateStats();
                    } else {
                        alert(response.data.message);
                    }
                });
            } else {
                alert('رمز التحقق خاطئ!');
            }
        });

        $(document).on('click', '#tm-passcode-cancel', function() {
            $('#tm-passcode-overlay').fadeOut();
            pendingDeleteId = null;
        });

        // Relational View
        $(document).on('click', '.tm-view-related', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            showCentralProfile(id, name);
        });

        // Sidebar close
        $(document).on('click', '#tm-sidebar-close', function() {
            $('#tm-recall-sidebar').fadeOut();
        });
    }

    function saveRecord(isManual) {
        const form = $('#tm-add-form')[0];
        if (!form) return;
        if (!form.checkValidity() && isManual) {
            form.reportValidity();
            return;
        }

        let formData = new FormData(form);
        if (editModeId) formData.append('id', editModeId);
        if (!isManual) formData.append('is_autosave', 'true');
        if (window.recordedAudioBlob) formData.append('audio', window.recordedAudioBlob, 'recording.webm');

        $.ajax({
            url: tm_ajax_obj.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    if (response.data.id) editModeId = response.data.id;
                    if (isManual) {
                        alert(response.data.message);
                        editModeId = null;
                        loadCategory(currentCategory);
                    } else {
                        $('#tm-autosave-indicator').html('✅ تم التوثيق التلقائي: ' + new Date().toLocaleTimeString());
                    }
                    updateStats();
                }
            }
        });
    }

    async function loadCategory(slug) {
        let formHtml = `
            <h3 style="font-weight: 900; margin-bottom: 25px;">إضافة جديد في ${categories[slug]}</h3>
            <div id="tm-autosave-indicator"></div>
            <form id="tm-add-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tm_add_record">
                <input type="hidden" name="category" value="${slug}">
                <input type="text" name="title" id="tm-form-title" placeholder="العنوان / الاسم" required>
                <textarea name="details" id="tm-form-details" placeholder="التفاصيل" required style="min-height: 150px;"></textarea>
                ${slug === 'financials' ? '<input type="number" step="0.01" name="amount" id="tm-form-amount" placeholder="المبلغ" required>' : ''}
                <input type="datetime-local" name="date" id="tm-form-date" value="${new Date().toISOString().slice(0, 16)}" required>
                <div style="margin-bottom:20px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_encrypted" id="tm-form-encrypt" value="true"> تشفير هذا السجل
                    </label>
                </div>
                <div id="tm-related-wrapper">
                    ${(slug === 'situations' || slug === 'financials') ? await getCharactersDropdown() : ''}
                </div>
                <button type="submit" class="tm-btn" style="width: 100%; font-size: 22px;">📥 حفظ السجل</button>
            </form>
            <hr>
            <div id="tm-records-list"></div>
        `;
        $('#tm-main-area').html(formHtml);
        $('#tm-recall-sidebar').hide();
        fetchRecords(slug);
    }

    function displayFederatedResults(results) {
        let html = '<h2 style="font-weight: 900; margin-bottom: 40px; border-bottom: 10px solid var(--border-color);">نتائج البحث الموحدة</h2>';
        if (results.length === 0) {
            html += '<p>لا توجد نتائج.</p>';
        } else {
            const grouped = {};
            results.forEach(r => {
                if (!grouped[r.category]) grouped[r.category] = [];
                grouped[r.category].push(r);
            });
            for (let cat in grouped) {
                html += `<div class="tm-search-group">
                    <h3 style="background: var(--text-color); color: var(--bg-color); padding: 10px 20px; display: inline-block; margin-bottom: 20px;">${categories[cat] || cat}</h3>
                    <div class="tm-group-items">`;
                grouped[cat].forEach(r => {
                    html += `<div class="tm-record-item" style="border-width: 3px; cursor: pointer;" onclick="window.tmLoadRecord(${r.id}, '${r.category}')">
                        <strong>${r.record_title}</strong>
                        <small style="display:block; opacity: 0.7;">${r.record_date}</small>
                    </div>`;
                });
                html += `</div></div>`;
            }
        }
        $('#tm-main-area').html(html);
    }

    window.tmLoadRecord = function(id, category) {
        currentCategory = category;
        $('.tm-nav-btn').removeClass('active');
        $(`[data-slug="${category}"]`).addClass('active');
        loadCategory(category);
    }

    function updateStats() {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_stats', security: tm_ajax_obj.nonce }, function(response) {
            if (response.success) $('#tm-stats-counter').html(`إجمالي السجلات: ${response.data.total}`);
        });
    }

    async function getCharactersDropdown() {
        return new Promise((resolve) => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: 'characters' }, function(response) {
                let html = '<div style="margin-bottom:20px;"><label style="font-weight: bold;">الارتباط بالشخصية:</label><select name="related_id" id="tm-form-related"><option value="">-- اختر ملف شخصي --</option>';
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
            if (response.success) displayRecords(response.data.records);
        });
    }

    function displayRecords(records) {
        let html = `<h3 style="font-weight: 900; margin-bottom: 30px;">📂 السجلات في ${categories[currentCategory] || currentCategory}</h3>`;
        if (records.length === 0) {
            html += '<p>لا توجد بيانات.</p>';
        } else {
            records.forEach(r => {
                const privacyClass = r.is_encrypted == 1 ? 'tm-privacy-blind' : '';
                html += `
                    <div class="tm-record-item" data-cat="${r.category}">
                        <div style="display:flex; justify-content:space-between; align-items: center; margin-bottom: 15px;">
                            <strong>${r.record_date} — ${r.record_title}</strong>
                            <div style="display:flex; gap:10px;">
                                <button class="tm-tts-btn tm-btn" style="padding:10px; font-size:14px;">🔊</button>
                                <button class="tm-edit-btn tm-btn" data-id="${r.id}" style="padding:10px; font-size:14px;">✏️ تعديل</button>
                                <button class="tm-delete-btn tm-btn" data-id="${r.id}" style="padding:10px; font-size:14px; background:white; color:red; border-color: red;">🗑️</button>
                            </div>
                        </div>
                        <p class="tm-record-details ${privacyClass}" style="white-space: pre-wrap;">${r.record_details}</p>
                        ${r.category === 'financials' ? `<div style="font-size: 24px; font-weight: 900; margin-top: 15px;">المبلغ: ${r.amount}</div>` : ''}
                        <div class="tm-action-row">
                            ${r.category === 'phones' ? `<a href="tel:${r.record_title}" class="tm-btn" style="font-size:14px; background: green; color: white;">📞 اتصال</a>` : ''}
                            ${r.category === 'characters' ? `<button class="tm-view-related tm-btn" data-id="${r.id}" data-name="${r.record_title}" style="font-size:14px;">🗂️ الملف الكامل</button>` : ''}
                        </div>
                        ${r.history && r.history.length > 0 ? `
                            <div class="tm-history-log" style="margin-top: 20px; border-top: 2px dashed var(--border-color); padding-top: 10px;">
                                <small style="display: block; font-weight: bold; margin-bottom: 5px;">📜 السجل التاريخي:</small>
                                ${r.history.map(h => `<div style="font-size: 12px; opacity: 0.6; margin-bottom: 5px;">[${h.created_at}] ${h.record_details.substring(0, 50)}...</div>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            });
        }
        $('#tm-records-list').html(html);
    }

    function showCentralProfile(charId, name) {
        $('#tm-main-area').html(`<h2 style="font-weight: 900; margin-bottom: 40px;">👤 الملف المركزي: ${name}</h2><div id="tm-profile-content"></div>`);

        // Show Sidebar with last event summary
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: 'situations', related_id: charId }, function(response) {
            if (response.success && response.data.records.length > 0) {
                const last = response.data.records[0];
                $('#tm-sidebar-content').html(`
                    <div style="font-size: 15px; border: 2px solid var(--border-color); padding: 15px;">
                        <p><strong>آخر تفاعل:</strong> ${last.record_date}</p>
                        <p><strong>العنوان:</strong> ${last.record_title}</p>
                        <p style="font-style: italic;">"${last.record_details.substring(0, 100)}..."</p>
                    </div>
                `);
                $('#tm-recall-sidebar').fadeIn();
            }
        });

        const sections = [
            { cat: 'phones', title: '📱 أرقام الهاتف' },
            { cat: 'financials', title: '💰 المعاملات المالية' },
            { cat: 'situations', title: '🎭 المواقف والأحداث' },
            { cat: 'notes', title: '📝 الملاحظات الخاصة' }
        ];
        let profileHtml = '';
        sections.forEach(s => {
            profileHtml += `<div style="margin-bottom: 40px;">
                <h3 style="background: var(--text-color); color: var(--bg-color); padding: 10px 20px;">${s.title}</h3>
                <div id="tm-profile-data-${s.cat}">جاري التحميل...</div>
                <button class="tm-btn tm-prefill-btn" data-cat="${s.cat}" data-id="${charId}" data-name="${name}" style="font-size: 12px; margin-top: 10px;">+ إضافة جديد مرتبط</button>
            </div>`;
        });
        $('#tm-profile-content').html(profileHtml);
        sections.forEach(s => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: s.cat, related_id: charId }, function(response) {
                let itemsHtml = '';
                if (response.success && response.data.records.length > 0) {
                    response.data.records.forEach(r => {
                        itemsHtml += `<div class="tm-record-item" style="border-width: 2px;" data-cat="${r.category}">
                            <strong>${r.record_title}</strong>
                            <p class="tm-record-details">${r.record_details}</p>
                            ${r.amount ? `<div style="font-weight: bold;">المبلغ: ${r.amount}</div>` : ''}
                        </div>`;
                    });
                } else {
                    itemsHtml = '<p>لا توجد بيانات.</p>';
                }
                $(`#tm-profile-data-${s.cat}`).html(itemsHtml);
            });
        });
    }

    $(document).on('click', '.tm-prefill-btn', function() {
        const cat = $(this).data('cat');
        const charId = $(this).data('id');
        const name = $(this).data('name');
        currentCategory = cat;
        $('.tm-nav-btn').removeClass('active');
        $(`[data-slug="${cat}"]`).addClass('active');
        loadCategory(cat).then(() => {
            $('#tm-form-related').val(charId);
            $('#tm-form-title').val(`مرتبط بـ ${name}`);
            $('html, body').animate({ scrollTop: 0 }, 500);
        });
    });

    function loadTimeline() {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce }, function(response) {
            if (response.success) {
                $('#tm-main-area').html('<h3 style="font-weight: 900; margin-bottom: 30px;">🕒 التسلسل الزمني</h3><div id="tm-records-list"></div>');
                const sorted = response.data.records.sort((a, b) => new Date(a.record_date) - new Date(b.record_date));
                displayRecords(sorted);
            }
        });
    }

    function startReminderPolling() {
        setInterval(() => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_reminders', security: tm_ajax_obj.nonce }, function(response) {
                if (response.success && response.data.reminders.length > 0) {}
            });
        }, 60000);
    }
});
