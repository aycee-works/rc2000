/* assets/js/main.js - shared behavior loaded on every admin page */

(function () {
    'use strict';
    var menuToggle = document.querySelector('[data-portal-menu]');
    if (menuToggle) {
        menuToggle.hidden = false;
        menuToggle.addEventListener('click', function () {
            var expanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', String(!expanded));
        });
        menuToggle.closest('.sidebar').addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menuToggle.offsetWidth && menuToggle.getAttribute('aria-expanded') === 'true') {
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.focus();
            }
        });
    }

    // ---- Helpers other scripts can reuse (window.RC) ----
    var RC = window.RC = window.RC || {};

    // The desktop navigation stays tucked away until hovered, focused, or pinned open.
    var adminSidebar = document.querySelector('body.admin-body:not(.superadmin-body) .sidebar');
    if (adminSidebar) {
        var sidebarToggle = adminSidebar.querySelector('[data-sidebar-toggle]');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                var pinned = adminSidebar.classList.toggle('is-pinned-open');
                sidebarToggle.setAttribute('aria-expanded', pinned ? 'true' : 'false');
                sidebarToggle.setAttribute('aria-label', pinned ? 'Close admin navigation' : 'Open admin navigation');
                if (!pinned) { sidebarToggle.blur(); }
            });
            document.addEventListener('click', function (event) {
                if (!adminSidebar.contains(event.target) && adminSidebar.classList.contains('is-pinned-open')) {
                    adminSidebar.classList.remove('is-pinned-open');
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                    sidebarToggle.setAttribute('aria-label', 'Open admin navigation');
                    if (adminSidebar.contains(document.activeElement)) { document.activeElement.blur(); }
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && adminSidebar.classList.contains('is-pinned-open')) {
                    adminSidebar.classList.remove('is-pinned-open');
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                    sidebarToggle.setAttribute('aria-label', 'Open admin navigation');
                    sidebarToggle.blur();
                }
            });
        }
    }

    RC.csrfToken = function () {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    };

    RC.baseUrl = function () {
        var m = document.querySelector('meta[name="app-base"]');
        return m ? m.getAttribute('content') : '';
    };

    RC.url = function (path) {
        var portalBase = document.querySelector('meta[name="portal-base"]');
        var portalName = document.querySelector('meta[name="portal-name"]');
        path = path.replace(/^\//, '');
        if (portalBase && portalName) {
            var prefix = portalName.getAttribute('content');
            if (path === prefix || path.indexOf(prefix + '/') === 0) {
                return portalBase.getAttribute('content') + path.slice(prefix.length);
            }
        }
        return RC.baseUrl() + '/' + path;
    };

    /**
     * POST/GET to a JSON endpoint with the CSRF header attached.
     * Returns a Promise that resolves to the parsed JSON ({success, message, data}).
     * Usage: RC.api('admin/api/dashboard.php').then(...)
     */
    RC.api = function (path, options) {
        options = options || {};
        var headers = { 'X-CSRF-Token': RC.csrfToken(), 'Accept': 'application/json' };
        var init = { method: options.method || 'GET', headers: headers, credentials: 'same-origin' };
        if (options.body instanceof FormData) {
            init.body = options.body;
        } else if (options.body) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }
        return fetch(RC.url(path), init).then(function (res) {
            if (res.status === 401) {
                var portalBase = document.querySelector('meta[name="portal-base"]');
                window.location.href = portalBase
                    ? portalBase.getAttribute('content') + '/login.php'
                    : RC.url('admin/login.php');
                return Promise.reject(new Error('Not authenticated'));
            }
            return res.json().catch(function () {
                return { success: false, message: 'Unexpected server response.', data: null };
            });
        });
    };

    /** Current page URL without ?id= and #hash (used when a review popup closes or after a save). */
    RC.reviewClosedUrl = function () {
        var u = new URL(window.location.href);
        u.searchParams.delete('id');
        u.hash = '';
        return u.toString();
    };

    // ---- Dismiss flash messages; auto-hide success ones ----
    document.addEventListener('click', function (e) {
        var close = e.target.closest('.flash-close');
        if (close) {
            close.closest('.flash').remove();
        }
    });
    document.querySelectorAll('.flash-success').forEach(function (el) {
        setTimeout(function () { el.remove(); }, 6000);
    });

    // ---- "Forgot password?" notice on the login page ----
    var forgotLink = document.getElementById('forgot-link');
    var forgotNotice = document.getElementById('forgot-notice');
    if (forgotLink && forgotNotice) {
        forgotLink.addEventListener('click', function (e) {
            e.preventDefault();
            forgotNotice.hidden = !forgotNotice.hidden;
        });
    }

    // ---- Portal notification bell ----
    var notificationRoot = document.querySelector('[data-notifications]');
    if (notificationRoot) {
        var notificationButton = notificationRoot.querySelector('[data-notification-toggle]');
        var notificationPanel = notificationRoot.querySelector('[data-notification-panel]');
        var notificationList = notificationRoot.querySelector('[data-notification-list]');
        var notificationEmpty = notificationRoot.querySelector('[data-notification-empty]');
        var notificationCount = notificationRoot.querySelector('[data-notification-count]');
        var notificationStatus = notificationRoot.querySelector('[data-notification-status]');
        var markReadButton = notificationRoot.querySelector('[data-notification-read]');
        var endpoint = notificationRoot.getAttribute('data-endpoint');
        var storageKey = 'rc2000-notifications-v1:' + notificationRoot.getAttribute('data-role') + ':' + notificationRoot.getAttribute('data-user-id');
        var lastSeen = null;

        try { lastSeen = window.localStorage.getItem(storageKey); } catch (e) {}
        if (lastSeen && Number.isNaN(Date.parse(lastSeen))) lastSeen = null;

        function relativeTime(value) {
            var date = new Date(value);
            if (Number.isNaN(date.getTime())) return '';
            var seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hr ago';
            return Math.floor(seconds / 86400) + ' days ago';
        }

        function setLastSeen(value) {
            lastSeen = value;
            try { window.localStorage.setItem(storageKey, value); } catch (e) {}
        }

        function renderNotifications(payload) {
            var unread = lastSeen ? Number(payload.unread_count || 0) : 0;
            notificationCount.hidden = unread < 1;
            notificationCount.textContent = unread > 99 ? '99+' : String(unread);
            notificationButton.setAttribute('aria-label', unread ? 'Notifications, ' + unread + ' unread' : 'Notifications');
            notificationList.replaceChildren();

            (payload.items || []).forEach(function (item) {
                var li = document.createElement('li');
                var link = document.createElement('a');
                var isUnread = lastSeen && Date.parse(item.created_at) > Date.parse(lastSeen);
                link.className = 'notification-item' + (isUnread ? ' is-unread' : '');
                link.href = item.url;

                var title = document.createElement('span');
                title.className = 'notification-item-title';
                title.textContent = item.title;
                var message = document.createElement('span');
                message.className = 'notification-item-message';
                message.textContent = item.message;
                var time = document.createElement('time');
                time.dateTime = item.created_at;
                time.textContent = relativeTime(item.created_at);

                link.append(title, message, time);
                li.appendChild(link);
                notificationList.appendChild(li);
            });

            var hasItems = (payload.items || []).length > 0;
            notificationEmpty.hidden = hasItems;
            notificationStatus.hidden = true;
        }

        function loadNotifications(markRead) {
            var requestUrl = endpoint;
            if (lastSeen) requestUrl += '?since=' + encodeURIComponent(lastSeen);
            return fetch(requestUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok || !body.success || !body.data) throw new Error(body.message || 'Could not load notifications.');
                    return body.data;
                });
            }).then(function (payload) {
                if (!lastSeen) setLastSeen(payload.server_time);
                else if (markRead) {
                    setLastSeen(payload.server_time);
                    payload.unread_count = 0;
                }
                renderNotifications(payload);
            }).catch(function () {
                notificationStatus.textContent = 'Notifications are temporarily unavailable.';
                notificationStatus.hidden = false;
            });
        }

        notificationButton.addEventListener('click', function () {
            var open = notificationButton.getAttribute('aria-expanded') === 'true';
            notificationButton.setAttribute('aria-expanded', String(!open));
            notificationPanel.hidden = open;
            if (!open) loadNotifications(false);
        });

        markReadButton.addEventListener('click', function () {
            loadNotifications(true);
        });

        document.addEventListener('click', function (event) {
            if (!notificationRoot.contains(event.target)) {
                notificationButton.setAttribute('aria-expanded', 'false');
                notificationPanel.hidden = true;
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !notificationPanel.hidden) {
                notificationButton.setAttribute('aria-expanded', 'false');
                notificationPanel.hidden = true;
                notificationButton.focus();
            }
        });

        loadNotifications(false);
        window.setInterval(function () { loadNotifications(false); }, 45000);
    }

    // ---- Confirm dialogs: <form data-confirm="Delete this?"> or <button data-confirm="..."> ----
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-confirm], a[data-confirm]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });
})();
