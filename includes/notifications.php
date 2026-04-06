<?php
/**
 * includes/notifications.php
 * Drop-in notification bell for the top-bar.
 * Works from any page depth — BASE_URL must be defined before including.
 */
$_gcr_notif_api = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/modules/ajax/get-notifications.php';
?>
<style>
/* ── Notification bell ── */
.gcr-notif-wrapper { position: relative; }

.gcr-notif-btn {
    position: relative;
    width: 38px; height: 38px;
    padding: 0;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
}
.gcr-notif-btn:hover { background: var(--bg-surface); box-shadow: 0 0 0 3px var(--accent-light); }
.gcr-notif-btn svg, .gcr-notif-btn i { width: 18px; height: 18px; color: var(--text-secondary); }

.gcr-notif-badge {
    display: none;
    position: absolute; top: -3px; right: -3px;
    min-width: 18px; height: 18px;
    padding: 0 4px;
    background: var(--danger);
    color: #fff;
    font-size: 0.6rem; font-weight: 700;
    border-radius: 9px;
    align-items: center; justify-content: center;
    border: 2px solid var(--bg-surface);
    line-height: 1;
}

.gcr-notif-panel {
    display: none;
    position: absolute;
    top: calc(100% + 8px); right: 0;
    width: 360px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    z-index: 999;
    overflow: hidden;
    animation: gcr-notif-appear 0.2s cubic-bezier(0.16,1,0.3,1);
    transform-origin: top right;
}
@keyframes gcr-notif-appear {
    from { opacity: 0; transform: scale(0.96) translateY(-8px); }
    to   { opacity: 1; transform: scale(1)    translateY(0); }
}

.gcr-notif-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-muted);
}
.gcr-notif-header h4 { margin: 0; font-size: 0.875rem; font-weight: 700; }
.gcr-notif-dismiss-all {
    background: none; border: none;
    color: var(--accent); font-size: 0.75rem; font-weight: 600;
    cursor: pointer; padding: 0;
}
.gcr-notif-dismiss-all:hover { text-decoration: underline; }

.gcr-notif-list { max-height: 380px; overflow-y: auto; padding: 0; }

.gcr-notif-item {
    display: flex; gap: 0.75rem; align-items: flex-start;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    text-decoration: none !important;
    color: var(--text-main);
    transition: background 0.12s;
}
.gcr-notif-item:last-child { border-bottom: none; }
.gcr-notif-item:hover { background: var(--bg-muted); }

/* Severity left-bar */
.gcr-notif-item.sev-danger  { border-left: 3px solid var(--danger);  }
.gcr-notif-item.sev-warning { border-left: 3px solid var(--warning); }
.gcr-notif-item.sev-info    { border-left: 3px solid var(--accent);  }

