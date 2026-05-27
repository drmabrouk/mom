jQuery(document).ready(function($) {
    let mediaRecorder;
    let audioChunks = [];

    window.initAudioRecorder = function() {
        const recordBtn = $('#tm-record-btn');
        const stopBtn = $('#tm-stop-btn');
        const audioPlayback = $('#tm-audio-playback');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            recordBtn.hide();
            return;
        }

        recordBtn.on('click', async function() {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = event => {
                audioChunks.push(event.data);
            };

            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlob);
                audioPlayback.attr('src', audioUrl).show();

                // Convert to file to be sent via AJAX
                const reader = new FileReader();
                reader.readAsDataURL(audioBlob);
                reader.onloadend = () => {
                    window.recordedAudioBase64 = reader.result;
                    window.recordedAudioBlob = audioBlob;
                };
            };

            mediaRecorder.start();
            recordBtn.hide();
            stopBtn.show();
        });

        stopBtn.on('click', function() {
            mediaRecorder.stop();
            stopBtn.hide();
            recordBtn.show();
        });
    };

    // Image preview
    $(document).on('change', '#tm-image-input', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#tm-image-preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });
});
