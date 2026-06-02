/* ============================================================
   ZURAB KOSTAVA - SPA ROUTER & LOGIC
   ============================================================ */
(function () {
    var ZK   = window.ZK || {};
    var BASE = (function () { try { return new URL(ZK.home).pathname; } catch (e) { return '/'; } })();

    var body      = document.body;
    var header    = document.getElementById('site-header');
    var toggle    = document.getElementById('navToggle');
    var nav       = document.getElementById('primaryNav');
    var mq        = window.matchMedia('(max-width: 900px)');
    var viewEl    = document.getElementById('view');
    var announcer = document.getElementById('route-announcer');
    var dropdowns = [].slice.call(nav.querySelectorAll('.has-dropdown, .has-nested-dropdown'));
    var cache     = {};

    /* Header Scroll */
    function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 24); }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    /* Mobile Menu & Multiple Dropdowns */
    function setMenu(open) {
        body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        body.style.overflow = open ? 'hidden' : '';
        if (!open) {
            dropdowns.forEach(function(d) {
                d.classList.remove('open');
                var tr = d.children[0];
                if (tr && tr.classList.contains('dropdown-trigger')) tr.setAttribute('aria-expanded', 'false');
            });
        }
    }
    toggle.addEventListener('click', function () { setMenu(!body.classList.contains('nav-open')); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

    dropdowns.forEach(function(dropdown) {
        var trigger = dropdown.children[0];
        if (trigger && trigger.classList.contains('dropdown-trigger')) {
            trigger.addEventListener('click', function (e) {
                if (!mq.matches) return;
                e.preventDefault();
                e.stopPropagation();

                var isOpen = dropdown.classList.contains('open');

                var siblings = dropdown.parentElement.children;
                for (var i = 0; i < siblings.length; i++) {
                    var sib = siblings[i];
                    if (sib !== dropdown && (sib.classList.contains('has-dropdown') || sib.classList.contains('has-nested-dropdown'))) {
                        sib.classList.remove('open');
                        var t = sib.children[0];
                        if(t && t.classList.contains('dropdown-trigger')) t.setAttribute('aria-expanded', 'false');
                    }
                }

                if (!isOpen) {
                    dropdown.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                } else {
                    dropdown.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
    window.addEventListener('resize', function () { if (!mq.matches) setMenu(false); });

    /* SPA Router */
    function toRoute(pathname) {
        var p = pathname || '/';
        if (BASE !== '/' && p.indexOf(BASE) === 0) p = '/' + p.slice(BASE.length);
        p = p.replace(/\/+$/, '');
        return p === '' ? '/' : p;
    }
    function keyOf(u) { return u.pathname + u.search; }
    function delay(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function updateChrome(route) {
        [].slice.call(nav.querySelectorAll('[data-route], .dropdown-trigger')).forEach(function (el) {
            el.classList.remove('is-current');
            el.removeAttribute('aria-current');
        });
        var active = null;
        [].slice.call(nav.querySelectorAll('[data-route]')).forEach(function (el) {
            if (el.getAttribute('data-route') === route) active = el;
        });
        if (active) {
            active.classList.add('is-current');
            active.setAttribute('aria-current', 'page');
            var li = active.closest('.has-dropdown, .has-nested-dropdown');
            while (li) {
                var tr = li.children[0];
                if (tr && tr.classList.contains('dropdown-trigger')) tr.classList.add('is-current');
                li = li.parentElement && li.parentElement.closest('.has-dropdown, .has-nested-dropdown');
            }
        }
    }

    function scrollTopInstant() {
        var d = document.documentElement, prev = d.style.scrollBehavior;
        d.style.scrollBehavior = 'auto';
        window.scrollTo(0, 0);
        d.style.scrollBehavior = prev;
    }

    function fetchView(href) {
        return fetch(href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            if ((r.headers.get('Content-Type') || '').indexOf('text/html') === -1) throw new Error('Not HTML');
            return r.text();
        }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var v   = doc.getElementById('view');
            if (!v) throw new Error('No #view in response');
            var titleEl = doc.querySelector('title');
            return {
                html:  v.innerHTML,
                route: v.getAttribute('data-route') || toRoute(new URL(href, location.origin).pathname),
                title: titleEl ? titleEl.textContent : document.title
            };
        });
    }
    function getView(href, key) {
        if (cache[key]) return Promise.resolve(cache[key]);
        return fetchView(href).then(function (data) { cache[key] = data; return data; });
    }

    var token = 0;
    function navigate(href, push) {
        var u;
        try { u = new URL(href, location.origin); } catch (e) { window.location.href = href; return; }
        var key = keyOf(u);
        var t = ++token;

        setMenu(false);
        viewEl.classList.add('is-loading');

        Promise.all([
            getView(href, key).then(function (d) { return d; }, function (err) { return { error: err }; }),
            delay(200)
        ]).then(function (res) {
            if (t !== token) return;
            var data = res[0];
            if (!data || data.error) { window.location.href = href; return; }
            if (push) history.pushState({ url: u.href }, '', u.href);
            viewEl.innerHTML = data.html;
            viewEl.setAttribute('data-route', data.route);
            document.title = data.title;
            viewEl.classList.remove('is-loading');
            updateChrome(data.route);
            if (announcer) announcer.textContent = (data.title || 'Page') + ' loaded';
            scrollTopInstant();
            viewEl.focus({ preventScroll: true });
        });
    }

    function isInternal(a, url) {
        if (url.origin !== location.origin) return false;
        if (a.hasAttribute('download')) return false;
        if (a.target && a.target !== '_self') return false;
        if (/\bexternal\b/.test(a.getAttribute('rel') || '')) return false;
        if (/\/wp-(admin|login|json|content|includes)\b/.test(url.pathname)) return false;
        if (/\.[a-z0-9]{1,8}$/i.test(url.pathname) && !/\.html?$/i.test(url.pathname)) return false;
        return true;
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        var url;
        try { url = new URL(a.href); } catch (_) { return; }
        if (!isInternal(a, url)) return;

        if (url.pathname === location.pathname && url.search === location.search) {
            if (url.hash) return;
            e.preventDefault();
            setMenu(false);
            return;
        }
        e.preventDefault();
        navigate(a.href, true);
    });

    window.addEventListener('popstate', function () { navigate(location.href, false); });

    cache[keyOf(location)] = {
        html:  viewEl.innerHTML,
        route: viewEl.getAttribute('data-route') || toRoute(location.pathname),
        title: document.title
    };
    updateChrome(viewEl.getAttribute('data-route') || toRoute(location.pathname));
})();

/* ============================================================
   CUSTOM FLOATING SCROLLBAR
   ============================================================ */
(function () {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    var docEl = document.documentElement;
    docEl.classList.add('custom-scroll');

    var bar = document.createElement('div');
    bar.className = 'scrollbar';
    bar.setAttribute('aria-hidden', 'true');
    var thumb = document.createElement('div');
    thumb.className = 'scrollbar__thumb';
    bar.appendChild(thumb);
    document.body.appendChild(bar);

    var MIN = 40, hideTimer = null, dragging = false, rafId = 0;

    function maxScroll() { return docEl.scrollHeight - window.innerHeight; }

    function update() {
        var ms = maxScroll();
        if (ms <= 1) { bar.classList.add('is-empty'); return; }
        bar.classList.remove('is-empty');
        var trackH = bar.clientHeight;
        var thumbH = Math.max(MIN, (window.innerHeight / docEl.scrollHeight) * trackH);
        var p = Math.min(1, Math.max(0, (window.scrollY || docEl.scrollTop) / ms));
        thumb.style.height = thumbH + 'px';
        thumb.style.transform = 'translateY(' + (p * (trackH - thumbH)) + 'px)';
    }

    function reveal() {
        bar.classList.add('is-active');
        if (hideTimer) clearTimeout(hideTimer); 
        if (!dragging) hideTimer = setTimeout(function () { bar.classList.remove('is-active'); }, 1200);
    }

    function onScroll() {
        if (rafId) return;
        rafId = requestAnimationFrame(function () { rafId = 0; update(); reveal(); });
    }

    thumb.addEventListener('pointerdown', function (e) {
        e.preventDefault();
        dragging = true;
        bar.classList.add('is-active', 'is-dragging');
        try { thumb.setPointerCapture(e.pointerId); } catch (_) {}
        var startY = e.clientY;
        var startScroll = window.scrollY || docEl.scrollTop;
        var thumbH = parseFloat(thumb.style.height) || MIN;
        var ratio = maxScroll() / (bar.clientHeight - thumbH);
        var prevBehavior = docEl.style.scrollBehavior;
        docEl.style.scrollBehavior = 'auto';

        function move(ev) { window.scrollTo(0, startScroll + (ev.clientY - startY) * ratio); }
        function up() {
            dragging = false;
            bar.classList.remove('is-dragging');
            docEl.style.scrollBehavior = prevBehavior;
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', up);
            reveal();
        }
        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', up);
    });

    document.addEventListener('pointermove', function (e) {
        if (!dragging && e.clientX >= window.innerWidth - 26) reveal();
    }, { passive: true });

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', update);
    if ('ResizeObserver' in window) { new ResizeObserver(update).observe(document.body); }

    update();
})();
