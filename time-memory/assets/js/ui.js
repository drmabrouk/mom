jQuery(document).ready(function($) {
    const modules = {
        'situations': { title: 'الأحداث', icon: '📅' },
        'characters': { title: 'الملفات', icon: '👤' },
        'phones': { title: 'الهاتف', icon: '📞' },
        'passwords': { title: 'الأمان', icon: '🔐' },
        'usernames': { title: 'الحسابات', icon: '🆔' },
        'emails': { title: 'البريد', icon: '📧' },
        'financials': { title: 'المالية', icon: '💰' },
        'vault': { title: 'الخزنة', icon: '📦' }
    };

    let currentModule = 'situations';
    let editModeId = null;
    let autoSaveTimer = null;

    // --- Core Initialization ---
    $(document).on('tm_auth_success', function() {
        initTheme();
        renderFloatingBadges();
        loadModule(currentModule);
        initGlobalListeners();
        updateLiveStats();
    });

    function initTheme() {
        const manual = localStorage.getItem('tm_dark');
        const hour = new Date().getHours();
        const isNight = hour >= 18 || hour < 6;
        if (manual === 'true' || (manual === null && isNight)) {
            $('body').addClass('tm-dark-mode');
        }
    }

    function renderFloatingBadges() {
        let navHtml = '';
        for (let m in modules) {
            navHtml += `<button class="tm-nav-btn ${currentModule === m ? 'active' : ''}" data-mod="${m}">
                <span>${modules[m].icon}</span> <span>${modules[m].title}</span>
            </button>`;
        }
        $('#tm-main-nav').html(navHtml);
    }

    function initGlobalListeners() {
        $(document).off('click', '.tm-nav-btn').on('click', '.tm-nav-btn', function() {
            const mod = $(this).data('mod');
            $('.tm-nav-btn').removeClass('active');
            $(this).addClass('active');
            editModeId = null;
            currentModule = mod;
            loadModule(mod);
        });

        let sTimer;
        $(document).on('keyup', '#tm-federated-search', function() {
            const q = $(this).val();
            clearTimeout(sTimer);
            if (q.length < 2) {
                if (q.length === 0) loadModule(currentModule);
                return;
            }
            sTimer = setTimeout(() => {
                $.get(tm_ajax_obj.ajax_url, { action: 'tm_federated_search', security: tm_ajax_obj.nonce, query: q }, function(res) {
                    if (res.success) renderFederatedResults(res.data.results);
                });
            }, 400);
        });

        let deleteId = null;
        $(document).on('click', '.tm-delete-trigger', function() {
            const btn = $(this);
            if (btn.text() === '🗑️') {
                btn.text('⚠️ تأكيد؟').css('background', 'orange');
                setTimeout(() => btn.text('🗑️').css('background', 'none'), 3000);
            } else {
                deleteId = btn.data('id');
                $('#tm-passcode-input').val('');
                $('#tm-passcode-overlay').fadeIn().css('display', 'flex');
            }
        });

        $('#tm-passcode-confirm').on('click', function() {
            const pin = $('#tm-passcode-input').val();
            $.post(tm_ajax_obj.ajax_url, { action: 'tm_delete_record', security: tm_ajax_obj.nonce, id: deleteId, passcode: pin }, function(res) {
                if (res.success) {
                    $('#tm-passcode-overlay').fadeOut();
                    loadModule(currentModule);
                    updateLiveStats();
                } else {
                    alert(res.data.message);
                }
            });
        });

        $('#tm-passcode-cancel').on('click', () => $('#tm-passcode-overlay').fadeOut());

        $('#tm-dark-toggle').on('click', function() {
            $('body').toggleClass('tm-dark-mode');
            localStorage.setItem('tm_dark', $('body').hasClass('tm-dark-mode'));
        });

        $(document).on('submit', '#tm-action-form', function(e) {
            e.preventDefault();
            saveRecord(true);
        });

        $(document).on('click', '.tm-edit-trigger', function() {
            const id = $(this).data('id');
            editModeId = id;
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, id: id }, function(res) {
                if (res.success && res.data.records.length > 0) {
                    const r = res.data.records[0];
                    $('#f-title').val(r.record_title);
                    $('#f-details').val(r.record_details);
                    if (r.amount) $('#f-amount').val(r.amount);
                    if (r.is_encrypted == 1) $('#f-encrypt').prop('checked', true);
                    if (r.is_pinned == 1) $('#f-pinned').prop('checked', true);
                    $('#f-priority').val(r.priority);
                    if (r.tags) $('#f-tags').val(r.tags);
                    if (r.metadata) {
                        try {
                            const meta = JSON.parse(r.metadata);
                            if (meta.username) $('#f-username').val(meta.username);
                            if (meta.hint) $('#f-hint').val(meta.hint);
                        } catch(e) {}
                    }
                    $('#tm-stage').animate({ scrollTop: 0 }, 500);
                    $('#tm-module-indicator').html('<strong style="color:orange;">تعديل نشط</strong>');
                }
            });
        });

        $(document).on('click', '#tm-sidebar-close', () => $('#tm-recall-sidebar').fadeOut());

        $(document).on('input', '#f-details', function() {
            if (currentModule === 'passwords') {
                const entropy = calculateEntropy($(this).val());
                $('#tm-entropy-bar').css('width', Math.min(entropy * 1.5, 100) + '%')
                    .css('background', entropy > 50 ? 'green' : (entropy > 30 ? 'orange' : 'red'));
            }
        });
    }

    function calculateEntropy(str) {
        if (!str) return 0;
        let pool = 0;
        if (/[a-z]/.test(str)) pool += 26;
        if (/[A-Z]/.test(str)) pool += 26;
        if (/[0-9]/.test(str)) pool += 10;
        if (/[^a-zA-Z0-9]/.test(str)) pool += 32;
        return str.length * Math.log2(pool);
    }

    function loadModule(mod) {
        let stageHtml = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                <h2 style="font-size:32px; font-weight:900;">${modules[mod].title}</h2>
                <div id="tm-module-indicator" style="font-size:14px; font-weight:900;"></div>
            </div>
            <form id="tm-action-form">
                <input type="hidden" name="category" value="${mod}">
                <div class="tm-form-grid">
                    <input type="text" name="title" id="f-title" placeholder="${getPlaceholder(mod)}" required>
                    ${mod === 'usernames' ? '<input type="text" name="metadata[username]" id="f-username" placeholder="المعرف المخصص">' : ''}
                    <textarea name="details" id="f-details" placeholder="${mod === 'passwords' ? 'أدخل كلمة السر...' : 'التفاصيل العميقة...'}" required style="min-height:150px;"></textarea>
                    ${mod === 'passwords' ? '<div style="height:8px; background:#ddd; margin-top:-10px; margin-bottom:15px; border:1px solid #000;"><div id="tm-entropy-bar" style="height:100%; width:0%; transition:width 0.4s;"></div></div>' : ''}
                    ${mod === 'passwords' ? '<input type="text" name="metadata[hint]" id="f-hint" placeholder="تلميح الأمان (بدون سر صريح)">' : ''}
                    ${mod === 'financials' ? '<input type="number" step="0.01" name="amount" id="f-amount" placeholder="المبلغ (+/-)">' : ''}
                    <div class="tm-form-extras">
                        <label><input type="checkbox" name="is_pinned" id="f-pinned"> تثبيت</label>
                        <label><input type="checkbox" name="is_encrypted" id="f-encrypt" ${mod === 'passwords' ? 'checked' : ''}> تعتيم</label>
                        <select name="priority" id="f-priority" style="width:auto;"><option value="0">أولوية عادية</option><option value="2">🔥 عاجل</option></select>
                    </div>
                </div>
                <button type="submit" class="tm-btn-main">📥 أرشفة في الذاكرة</button>
            </form>
            <hr style="margin:50px 0; border:0; border-top:5px solid var(--fg);">
            <div id="tm-record-list"></div>
        `;
        $('#tm-stage').html(stageHtml);
        return renderRecords(mod).then(initAutoSave);
    }

    function getPlaceholder(mod) {
        switch(mod) {
            case 'passwords': return 'الخدمة / الحساب';
            case 'characters': return 'الاسم الكامل';
            case 'phones': return 'رقم الهاتف';
            case 'usernames': return 'اسم النظام / التطبيق';
            default: return 'العنوان التعريفي';
        }
    }

    function renderRecords(mod) {
        return new Promise((resolve) => {
            $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, category: mod }, function(res) {
                if (!res.success) return resolve();
                let html = '';
                res.data.records.forEach(r => {
                    const isPrivate = r.is_encrypted == 1 ? 'tm-privacy-blind' : '';
                    let meta = {}; try { if (r.metadata) meta = JSON.parse(r.metadata); } catch(e) {}
                    html += `
                        <div class="tm-card ${r.is_pinned == 1 ? 'tm-pinned' : ''}" id="record-${r.id}">
                            <div class="tm-card-meta">
                                <span>${r.record_date}</span>
                                <div class="tm-card-actions">
                                    <button class="tm-edit-trigger" data-id="${r.id}">✏️</button>
                                    <button class="tm-delete-trigger" data-id="${r.id}">🗑️</button>
                                </div>
                            </div>
                            <h3 class="tm-card-title">${r.record_title}</h3>
                            ${meta.username ? `<div style="margin-bottom:15px;"><code style="background:var(--fg); color:var(--bg); padding:5px 12px; font-size:18px;">ID: ${meta.username}</code> <button class="tm-copy-btn" onclick="navigator.clipboard.writeText('${meta.username}')">Copy</button></div>` : ''}
                            <div class="tm-card-body ${isPrivate}">${r.record_details}</div>
                            ${meta.hint ? `<div style="margin-top:15px; font-size:15px; color:gray;">💡 تلميح: ${meta.hint}</div>` : ''}
                            ${renderSpecializedControls(r, mod)}
                            ${renderHistory(r)}
                        </div>
                    `;
                });
                $('#tm-record-list').html(html);
                resolve();
            });
        });
    }

    function renderSpecializedControls(r, mod) {
        let controls = '<div style="margin-top:20px; display:flex; gap:15px; align-items:center; flex-wrap:wrap;">';
        if (mod === 'phones') controls += `<a href="tel:${r.record_title}" style="background:green; color:white; padding:10px 20px; text-decoration:none; font-weight:900;">📞 اتصال</a>`;
        if (mod === 'financials') {
            controls += `<span style="font-size:24px; font-weight:900; color:${r.amount >= 0 ? 'green' : 'red'};">المبلغ: ${r.amount}</span>`;
            controls += `<button onclick="window.tmSettle(${r.id})" style="background:var(--fg); color:var(--bg); border:0; padding:10px 20px; font-weight:900; cursor:pointer;">✅ تسوية</button>`;
        }
        if (mod === 'characters') controls += `<button onclick="window.tmShowRadar(${r.id})" style="background:var(--fg); color:var(--bg); padding:10px 20px; border:0; font-weight:900; cursor:pointer;">📡 رادار</button>`;
        if (mod === 'passwords') controls += `<button onclick="navigator.clipboard.writeText('${r.record_details}')" style="background:var(--fg); color:var(--bg); border:0; padding:10px 20px; font-weight:900; cursor:pointer;">📋 نسخ</button>`;
        return controls + '</div>';
    }

    function renderHistory(r) {
        if (!r.history || r.history.length === 0) return '';
        return `<div style="margin-top:25px; border-top:2px dashed var(--fg); padding-top:15px;">
            <small style="display:block; font-weight:900; margin-bottom:10px;">📜 التاريخ الأرشيفي:</small>
            ${r.history.map(h => `<div style="font-size:12px; opacity:0.6; margin-bottom:5px;">[${h.created_at}] ${h.record_title}</div>`).join('')}
        </div>`;
    }

    window.tmSettle = function(id) {
        if (confirm('تأكيد تسوية وأرشفة المعاملة؟')) {
            $.post(tm_ajax_obj.ajax_url, { action: 'tm_settle_finance', security: tm_ajax_obj.nonce, id: id }, () => loadModule('financials'));
        }
    };

    function renderFederatedResults(results) {
        let html = '<h2 style="font-weight:900; margin-bottom:40px; border-bottom:10px solid var(--fg);">نتائج البحث الموحدة</h2>';
        if (results.length === 0) html += '<p>لا توجد نتائج.</p>';
        else {
            results.forEach(r => {
                html += `<div class="tm-card" onclick="window.tmLoadRecord(${r.id}, '${r.category}')" style="cursor:pointer;">
                    <small style="font-weight:900; background:var(--fg); color:var(--bg); padding:2px 10px;">${modules[r.category].title}</small>
                    <h3 style="margin-top:10px;">${r.record_title}</h3>
                </div>`;
            });
        }
        $('#tm-stage').html(html);
    }

    window.tmLoadRecord = function(id, cat) {
        currentModule = cat;
        $('.tm-nav-btn').removeClass('active');
        $(`[data-mod="${cat}"]`).addClass('active');
        loadModule(cat).then(() => {
            setTimeout(() => $('.tm-edit-trigger[data-id="'+id+'"]').click(), 200);
        });
    };

    function saveRecord(isManual) {
        const data = new FormData($('#tm-action-form')[0]);
        data.append('action', 'tm_add_record');
        data.append('security', tm_ajax_obj.nonce);
        if (editModeId) data.append('id', editModeId);
        if (!isManual) data.append('is_autosave', 'true');

        $.ajax({
            url: tm_ajax_obj.ajax_url,
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: (res) => {
                if (res.success) {
                    editModeId = res.data.id;
                    if (isManual) {
                        editModeId = null;
                        loadModule(currentModule);
                    } else {
                        $('#tm-module-indicator').text('✅ مزامنة: ' + new Date().toLocaleTimeString());
                    }
                    updateLiveStats();
                }
            }
        });
    }

    function initAutoSave() {
        $(document).off('keyup change', '#tm-action-form input, #tm-action-form textarea');
        $(document).on('keyup change', '#tm-action-form input, #tm-action-form textarea', function() {
            clearTimeout(autoSaveTimer);
            $('#tm-module-indicator').text('⌛ جاري المزامنة...');
            autoSaveTimer = setTimeout(() => saveRecord(false), 4000);
        });
    }

    function updateLiveStats() {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_stats', security: tm_ajax_obj.nonce }, function(res) {
            if (res.success) {
                let total = 0;
                for (let c in res.data.stats) total += parseInt(res.data.stats[c].count);
                $('#tm-stats-counter').text(`المحفوظات: ${total}`);
            }
        });
    }

    window.tmShowRadar = function(charId) {
        $.get(tm_ajax_obj.ajax_url, { action: 'tm_get_records', security: tm_ajax_obj.nonce, related_id: charId }, function(res) {
            if (res.success && res.data.records.length > 0) {
                const last = res.data.records[0];
                $('#tm-sidebar-content').html(`<h4>آخر تفاعل</h4><p>${last.record_date}</p><p>${last.record_title}</p><hr><p>${last.record_details.substring(0, 100)}...</p>`);
                $('#tm-recall-sidebar').fadeIn().css('display', 'flex');
            }
        });
    };
});
