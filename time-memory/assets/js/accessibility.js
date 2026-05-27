jQuery(document).ready(function($) {
    // Text-to-Speech
    window.tmSpeak = function(text) {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'ar-SA';
            window.speechSynthesis.speak(utterance);
        } else {
            alert('خاصية القراءة الصوتية غير مدعومة.');
        }
    };

    $(document).on('click', '.tm-tts-btn', function() {
        const text = $(this).closest('.tm-card').find('.tm-card-body').text();
        tmSpeak(text);
    });

    // Font Resizing
    let currentFontSize = parseInt(localStorage.getItem('tm-font-size')) || 100;
    const applyFS = (s) => { $('#tm-app').css('font-size', s + '%'); localStorage.setItem('tm-font-size', s); };
    applyFS(currentFontSize);

    $(document).on('click', '#tm-zoom-in', () => applyFS(currentFontSize += 10));
    $(document).on('click', '#tm-zoom-out', () => { if (currentFontSize > 60) applyFS(currentFontSize -= 10); });
});
