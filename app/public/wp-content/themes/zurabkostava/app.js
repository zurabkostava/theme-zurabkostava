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



/* ============================================================
   CUSTOM GRID CONTROLS: SORTING & LIVE SEARCH (Vanilla JS)
   ============================================================ */
(function() {
    // 1. სორტირების მთავარი ფუნქცია
    function applySort(wrapper, order, animate) {
        var dropdown = wrapper.querySelector('.zk-sort-dropdown');
        var currentText = wrapper.querySelector('.zk-sort-current');
        var grid = wrapper.querySelector('.zk-post-grid');
        var options = wrapper.querySelectorAll('.zk-sort-option');
        var targetOption = wrapper.querySelector('.zk-sort-option[data-sort="' + order + '"]');

        if (!grid || !targetOption) return;

        options.forEach(function(o) { o.classList.remove('is-selected'); });
        targetOption.classList.add('is-selected');
        if (currentText) currentText.textContent = targetOption.textContent;
        if (dropdown) dropdown.classList.remove('is-open');

        try { localStorage.setItem('zkGridSort', order); } catch(e) {}

        var cards = [].slice.call(grid.querySelectorAll('.zk-grid-card'));
        cards.sort(function(a, b) {
            var tA = parseInt(a.getAttribute('data-time'), 10);
            var tB = parseInt(b.getAttribute('data-time'), 10);
            return order === 'asc' ? tA - tB : tB - tA;
        });

        if (animate) {
            grid.style.transition = 'opacity 0.25s var(--ease)';
            grid.style.opacity = '0';
            setTimeout(function() {
                cards.forEach(function(card) { grid.appendChild(card); });
                grid.style.opacity = '1';
            }, 250);
        } else {
            cards.forEach(function(card) { grid.appendChild(card); });
        }
    }

    // 2. კლიკების მართვა (Dropdown)
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.zk-sort-trigger');
        if (trigger) {
            var dropdown = trigger.closest('.zk-sort-dropdown');
            var isOpen = dropdown.classList.contains('is-open');
            document.querySelectorAll('.zk-sort-dropdown').forEach(function(d) { d.classList.remove('is-open'); });
            if (!isOpen) dropdown.classList.add('is-open');
            return;
        }

        if (!e.target.closest('.zk-sort-dropdown')) {
            document.querySelectorAll('.zk-sort-dropdown').forEach(function(d) { d.classList.remove('is-open'); });
        }

        var option = e.target.closest('.zk-sort-option');
        if (option) {
            var wrapper = option.closest('.zk-grid-wrapper');
            var order = option.getAttribute('data-sort');
            if (!option.classList.contains('is-selected')) {
                applySort(wrapper, order, true);
            }
        }
    });

    // 3. მყისიერი ძებნისა და ფილტრაციის ლოგიკა (Live Search)
    document.addEventListener('input', function(e) {
        var input = e.target.closest('.zk-search-input');
        if (!input) return;

        var wrapper = input.closest('.zk-grid-wrapper');
        var query = input.value.toLowerCase().trim();
        var cards = wrapper.querySelectorAll('.zk-grid-card');

        cards.forEach(function(card) {
            // ეძებს როგორც სათაურში, ისე კატეგორიის სახელში
            var title = (card.querySelector('.zk-card-title').textContent || '').toLowerCase();
            var cat = (card.querySelector('.zk-card-category').textContent || '').toLowerCase();

            if (title.indexOf(query) !== -1 || cat.indexOf(query) !== -1) {
                card.style.display = '';
                card.style.opacity = '1';
            } else {
                card.style.display = 'none';
                card.style.opacity = '0';
            }
        });
    });

    // 4. მეხსიერების შემოწმება გვერდის ჩატვირთვისას და SPA გადასვლებისას
    function checkMemory() {
        try {
            var savedOrder = localStorage.getItem('zkGridSort');
            if (savedOrder) {
                document.querySelectorAll('.zk-grid-wrapper').forEach(function(wrapper) {
                    applySort(wrapper, savedOrder, false);
                });
            }
        } catch(e) {}
    }

    checkMemory();

    var viewEl = document.getElementById('view');
    if (viewEl) {
        new MutationObserver(function() {
            checkMemory();
        }).observe(viewEl, { childList: true });
    }
})();

/* ============================================================
   STICKY BREADCRUMBS SENSOR (მინის ეფექტის ჩამრთველი)
   ============================================================ */
(function() {
    function initStickyBreadcrumbs() {
        var breadcrumbs = document.querySelector('.zk-breadcrumbs');
        // თუ ბრედკრამბი არ არსებობს ან სენსორი უკვე დასმულია, არაფერს ვაკეთებთ
        if (!breadcrumbs || (breadcrumbs.previousElementSibling && breadcrumbs.previousElementSibling.classList.contains('zk-sentinel'))) return;

        // ვქმნით უხილავ პატარა სენსორს ბრედკრამბის ზუსტად თავზე
        var sentinel = document.createElement('div');
        sentinel.className = 'zk-sentinel';
        sentinel.style.position = 'absolute';
        sentinel.style.height = '0px';
        breadcrumbs.parentNode.insertBefore(sentinel, breadcrumbs);

        // ვაკვირდებით, როდის გადაკვეთს ეს სენსორი ჰედერის ხაზს (64px სიმაღლე + 14px დაშორება = 78px)
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                // თუ სენსორი გასცდა ხაზს (ზემოთ ავიდა), ვრთავთ მინის კაფსულას
                breadcrumbs.classList.toggle('is-stuck', !entry.isIntersecting);
            });
        }, {
            rootMargin: '-78px 0px 0px 0px'
        });

        observer.observe(sentinel);
    }

    // ვუშვებთ ეგრევე ჩატვირთვისას
    initStickyBreadcrumbs();

    // SPA როუტერისთვის: როცა გვერდები უდეფრეშოდ იცვლება, ახალ გვერდზეც ავტომატურად ვრთავთ სენსორს
    var viewEl = document.getElementById('view');
    if (viewEl) {
        new MutationObserver(initStickyBreadcrumbs).observe(viewEl, { childList: true });
    }
})();

