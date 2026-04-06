</main>

<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>

<!-- Unified System Modal -->
<div id="gcr-modal-overlay" class="gcr-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="gcr-modal-title">
    <div class="gcr-modal">
        <div id="gcr-modal-icon" class="gcr-modal-icon" style="display:none;"></div>
        <h3 id="gcr-modal-title">Confirm Action</h3>
        <p id="gcr-modal-message">Are you sure you want to proceed?</p>
        <div class="gcr-modal-actions">
            <button class="btn btn-secondary" id="gcr-modal-cancel">Cancel</button>
            <button id="gcr-modal-confirm" class="btn btn-primary">Proceed</button>
        </div>
    </div>
</div>

</div>

<script>
    /**
     * openGcrModal(title, message, onConfirm, options)
     * options: { variant: 'primary'|'success'|'danger', confirmLabel: string, icon: string }
     */
    function openGcrModal(title, message, onConfirm, options) {
        options = options || {};
        
        if (typeof title === 'object' && title !== null) {
            options = title;
            title = options.title || 'Confirm Action';
            message = options.message || 'Are you sure you want to proceed?';
            onConfirm = options.onConfirm || function() {};
        }

        var variant = options.variant || 'primary';
        var confirmLabel = options.confirmLabel || 'Confirm';
        var icon = options.icon || null;

        var overlay = document.getElementById('gcr-modal-overlay');
        var titleEl = document.getElementById('gcr-modal-title');
        var messageEl = document.getElementById('gcr-modal-message');
        var iconEl = document.getElementById('gcr-modal-icon');

        titleEl.textContent = title;
        messageEl.innerHTML = message;

        // Icon
        if (icon) {
            iconEl.style.display = 'flex';
            iconEl.className = 'gcr-modal-icon gcr-modal-icon--' + variant;
            iconEl.innerHTML = '<i data-lucide="' + icon + '" style="width:28px;height:28px;"></i>';
        } else {
            iconEl.style.display = 'none';
        }

        // Confirm button — clone to strip old listeners
        var confirmBtn = document.getElementById('gcr-modal-confirm');
        var newConfirmBtn = confirmBtn.cloneNode(true);
        newConfirmBtn.textContent = confirmLabel;
        newConfirmBtn.className = 'btn btn-' + variant;
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', function () {
            // Loading state
            newConfirmBtn.disabled = true;
            newConfirmBtn.innerHTML = '<i data-lucide="loader-2" class="lucide-spin" style="width:16px;height:16px;"></i> Processing…';
            if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [newConfirmBtn] });
            onConfirm();
            // Modal closes after a short delay so form can submit
            setTimeout(closeGcrModal, 400);
        });

        // Cancel button
        var cancelBtn = document.getElementById('gcr-modal-cancel');
        cancelBtn.onclick = closeGcrModal;

        overlay.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [iconEl] });
        // Focus the confirm button for keyboard accessibility
        setTimeout(function() { newConfirmBtn.focus(); }, 50);
    }

    function closeGcrModal() {
        document.getElementById('gcr-modal-overlay').style.display = 'none';
    }

    // Overlay click — close if clicking the backdrop
    document.getElementById('gcr-modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) closeGcrModal();
    });

    // ESC key dismiss
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var overlay = document.getElementById('gcr-modal-overlay');
            if (overlay && overlay.style.display === 'flex') closeGcrModal();
        }
    });

    window.originalConfirm = window.confirm;
</script>

</body>

</html>