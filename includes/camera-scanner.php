<!-- includes/camera-scanner.php -->
<style>
    #cameraModal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
    }

    .camera-box {
        background: #fff;
        border-radius: 20px;
        padding: 28px;
        width: 100%;
        max-width: 520px;
        margin: 16px;
        position: relative;
        box-shadow: 0 30px 60px -12px rgba(0,0,0,0.35);
        animation: camSlideIn 0.2s ease-out;
    }

    @keyframes camSlideIn {
        from { opacity: 0; transform: scale(0.95) translateY(10px); }
        to   { opacity: 1; transform: scale(1)    translateY(0); }
    }

    .camera-close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: #f1f5f9;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #64748b;
        transition: background 0.15s, color 0.15s;
        flex-shrink: 0;
    }
    .camera-close:hover { background: #fee2e2; color: #dc2626; }

    .camera-viewport {
        background: #0f172a;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
        width: 100%;
        padding-top: 56.25%; /* 16:9 */
    }

    .camera-viewport video,
    .camera-viewport canvas {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100%; height: 100%;
        object-fit: cover;
    }

    .camera-loader {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #fff;
        font-size: 0.875rem;
        font-weight: 600;
        z-index: 10;
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
    }

    .camera-loader::before {
        content: '';
        display: inline-block;
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
        flex-shrink: 0;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .camera-controls {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .cam-preview-container {
        display: none;
        align-items: center;
        gap: 14px;
        margin-top: 14px;
        padding: 12px 16px;
        border-radius: 12px;
        background: #f0fdf4;
        border: 1px dashed #22c55e;
        width: fit-content;
        animation: camFadeIn 0.3s ease-out forwards;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    @keyframes camFadeIn {
        from { opacity: 0; transform: translateY(-5px) scale(0.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .cam-preview-thumb {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        flex-shrink: 0;
    }

    .cam-success-badge {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .cam-success-badge-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        font-weight: 700;
        color: #166534;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .cam-success-badge-text {
        font-size: 0.75rem;
        font-weight: 500;
        color: #15803d;
    }

    .cam-error-msg {
        display: none;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #dc2626;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        padding: 10px 14px;
        margin-top: 8px;
    }
</style>

<div id="cameraModal">
    <div class="camera-box" role="dialog" aria-modal="true" aria-labelledby="cameraTitle">
        <button type="button" onclick="closeCamera()" class="camera-close" title="Close (Esc)" aria-label="Close camera">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
        </button>

        <h3 id="cameraTitle"
            style="font-size:1.125rem;font-weight:900;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;color:#0f172a;padding-right:40px;">
            Take Photo
        </h3>
        <p id="cameraSubtitle"
            style="font-size:0.72rem;color:#64748b;font-weight:600;margin:0 0 18px;text-transform:uppercase;letter-spacing:.05em;">
            Position subject and press Capture
        </p>

        <div class="camera-viewport">
            <!-- Video feed (live camera) -->
            <video id="cameraVideo" autoplay playsinline muted
                style="display:block;"></video>
            <!-- Canvas preview (frozen capture) -->
            <canvas id="cameraCanvas"
                style="display:none;" width="1280" height="720"></canvas>
            <!-- Loading overlay -->
            <div id="cameraLoader" class="camera-loader">Initializing Camera…</div>
            <!-- Error overlay -->
            <div id="cameraErrorOverlay"
                style="display:none;position:absolute;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;padding:20px;text-align:center;color:#fff;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
                </svg>
                <p id="cameraErrorText" style="font-size:.875rem;font-weight:600;margin:0;"></p>
            </div>
        </div>

        <div class="camera-controls">
            <!-- Capture: shown when live stream is running -->
            <button type="button" id="btnCapture" onclick="capturePhoto()" class="btn btn-primary"
                style="padding:10px 22px;border-radius:12px;display:none;align-items:center;gap:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
                    <circle cx="12" cy="13" r="3"/>
                </svg>
                Capture
            </button>
            <!-- Retake: shown after capture -->
            <button type="button" id="btnRetake" onclick="retakePhoto()" class="btn btn-secondary"
                style="padding:10px 22px;border-radius:12px;display:none;align-items:center;gap:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                    <path d="M3 3v5h5"/>
                </svg>
                Retake
            </button>
            <!-- Confirm: shown after capture -->
            <button type="button" id="btnConfirm" onclick="confirmPhoto()" class="btn"
                style="padding:10px 22px;border-radius:12px;display:none;align-items:center;gap:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:.75rem;background:#059669;color:#fff;border-color:#059669;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                Use Photo
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    // ─── State ────────────────────────────────────────────────────────────────
    let _inputName  = '';
    let _stream     = null;
    let _blob       = null;
    let _objectURL  = null;   // for preview thumbnail

    // ─── Element refs ─────────────────────────────────────────────────────────
    const modal         = document.getElementById('cameraModal');
    const video         = document.getElementById('cameraVideo');
    const canvas        = document.getElementById('cameraCanvas');
    const loader        = document.getElementById('cameraLoader');
    const errOverlay    = document.getElementById('cameraErrorOverlay');
    const errText       = document.getElementById('cameraErrorText');
    const btnCapture    = document.getElementById('btnCapture');
    const btnRetake     = document.getElementById('btnRetake');
    const btnConfirm    = document.getElementById('btnConfirm');
    const titleEl       = document.getElementById('cameraTitle');
    const subtitleEl    = document.getElementById('cameraSubtitle');

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function _stopStream() {
        if (_stream) {
            _stream.getTracks().forEach(t => t.stop());
            _stream = null;
        }
        video.srcObject = null;
    }

    function _resetUI() {
        video.style.display   = 'block';
        canvas.style.display  = 'none';
        loader.style.display  = 'flex';
        errOverlay.style.display = 'none';
        btnCapture.style.display = 'none';
        btnRetake.style.display  = 'none';
        btnConfirm.style.display = 'none';
        _blob = null;
        if (_objectURL) { URL.revokeObjectURL(_objectURL); _objectURL = null; }
    }

    function _showError(msg) {
        loader.style.display    = 'none';
        errText.textContent     = msg;
        errOverlay.style.display = 'flex';
        btnCapture.style.display = 'none';
        btnRetake.style.display  = 'none';
        btnConfirm.style.display = 'none';
    }

    // ─── Public API ───────────────────────────────────────────────────────────
    window.openCamera = async function (inputName, title, subtitle) {
        _inputName = inputName;
        titleEl.textContent    = title    || 'Take Photo';
        subtitleEl.textContent = subtitle || 'Position subject and press Capture';

        _resetUI();
        modal.style.display = 'flex';

        // Check API availability
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            _showError('Camera API not supported. Use a modern browser over HTTPS or localhost.');
            return;
        }

        // Try environment (back) camera first, fall back to any camera
        const constraints = [
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } } },
            { video: { width: { ideal: 1280 }, height: { ideal: 720 } } },
            { video: true }
        ];

        let started = false;
        for (const c of constraints) {
            try {
                _stream = await navigator.mediaDevices.getUserMedia(c);
                started = true;
                break;
            } catch (_) { /* try next */ }
        }

        if (!started) {
            _showError('Could not access a camera. Please check permissions and try again.');
            return;
        }

        video.srcObject = _stream;

        video.onloadedmetadata = () => {
            loader.style.display     = 'none';
            btnCapture.style.display = 'flex';
        };

        // Safety: if metadata fires quickly it may have already fired
        if (video.readyState >= 1) {
            loader.style.display     = 'none';
            btnCapture.style.display = 'flex';
        }
    };

    window.closeCamera = function () {
        _stopStream();
        _resetUI();
        modal.style.display = 'none';
    };

    window.capturePhoto = function () {
        if (!_stream) return;

        // Draw current video frame onto canvas
        canvas.width  = video.videoWidth  || 1280;
        canvas.height = video.videoHeight || 720;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        // Switch display: freeze frame on canvas, hide live video
        video.style.display  = 'none';
        canvas.style.display = 'block';

        // Stop stream to turn off camera light
        _stopStream();

        // Button states
        btnCapture.style.display = 'none';
        btnRetake.style.display  = 'flex';
        btnConfirm.style.display = 'flex';

        // Build blob
        canvas.toBlob(blob => {
            _blob = blob;
            if (_objectURL) URL.revokeObjectURL(_objectURL);
            _objectURL = URL.createObjectURL(blob);
        }, 'image/jpeg', 0.92);
    };

    window.retakePhoto = function () {
        // Re-open stream to same input
        const inp = _inputName;
        const ttl = titleEl.textContent;
        const sub = subtitleEl.textContent;
        _resetUI();        // clears blob + hides canvas
        window.openCamera(inp, ttl, sub);
    };

    window.confirmPhoto = function () {
        if (!_blob) return;

        const filename = _inputName + '_cam_' + Date.now() + '.jpg';
        const file = new File([_blob], filename, { type: 'image/jpeg', lastModified: Date.now() });

        // Inject into the file input
        const inputEl = document.querySelector(`input[name="${_inputName}"]`);
        if (inputEl) {
            try {
                const dt = new DataTransfer();
                dt.items.add(file);
                inputEl.files = dt.files;

                // Trigger change event so any listeners fire
                inputEl.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                console.warn('DataTransfer not supported:', e);
            }

            // ── Visual feedback ──────────────────────────────────────────────
            // Find the wrapper (flex row containing the input + camera button)
            const wrapper = inputEl.closest('div');

            // Ensure container exists
            let container = document.getElementById('cam_container_' + _inputName);
            let thumb = document.getElementById('cam_thumb_' + _inputName);

            if (!container) {
                container = document.createElement('div');
                container.id = 'cam_container_' + _inputName;
                container.className = 'cam-preview-container';

                thumb = document.createElement('img');
                thumb.id = 'cam_thumb_' + _inputName;
                thumb.className = 'cam-preview-thumb';
                thumb.alt = 'Captured photo';

                let badge = document.createElement('div');
                badge.className = 'cam-success-badge';
                badge.innerHTML = `
                    <div class="cam-success-badge-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                        Photo Captured
                    </div>
                    <div class="cam-success-badge-text">Save form to confirm upload.</div>
                `;

                container.appendChild(thumb);
                container.appendChild(badge);

                // Insert container after wrapper
                wrapper.parentElement.insertBefore(container, wrapper.nextSibling);
            }

            if (_objectURL) { thumb.src = _objectURL; }
            container.style.display = 'flex';
        }

        closeCamera();
    };

    // ─── Close on Escape key ──────────────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display === 'flex') closeCamera();
    });

    // ─── Close on backdrop click ──────────────────────────────────────────────
    modal.addEventListener('click', e => {
        if (e.target === modal) closeCamera();
    });

    // ─── Stop camera if user navigates away ───────────────────────────────────
    window.addEventListener('pagehide', _stopStream);
    window.addEventListener('beforeunload', _stopStream);
})();
</script>