.gcr-notif-icon {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.gcr-notif-icon i, .gcr-notif-icon svg { width: 15px; height: 15px; }
.gcr-notif-icon.danger  { background: var(--danger-light,#fef2f2);  color: var(--danger); }
.gcr-notif-icon.warning { background: var(--warning-light,#fffbeb); color: var(--warning); }
.gcr-notif-icon.info    { background: var(--accent-50,#eff6ff);     color: var(--accent); }
.gcr-notif-icon.success { background: var(--success-light,#f0fdf4); color: var(--success); }

.gcr-notif-body { flex: 1; min-width: 0; }
.gcr-notif-title-row {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 2px;
}
.gcr-notif-title { font-size: 0.8125rem; font-weight: 600; }
.gcr-notif-time  { font-size: 0.6875rem; color: var(--text-muted); white-space: nowrap; }
.gcr-notif-msg   {
    font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

.gcr-notif-empty {
    padding: 2.5rem 1rem;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
}
.gcr-notif-empty i, .gcr-notif-empty svg { display: block; margin: 0 auto 0.5rem; width: 28px; height: 28px; color: var(--success); }

.gcr-notif-footer {
    padding: 0.5rem 1rem;
    border-top: 1px solid var(--border-color);
    background: var(--bg-muted);
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
}

/* Severity group dividers */
.gcr-notif-group-label {
    padding: 0.35rem 1rem;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
    background: var(--bg-muted);
    border-bottom: 1px solid var(--border-color);
}
</style>

<div class="gcr-notif-wrapper" id="gcr-notif-wrapper">
    <button class="gcr-notif-btn" id="gcr-notif-btn" aria-label="Notifications" type="button">
        <i data-lucide="bell"></i>
        <span class="gcr-notif-badge" id="gcr-notif-badge"></span>
    </button>

    <div class="gcr-notif-panel" id="gcr-notif-panel">
        <div class="gcr-notif-header">
            <h4>Pending Alerts</h4>
            <button class="gcr-notif-dismiss-all" id="gcr-notif-dismiss-all" type="button">Dismiss all</button>
        </div>
        <div class="gcr-notif-list" id="gcr-notif-list">
            <div class="gcr-notif-empty"><i data-lucide="loader-2" class="lucide-spin"></i>Loading…</div>
        </div>
        <div class="gcr-notif-footer" id="gcr-notif-footer">Live system alerts · updates every 60s</div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var API_URL  = <?= json_encode($gcr_notif_api ?? $_gcr_notif_api) ?>;
    var btn      = document.getElementById('gcr-notif-btn');
    var panel    = document.getElementById('gcr-notif-panel');
    var badge    = document.getElementById('gcr-notif-badge');
    var list     = document.getElementById('gcr-notif-list');
    var footer   = document.getElementById('gcr-notif-footer');
    var dismissAll = document.getElementById('gcr-notif-dismiss-all');

    var isOpen   = false;
    var dismissed = {};         // id → true: client-side dismissed
    var POLL_MS  = 60000;       // re-fetch every 60 s

    /* ── severity → CSS class ── */
    var SEV_CLASS = { danger: 'danger', warning: 'warning', info: 'info', success: 'success' };

    /* ── toggle panel ── */
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        isOpen = !isOpen;
        panel.style.display = isOpen ? 'block' : 'none';
        if (isOpen) fetchAndRender();
    });

    /* ── close on outside click ── */
    document.addEventListener('click', function (e) {
        if (isOpen && !panel.contains(e.target) && !btn.contains(e.target)) {
            isOpen = false;
            panel.style.display = 'none';
        }
    });

    /* ── dismiss all (client-side) ── */
    dismissAll.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        // mark all currently shown as dismissed
        list.querySelectorAll('.gcr-notif-item[data-id]').forEach(function (el) {
            dismissed[el.getAttribute('data-id')] = true;
        });
        renderEmpty();
        updateBadge(0);
    });

    /* ── fetch from API ── */
    function fetchAndRender() {
        fetch(API_URL)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) { renderError(); return; }
                var items = (data.notifications || []).filter(function (n) {
                    return !dismissed[n.id];
                });
                renderList(items);
                updateBadge(items.length);
            })
            .catch(renderError);
    }

    /* ── silent badge refresh ── */
    function silentFetch() {
        fetch(API_URL)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                var count = (data.notifications || []).filter(function (n) {
                    return !dismissed[n.id];
                }).length;
                updateBadge(count);
            })
            .catch(function () {});
    }

    /* ── render helpers ── */
    function renderList(items) {
        if (!items || items.length === 0) { renderEmpty(); return; }

        var html = '';
        var lastSev = null;

        /* Group label thresholds */
        var labels = { danger: '🔴 Critical', warning: '⚠️ Warnings', info: '🔵 Info' };

        items.forEach(function (item) {
            var sev   = item.severity || 'info';
            var color = SEV_CLASS[sev] || 'info';
            var link  = esc(item.href || '#');
            var icon  = esc(item.icon || 'bell');
            var time  = item.time ? timeAgo(new Date(item.time)) : '';

            /* Group separator */
            if (sev !== lastSev) {
                html += '<div class="gcr-notif-group-label">' + (labels[sev] || sev) + '</div>';
                lastSev = sev;
            }

            html += '<a href="' + link + '" class="gcr-notif-item sev-' + color + '" data-id="' + esc(String(item.id)) + '">'
                + '<div class="gcr-notif-icon ' + color + '"><i data-lucide="' + icon + '"></i></div>'
                + '<div class="gcr-notif-body">'
                + '<div class="gcr-notif-title-row">'
                + '<span class="gcr-notif-title">' + esc(item.title || '') + '</span>'
                + (time ? '<span class="gcr-notif-time">' + time + '</span>' : '')
                + '</div>'
                + '<div class="gcr-notif-msg">' + esc(item.body || '') + '</div>'
                + '</div>'
                + '</a>';
        });

        list.innerHTML = html;
        footer.textContent = items.length + ' pending alert' + (items.length !== 1 ? 's' : '') + ' · updates every 60s';

        if (window.lucide) window.lucide.createIcons({ nodes: list.querySelectorAll('[data-lucide]') });
    }

    function renderEmpty() {
        list.innerHTML = '<div class="gcr-notif-empty"><i data-lucide="check-circle"></i>No pending actions.</div>';
        footer.textContent = 'All clear · updates every 60s';
        if (window.lucide) window.lucide.createIcons({ nodes: list.querySelectorAll('[data-lucide]') });
    }

    function renderError() {
        list.innerHTML = '<div class="gcr-notif-empty">Failed to load alerts.</div>';
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.style.display = 'flex';
            badge.textContent   = count > 99 ? '99+' : count;
        } else {
            badge.style.display = 'none';
        }
    }

    /* ── utilities ── */
    function esc(text) {
        var d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    function timeAgo(date) {
        if (!(date instanceof Date) || isNaN(date)) return '';
        var s = Math.floor((Date.now() - date) / 1000);
        if (s < 60)       return s + 's';
        if (s < 3600)     return Math.floor(s / 60)    + 'm';
        if (s < 86400)    return Math.floor(s / 3600)  + 'h';
        if (s < 2592000)  return Math.floor(s / 86400) + 'd';
        return Math.floor(s / 2592000) + 'mo';
    }

    /* ── initial silent badge load + 60s poll ── */
    silentFetch();
    setInterval(silentFetch, POLL_MS);
})();
</script>
