jQuery(document).ready(function($) {
    // Text-to-Speech
    window.tmSpeak = function(text) {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel(); // Stop current speech
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'ar-SA';
            window.speechSynthesis.speak(utterance);
        } else {
            alert('خاصية القراءة الصوتية غير مدعومة في متصفحك.');
        }
    };

    $(document).on('click', '.tm-tts-btn', function() {
        const text = $(this).closest('.tm-record-item').find('.tm-record-details').text();
        tmSpeak(text);
    });

    // Font Resizing (Zoom)
    let currentFontSize = parseInt(localStorage.getItem('tm-font-size')) || 100;
    applyFontSize(currentFontSize);

    $(document).on('click', '#tm-zoom-in', function() {
        currentFontSize += 10;
        applyFontSize(currentFontSize);
    });

    $(document).on('click', '#tm-zoom-out', function() {
        if (currentFontSize > 70) {
            currentFontSize -= 10;
            applyFontSize(currentFontSize);
        }
    });

    function applyFontSize(size) {
        $('#tm-app').css('font-size', size + '%');
        localStorage.setItem('tm-font-size', size);
    }

    // Line Spacing
    let currentLineSpacing = parseFloat(localStorage.getItem('tm-line-spacing')) || 1.6;
    applyLineSpacing(currentLineSpacing);

    $(document).on('click', '#tm-spacing-inc', function() {
        currentLineSpacing += 0.2;
        applyLineSpacing(currentLineSpacing);
    });

    $(document).on('click', '#tm-spacing-dec', function() {
        if (currentLineSpacing > 1.0) {
            currentLineSpacing -= 0.2;
            applyLineSpacing(currentLineSpacing);
        }
    });

    function applyLineSpacing(spacing) {
        $('#tm-app').css('line-height', spacing);
        localStorage.setItem('tm-line-spacing', spacing);
    }
});
