<?php
/**
 * includes/notifications.php
 * Drop-in notification bell for the top-bar.
 * Redesigned UI with tabs, grouping, and smart badge.
 */
$_gcr_notif_api = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/modules/ajax/get-notifications.php';
?>
<style>
    /* ── Notification bell ── */
    .gcr-notif-wrapper {
        position: relative;
        font-family: inherit;
    }

    .gcr-notif-btn {
        position: relative;
        width: 38px;
        height: 38px;
        padding: 0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-muted);
        border: 1px solid var(--border-color);
        cursor: pointer;
        transition: background 0.15s, box-shadow 0.15s;
    }

    .gcr-notif-btn:hover {
        background: var(--bg-surface);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    .gcr-notif-btn svg,
    .gcr-notif-btn i {
        width: 18px;
        height: 18px;
        color: var(--text-secondary);
    }

    .gcr-notif-badge {
        display: none;
        position: absolute;
        top: -3px;
        right: -3px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        background: var(--danger);
        color: #fff;
        font-size: 0.6rem;
        font-weight: 700;
        border-radius: 9px;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--bg-surface);
        line-height: 1;
    }

    .gcr-notif-panel {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 420px;
        /* Slightly wider for tabs */
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.2);
        z-index: 9999;
        overflow: hidden;
        animation: gcr-notif-appear 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        transform-origin: top right;
    }

    @keyframes gcr-notif-appear {
        from {
            opacity: 0;
            transform: scale(0.96) translateY(-8px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .gcr-notif-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem 0.5rem;
        background: var(--bg-surface);
    }

    .gcr-notif-header h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .gcr-notif-dismiss-all {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: background 0.15s, color 0.15s;
    }

    .gcr-notif-dismiss-all:hover {
        background: var(--bg-muted);
        color: var(--text-main);
    }

    /* ── Tabs Navigation ── */
    .gcr-notif-tabs {
        display: flex;
        gap: 0.5rem;
        padding: 0.5rem 1.25rem;
        border-bottom: 1px solid var(--border-color);
        overflow-x: auto;
        scrollbar-width: none;
    }

    .gcr-notif-tabs::-webkit-scrollbar {
        display: none;
    }

    .gcr-notif-tab {
        background: none;
        border: none;
        padding: 6px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        border-radius: var(--radius-full);
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
    }

    .gcr-notif-tab:hover {
        background: var(--bg-muted);
        color: var(--text-main);
    }

    .gcr-notif-tab.active {
        background: var(--primary-100);
        color: var(--primary-700);
    }

    .gcr-notif-list {
        max-height: 420px;
        overflow-y: auto;
        padding: 0.5rem 0;
    }

    /* ── Accordion Groups ── */
    .gcr-notif-group {
        margin-bottom: 0.25rem;
    }

    .gcr-notif-group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 1.25rem;
        cursor: pointer;
        user-select: none;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        background: var(--bg-surface);
        transition: background 0.15s;
    }

    .gcr-notif-group-header:hover {
        background: var(--bg-muted);
    }

    .gcr-notif-group-header .lucide-chevron-down {
        transition: transform 0.2s;
        width: 14px;
        height: 14px;
    }

    .gcr-notif-group.collapsed .gcr-notif-group-content {
        display: none;
    }

    .gcr-notif-group.collapsed .lucide-chevron-down {
        transform: rotate(-90deg);
    }

    /* Group color badges */
    .grp-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.65rem;
        color: #fff;
        margin-left: 6px;
    }

    .grp-danger {
        background: var(--danger);
    }

    .grp-warning {
        background: var(--warning);
        color: #000;
    }

    .grp-info {
        background: var(--info, #3b82f6);
    }

    /* ── Items ── */
    .gcr-notif-item {
        position: relative;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.75rem 1.25rem;
        text-decoration: none !important;
        color: var(--text-main);
        background: var(--bg-surface);
        transition: background 0.15s;
        border-bottom: 1px solid var(--border-color);
    }

    .gcr-notif-item:last-child {
        border-bottom: none;
    }

    .gcr-notif-item:hover {
        background: var(--bg-muted);
    }

    .gcr-notif-item.read {
        opacity: 0.6;
    }

    /* Severity left-bar */
    .gcr-notif-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }

    .gcr-notif-item.sev-danger::before {
        background: var(--danger);
    }

    .gcr-notif-item.sev-warning::before {
        background: var(--warning);
    }

    .gcr-notif-item.sev-info::before {
        background: var(--info, #3b82f6);
    }

    .gcr-notif-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .gcr-notif-icon i,
    .gcr-notif-icon svg {
        width: 14px;
        height: 14px;
    }

    .gcr-notif-icon.danger {
        background: var(--danger-light, #fef2f2);
        color: var(--danger);
    }

    .gcr-notif-icon.warning {
        background: var(--warning-light, #fffbeb);
        color: var(--warning-dark, #b45309);
    }

    .gcr-notif-icon.info {
        background: var(--accent-50, #eff6ff);
        color: var(--accent);
    }

    .gcr-notif-body {
        flex: 1;
        min-width: 0;
    }

    .gcr-notif-title-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 3px;
    }

    .gcr-notif-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .gcr-notif-time {
        font-size: 0.7rem;
        color: var(--text-muted);
        white-space: nowrap;
        font-weight: 600;
    }

    .gcr-notif-msg {
        font-size: 0.8rem;
        color: var(--text-secondary);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Quick Actions Overlay on Hover */
    .gcr-notif-actions {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        gap: 4px;
        opacity: 0;
        transition: opacity 0.2s;
        background: var(--bg-muted);
        padding: 4px;
        border-radius: 6px;
        box-shadow: var(--shadow-sm);
    }

    .gcr-notif-item:hover .gcr-notif-actions {
        opacity: 1;
    }

    .gcr-notif-action-btn {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        border-radius: 4px;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }

    .gcr-notif-action-btn:hover {
        background: var(--text-main);
        color: var(--bg-surface);
    }

    .gcr-notif-action-btn svg {
        width: 12px;
        height: 12px;
    }

    .gcr-notif-empty {
        padding: 3rem 1rem;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .gcr-notif-empty svg {
        display: block;
        margin: 0 auto 0.75rem;
        width: 32px;
        height: 32px;
        color: var(--border-color);
    }

    .gcr-notif-footer {
        padding: 0.75rem 1.25rem;
        border-top: 1px solid var(--border-color);
        background: var(--bg-muted);
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-align: center;
    }
</style>

<div class="gcr-notif-wrapper" id="gcr-notif-wrapper">
    <button class="gcr-notif-btn" id="gcr-notif-btn" aria-label="Notifications" type="button">
        <i data-lucide="bell"></i>
        <span class="gcr-notif-badge" id="gcr-notif-badge"></span>
    </button>

    <div class="gcr-notif-panel" id="gcr-notif-panel">
        <div class="gcr-notif-header">
            <h4><i data-lucide="inbox" style="width:18px;height:18px;color:var(--primary);"></i> Alerts</h4>
            <button class="gcr-notif-dismiss-all" id="gcr-notif-dismiss-all" type="button">Dismiss Tab</button>
        </div>

        <div class="gcr-notif-tabs" id="gcr-notif-tabs">
            <button class="gcr-notif-tab active" data-tab="all">All</button>
            <button class="gcr-notif-tab" data-tab="rentals">🚗 Rentals</button>
            <button class="gcr-notif-tab" data-tab="maintenance">🛠 Maintenance</button>
            <button class="gcr-notif-tab" data-tab="compliance">📋 Compliance</button>
            <button class="gcr-notif-tab" data-tab="inventory">📦 Inventory</button>
            <button class="gcr-notif-tab" data-tab="drivers">👤 Drivers</button>
            <button class="gcr-notif-tab" data-tab="procurement">🛒 Procurement</button>
        </div>

        <div class="gcr-notif-list" id="gcr-notif-list">
            <div class="gcr-notif-empty"><i data-lucide="loader-2" class="lucide-spin"></i>Loading…</div>
        </div>
        <div class="gcr-notif-footer" id="gcr-notif-footer">Aggregated live alerts · updates every 60s</div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        var API_URL = <?= json_encode($gcr_notif_api ?? $_gcr_notif_api) ?>;
        var btn = document.getElementById('gcr-notif-btn');
        var panel = document.getElementById('gcr-notif-panel');
        var badge = document.getElementById('gcr-notif-badge');
        var list = document.getElementById('gcr-notif-list');
        var tabs = document.querySelectorAll('.gcr-notif-tab');
        var dismissAll = document.getElementById('gcr-notif-dismiss-all');

        var isOpen = false;
        var currentTab = 'all';
        var allItems = [];
        var dismissed = {}; // Client-side state

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

        /* ── Tabs Logic ── */
        tabs.forEach(t => {
            t.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                tabs.forEach(tx => tx.classList.remove('active'));
                this.classList.add('active');
                currentTab = this.getAttribute('data-tab');
                renderUI();
            });
        });

        /* ── Dismiss Current Tab ── */
        dismissAll.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var itemsToDismiss = currentTab === 'all' ? allItems : allItems.filter(i => i.category === currentTab);

            itemsToDismiss.forEach(function (i) { dismissed[i.id] = true; });
            renderUI();
            updateBadge();
        });

        /* ── Fetch Data ── */
        function fetchAndRender() {
            fetch(API_URL)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { renderError(); return; }
                    allItems = data.notifications || [];
                    // Badge is now smart! Only critical/unread (backend provided critical_count)
                    // But since we have client dismiss, we filter manually.
                    updateBadge();
                    renderUI();
                })
                .catch(renderError);
        }

        /* ── Silent Background Fetch ── */
        function silentFetch() {
            fetch(API_URL).then(r => r.json()).then(data => {
                if (data.success) { allItems = data.notifications || []; updateBadge(); }
            }).catch(() => { });
        }

        function updateBadge() {
            var count = allItems.filter(n => n.severity === 'danger' && !dismissed[n.id]).length;
            if (count > 0) {
                badge.style.display = 'flex';
                badge.textContent = count > 99 ? '99+' : count;
            } else {
                badge.style.display = 'none';
            }
        }

        /* ── Accordion Toggle ── */
        document.addEventListener('click', function (e) {
            var header = e.target.closest('.gcr-notif-group-header');
            if (header) {
                e.stopPropagation();
                var group = header.closest('.gcr-notif-group');
                group.classList.toggle('collapsed');
            }

            var actionBtn = e.target.closest('.gcr-notif-action-btn');
            if (actionBtn) {
                e.preventDefault();
                e.stopPropagation();
                var itemEl = actionBtn.closest('.gcr-notif-item');
                var id = itemEl.getAttribute('data-id');
                var action = actionBtn.getAttribute('data-action');
                if (action === 'dismiss') {
                    dismissed[id] = true;
                    renderUI();
                    updateBadge();
                } else if (action === 'read') {
                    itemEl.classList.add('read');
                }
            }
        });

        /* ── Render UI based on Tab & Groups ── */
        function renderUI() {
            var filtered = allItems.filter(n => !dismissed[n.id]);

            if (currentTab !== 'all') {
                filtered = filtered.filter(n => n.category === currentTab);
            }

            if (filtered.length === 0) {
                list.innerHTML = '<div class="gcr-notif-empty"><i data-lucide="check-circle"></i><br>No alerts in this category.</div>';
                if (window.lucide) window.lucide.createIcons();
                return;
            }

            var groups = { danger: [], warning: [], info: [] };
            filtered.forEach(n => {
                if (groups[n.severity]) groups[n.severity].push(n);
            });

            var html = '';
            var labels = { danger: 'Critical', warning: 'Warning', info: 'Info' };

            ['danger', 'warning', 'info'].forEach(sev => {
                if (groups[sev].length === 0) return;
                var isCollapsed = (sev === 'info') ? 'collapsed' : '';
                var items = groups[sev];

                // Limit visible items inside group
                var displayItems = items.slice(0, 7);
                var hiddenCount = items.length - 7;

                html += `<div class="gcr-notif-group ${isCollapsed}">
                <div class="gcr-notif-group-header">
                    <span style="display:flex;align-items:center;">
                        ${labels[sev]} <span class="grp-badge grp-${sev}">${items.length}</span>
                    </span>
                    <i data-lucide="chevron-down"></i>
                </div>
                <div class="gcr-notif-group-content">`;

                displayItems.forEach(n => {
                    var timeStr = timeAgo(n.time);
                    html += `
                    <a href="${esc(n.href || '#')}" class="gcr-notif-item sev-${esc(n.severity)}" data-id="${esc(n.id)}">
                        <div class="gcr-notif-icon ${esc(n.severity)}"><i data-lucide="${esc(n.icon)}"></i></div>
                        <div class="gcr-notif-body">
                            <div class="gcr-notif-title-row">
                                <span class="gcr-notif-title">${esc(n.title)}</span>
                                <span class="gcr-notif-time">${timeStr}</span>
                            </div>
                            <div class="gcr-notif-msg">${esc(n.body)}</div>
                        </div>
                        <div class="gcr-notif-actions">
                            <button class="gcr-notif-action-btn" data-action="read" title="Mark as read"><i data-lucide="check"></i></button>
                            <button class="gcr-notif-action-btn" data-action="dismiss" title="Dismiss"><i data-lucide="x"></i></button>
                        </div>
                    </a>`;
                });

                if (hiddenCount > 0) {
                    html += `<div style="text-align:center; padding: 10px; font-size:0.75rem; color:var(--text-muted); border-bottom:1px solid var(--border-color); background:var(--bg-surface);">+ ${hiddenCount} more alerts in this group</div>`;
                }

                html += `</div></div>`;
            });

            list.innerHTML = html;
            if (window.lucide) window.lucide.createIcons({ nodes: list.querySelectorAll('[data-lucide]') });
        }

        function renderError() {
            list.innerHTML = '<div class="gcr-notif-empty">Failed to load alerts.</div>';
        }

        /* ── Human Readable Time (No Negative Seconds!) ── */
        function timeAgo(dateStr) {
            if (!dateStr) return 'Just now';
            var d = new Date(dateStr);
            if (isNaN(d)) return '';
            var s = Math.round((d.getTime() - Date.now()) / 1000);

            var isFuture = s > 0;
            s = Math.abs(s);

            var res = '';
            if (s < 60) res = 'Just now';
            else if (s < 3600) res = Math.floor(s / 60) + 'm';
            else if (s < 86400) res = Math.floor(s / 3600) + 'h';
            else if (s < 2592000) res = Math.floor(s / 86400) + 'd';
            else res = Math.floor(s / 2592000) + 'mo';

            if (res === 'Just now') return res;
            return isFuture ? 'in ' + res : res + ' ago';
        }

        function esc(text) {
            var d = document.createElement('div');
            d.textContent = String(text || '');
            return d.innerHTML;
        }

        silentFetch();
        setInterval(silentFetch, 60000);
    })();
</script>