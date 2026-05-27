jQuery(document).ready(function($) {
    // Text-to-Speech
    window.tmSpeak = function(text) {
        if ('speechSynthesis' in window) {
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
    let currentFontSize = 100; // percent
    $(document).on('click', '#tm-zoom-in', function() {
        currentFontSize += 10;
        $('#tm-app').css('font-size', currentFontSize + '%');
    });

    $(document).on('click', '#tm-zoom-out', function() {
        if (currentFontSize > 50) {
            currentFontSize -= 10;
            $('#tm-app').css('font-size', currentFontSize + '%');
        }
    });
});