/* ============================================================
   CINEMATIC IMAGE LOADING — soft fade-in once fully loaded
   ------------------------------------------------------------
   Reveals real <img> (article content) and CSS background-image
   holders (.zk-card-image, .zk-post-hero) only after their pixels
   are ready, so nothing pops. Re-runs on every SPA view swap.
   ============================================================ */
(function () {
    try {
        var LOADED = 'is-loaded';
        function reveal(el) { el.classList.add(LOADED); }

        /* Real <img>: native load/error events (+ cached check). */
        function watchImg(img) {
            if (img.dataset.zkFade) return;
            img.dataset.zkFade = '1';
            if (img.complete && img.naturalWidth > 0) { reveal(img); return; }
            function done() {
                reveal(img);
                img.removeEventListener('load', done);
                img.removeEventListener('error', done);
            }
            img.addEventListener('load', done);
            img.addEventListener('error', done); // a broken image must not stay hidden
        }

        /* CSS background-image holder: preload the URL, then reveal. */
        function watchBg(el) {
            if (el.dataset.zkFade) return;
            el.dataset.zkFade = '1';
            var bg = el.style.backgroundImage || getComputedStyle(el).backgroundImage;
            var m  = bg && bg.match(/url\(\s*["']?(.*?)["']?\s*\)/);
            if (!m || !m[1]) { reveal(el); return; } // nothing to wait for
            var pre = new Image();
            pre.onload = pre.onerror = function () { reveal(el); };
            pre.src = m[1];
            if (pre.complete && pre.naturalWidth > 0) reveal(el); // already cached
        }

        function scan(scope) {
            var root = scope || document;
            root.querySelectorAll('.page__content img').forEach(watchImg);
            root.querySelectorAll('.zk-card-image, .zk-post-hero').forEach(watchBg);
        }

        scan(document);

        /* Re-scan after each SPA navigation (matches the grid/breadcrumb modules). */
        var viewEl = document.getElementById('view');
        if (viewEl) {
            new MutationObserver(function () { scan(viewEl); }).observe(viewEl, { childList: true });
        }
    } catch (e) {
        /* Fail open — never leave images invisible if something goes wrong. */
        document.documentElement.classList.remove('zk-img-js');
    }
})();


/* ============================================================
   CINEMATIC PHOTOGRAPHY GALLERY - LOGIC
   ============================================================ */
(function() {
    function initGallery() {
        var gallery = document.querySelector('.zk-gallery-wrapper');
        if (!gallery) return;

        var buttons = gallery.querySelectorAll('.zk-filter-btn');
        var items = gallery.querySelectorAll('.zk-gallery-item');

        // 1. ფილტრაცია (Seamless Masonry Sort)
        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var filter = this.getAttribute('data-filter');

                buttons.forEach(function(b) { b.classList.remove('is-active'); });
                this.classList.add('is-active');

                items.forEach(function(item) {
                    var cat = item.getAttribute('data-category');
                    if (filter === 'all' || cat === filter) {
                        item.style.position = 'relative';
                        item.style.opacity = '1';
                        item.style.transform = 'scale(1)';
                        item.style.pointerEvents = 'auto';
                    } else {
                        item.style.opacity = '0';
                        item.style.transform = 'scale(0.9)';
                        item.style.pointerEvents = 'none';
                        // რათა სივრცე გაათავისუფლოს და სვეტები შეიკრას
                        setTimeout(function() {
                            if (item.style.opacity === '0') item.style.position = 'absolute';
                        }, 400);
                    }
                });
            });
        });

        // 2. Cinematic Lightbox (გადიდება)
        var lightbox = document.getElementById('zkLightbox');
        var lightboxImg = lightbox.querySelector('.zk-lightbox-img');
        var lightboxExif = lightbox.querySelector('.zk-lightbox-exif');
        var closeBtn = lightbox.querySelector('.zk-lightbox-close');

        items.forEach(function(item) {
            var img = item.querySelector('img');
            if (!img) return;

            item.addEventListener('click', function() {
                var fullSrc = img.getAttribute('data-full');
                var exif = img.getAttribute('data-exif');

                lightboxImg.src = fullSrc;
                lightboxExif.textContent = exif || '';
                lightbox.classList.add('is-open');
            });
        });

        function closeLightbox() {
            lightbox.classList.remove('is-open');
            setTimeout(function() { lightboxImg.src = ''; }, 500);
        }

        closeBtn.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) closeLightbox();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.classList.contains('is-open')) closeLightbox();
        });
    }

    initGallery();

    // SPA თავსებადობა
    var viewEl = document.getElementById('view');
    if (viewEl) {
        new MutationObserver(initGallery).observe(viewEl, { childList: true });
    }
})();