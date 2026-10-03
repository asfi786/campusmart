/* =====================================================================
   CampusMart client JS — vanilla, no build step.
   - theme toggle, toasts, menus
   - wishlist hearts (AJAX, no reload)
   - Smart Price Suggestion on the listing form
   - chat polling (every 3s) + sending
   - unread badges, image previews
   ===================================================================== */
(function () {
    'use strict';

    var CM = window.CM || {};
    var base = CM.base || '/';
    var pollMs = CM.pollMs || 3000;

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ---------------- Theme ---------------- */
    var themeBtn = $('#theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            try { localStorage.setItem('cm-theme', next); } catch (e) {}
        });
    }

    /* ---------------- Menus ---------------- */
    var navToggle = $('#nav-toggle'), mobileNav = $('#mobile-nav');
    if (navToggle && mobileNav) {
        navToggle.addEventListener('click', function () {
            var open = mobileNav.hasAttribute('hidden');
            if (open) { mobileNav.removeAttribute('hidden'); }
            else { mobileNav.setAttribute('hidden', ''); }
            navToggle.setAttribute('aria-expanded', String(open));
        });
    }
    var avatarBtn = $('#avatar-btn'), avatarMenu = $('#avatar-menu');
    if (avatarBtn && avatarMenu) {
        avatarBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            avatarMenu.toggleAttribute('hidden');
        });
        document.addEventListener('click', function () { avatarMenu.setAttribute('hidden', ''); });
    }

    /* ---------------- Toasts ---------------- */
    function dismissToast(el) { el.remove(); }
    $all('.toast').forEach(function (t) {
        setTimeout(function () { dismissToast(t); }, 6000);
        var c = $('.toast-close', t);
        if (c) c.addEventListener('click', function () { dismissToast(t); });
    });
    window.showToast = function (type, msg) {
        var wrap = $('#toast-wrap');
        if (!wrap) return;
        var t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.setAttribute('role', 'status');
        t.textContent = msg;
        var c = document.createElement('button');
        c.className = 'toast-close'; c.setAttribute('aria-label', 'Dismiss'); c.textContent = '×';
        c.addEventListener('click', function () { dismissToast(t); });
        t.appendChild(c);
        wrap.appendChild(t);
        setTimeout(function () { dismissToast(t); }, 4000);
    };

    /* ---------------- AJAX helper (with CSRF) ---------------- */
    function postForm(url, data) {
        var fd = new FormData();
        fd.append('csrf', CM.csrf || '');
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    /* ---------------- Wishlist hearts (no page reload) ---------------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-wishlist]');
        if (!btn) return;
        e.preventDefault();
        var listingId = btn.getAttribute('data-wishlist');
        postForm(base + 'api/wishlist_toggle.php', { listing_id: listingId })
            .then(function (res) {
                if (!res.ok) { showToast('error', res.error || 'Please log in.'); return; }
                btn.classList.toggle('active', res.wishlisted);
                btn.setAttribute('aria-pressed', String(res.wishlisted));
                if (btn.classList.contains('heart-toggle')) {
                    btn.textContent = res.wishlisted ? '♥ Saved to wishlist' : '♡ Save to wishlist';
                }
                showToast('success', res.wishlisted ? 'Saved to your wishlist.' : 'Removed from wishlist.');
            })
            .catch(function () { showToast('error', 'Something went wrong.'); });
    });

    /* ---------------- Gallery thumbnails ---------------- */
    $all('.thumb').forEach(function (th) {
        th.addEventListener('click', function () {
            var main = $('#gallery-main');
            if (main) main.src = th.getAttribute('data-full');
            $all('.thumb').forEach(function (x) { x.classList.remove('active'); });
            th.classList.add('active');
        });
    });

    /* ---------------- Image previews on the listing form ---------------- */
    var fileInput = $('input[data-preview]'), previewBox = $('#img-preview');
    if (fileInput && previewBox) {
        fileInput.addEventListener('change', function () {
            previewBox.innerHTML = '';
            previewBox.removeAttribute('aria-hidden');
            Array.prototype.forEach.call(fileInput.files, function (f) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.alt = '';
                previewBox.appendChild(img);
            });
        });
    }

    /* ---------------- Smart Price Suggestion ---------------- */
    var psBox = $('#price-suggest');
    if (psBox) {
        var fCat = $('#f-category'), fTitle = $('#f-title'), fCond = $('#f-condition'),
            fPrice = $('#f-price'), psTimer = null, lastMedian = null;

        function fetchSuggestion() {
            var cat = fCat.value, title = fTitle.value.trim();
            if (!cat || title.length < 3) { psBox.setAttribute('hidden', ''); return; }
            var url = base + 'api/price_suggest.php?category_id=' + encodeURIComponent(cat) +
                      '&title=' + encodeURIComponent(title) +
                      '&condition=' + encodeURIComponent(fCond.value);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (s) {
                    if (!s.enough) { psBox.setAttribute('hidden', ''); return; }
                    psBox.removeAttribute('hidden');
                    $('#ps-range').textContent = s.min_fmt + ' – ' + s.max_fmt;
                    $('#ps-note').textContent = s.note + ' Median: ' + s.median_fmt + '.';
                    var conf = $('#ps-conf');
                    conf.textContent = s.confidence + ' confidence';
                    conf.className = 'ps-conf conf-' + s.confidence;
                    lastMedian = s.median;
                    $('#ps-apply').removeAttribute('hidden');
                })
                .catch(function () { /* suggestion is best-effort */ });
        }
        [fCat, fTitle, fCond].forEach(function (el) {
            el.addEventListener('input', function () {
                clearTimeout(psTimer);
                psTimer = setTimeout(fetchSuggestion, 500);
            });
            el.addEventListener('change', function () {
                clearTimeout(psTimer);
                psTimer = setTimeout(fetchSuggestion, 500);
            });
        });
        $('#ps-apply').addEventListener('click', function () {
            if (lastMedian !== null && fPrice) {
                fPrice.value = Math.round(lastMedian);
                showToast('success', 'Median price applied — adjust it if you like.');
            }
        });
    }

    /* ---------------- Chat: polling + sending ---------------- */
    var thread = $('#chat-thread');
    if (thread) {
        var convId = thread.getAttribute('data-conversation');
        var box = $('#chat-messages');
        var form = $('#chat-form'), input = $('#chat-input');

        function lastId() {
            var msgs = $all('.msg', box);
            if (!msgs.length) return 0;
            return parseInt(msgs[msgs.length - 1].getAttribute('data-msg-id') || '0', 10);
        }
        function scrollBottom() { box.scrollTop = box.scrollHeight; }

        function renderMsg(m) {
            var wrap = document.createElement('div');
            wrap.className = 'msg ' + (m.mine ? 'me' : 'they');
            wrap.setAttribute('data-msg-id', m.id);
            var bubble = document.createElement('div');
            bubble.className = 'msg-bubble';
            bubble.textContent = m.body; // textContent => no HTML injection
            var time = document.createElement('div');
            time.className = 'msg-time muted tiny';
            time.textContent = m.time_ago;
            wrap.appendChild(bubble);
            wrap.appendChild(time);
            box.appendChild(wrap);
        }

        var polling = false;
        function poll() {
            if (polling || document.hidden) return;
            polling = true;
            fetch(base + 'api/chat_poll.php?conversation_id=' + convId + '&after_id=' + lastId(),
                  { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    polling = false;
                    if (!res.ok || !res.messages.length) return;
                    var incoming = false;
                    res.messages.forEach(function (m) {
                        renderMsg(m);
                        if (!m.mine) incoming = true;
                    });
                    scrollBottom();
                    if (incoming) {
                        postForm(base + 'api/chat_mark_read.php', { conversation_id: convId });
                    }
                })
                .catch(function () { polling = false; });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = input.value.trim();
            if (!text) return;
            input.value = '';
            postForm(base + 'api/chat_send.php', { conversation_id: convId, body: text })
                .then(function (res) {
                    if (res.ok) { renderMsg(res.message); scrollBottom(); }
                    else showToast('error', res.error || 'Could not send.');
                })
                .catch(function () { showToast('error', 'Could not send. Check your connection.'); });
        });

        scrollBottom();
        setInterval(poll, pollMs);
    }

    /* ---------------- Unread badges ---------------- */
    var badgeTargets = ['#notif-badge', '#tab-msg-badge'];
    function refreshBadges() {
        if (!CM.userId) return;
        fetch(base + 'api/unread_count.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) return;
                var nb = $('#notif-badge');
                if (nb) {
                    nb.textContent = res.notifications;
                    if (res.notifications > 0) nb.removeAttribute('hidden');
                    else nb.setAttribute('hidden', '');
                }
                var tb = $('#tab-msg-badge');
                if (tb) {
                    tb.textContent = res.messages;
                    if (res.messages > 0) tb.removeAttribute('hidden');
                    else tb.setAttribute('hidden', '');
                }
                var pill = $('#nav-msg-pill');
                if (pill) {
                    pill.textContent = res.messages;
                    pill.style.display = res.messages > 0 ? '' : 'none';
                }
            })
            .catch(function () {});
    }
    if (CM.userId) setInterval(refreshBadges, 15000);

    /* ---------------- Lost & Found form toggle ---------------- */
    var lfToggle = $('#lf-toggle'), lfForm = $('#lf-form');
    if (lfToggle && lfForm) {
        lfToggle.addEventListener('click', function () {
            lfForm.toggleAttribute('hidden');
            if (!lfForm.hasAttribute('hidden')) lfForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
})();
