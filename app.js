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

    var mobileMenuTrigger = document.getElementById('zk-mobile-menu-trigger');
    if (mobileMenuTrigger) {
        mobileMenuTrigger.addEventListener('click', function() { setMenu(!body.classList.contains('nav-open')); });
    }

    var bottomNav = document.getElementById('zk-bottom-nav');

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
    
    function zkTrackView(route) {
        if (!window.fetch || !window.ZK) return;
        if (window.zkIsAdmin) return;
        try { 
            if (localStorage.getItem('zk_ignore_tracking') === 'true' && window.location.search.indexOf('force_track') === -1) return; 
        } catch(e) {}

        var apiRoute = ZK.home.replace(/\/$/, '') + '/wp-json/zk/v1/sync';
        
        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        var visitorId = '';
        try {
            visitorId = localStorage.getItem('zk_visitor_id');
            if (!visitorId) {
                visitorId = generateUUID();
                localStorage.setItem('zk_visitor_id', visitorId);
            }
        } catch (e) {}

        var sessionId = '';
        try {
            sessionId = sessionStorage.getItem('zk_session_id');
            if (!sessionId) {
                sessionId = generateUUID();
                sessionStorage.setItem('zk_session_id', sessionId);
            }
        } catch (e) {}

        function sendTrack(country, city) {
            var payload = JSON.stringify({ 
                url: route, 
                country: country || '', 
                city: city || '',
                visitor_id: visitorId,
                session_id: sessionId
            });
            var sent = false;
            if (navigator.sendBeacon) {
                try { sent = navigator.sendBeacon(apiRoute, payload); } catch(e) {}
            }
            if (!sent) {
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', apiRoute, true);
                    xhr.setRequestHeader('Content-Type', 'text/plain');
                    xhr.send(payload);
                } catch(err) {}
            }
        }

        var geoResolved = false;
        var geoTimeout = setTimeout(function() {
            if (!geoResolved) {
                geoResolved = true;
                sendTrack('', '');
            }
        }, 1500);

        try {
            var cachedGeo = sessionStorage.getItem('zk_geo');
            if (cachedGeo) {
                var geo = JSON.parse(cachedGeo);
                geoResolved = true;
                clearTimeout(geoTimeout);
                sendTrack(geo.country, geo.city);
                return;
            }
        } catch (e) {}

        fetch('https://ipapi.co/json/')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (geoResolved) return;
                geoResolved = true;
                clearTimeout(geoTimeout);
                var cty = data.country_name || data.country;
                var ctyName = data.city || '';
                try { sessionStorage.setItem('zk_geo', JSON.stringify({ country: cty, city: ctyName })); } catch (e) {}
                sendTrack(cty, ctyName);
            })
            .catch(function() {
                if (geoResolved) return;
                fetch('https://get.geojs.io/v1/ip/geo.json')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (geoResolved) return;
                        geoResolved = true;
                        clearTimeout(geoTimeout);
                        try { sessionStorage.setItem('zk_geo', JSON.stringify({ country: data.country, city: data.city || '' })); } catch (e) {}
                        sendTrack(data.country, data.city || '');
                    })
                    .catch(function() {
                        if (geoResolved) return;
                        geoResolved = true;
                        clearTimeout(geoTimeout);
                        sendTrack('', '');
                    });
            });
    }
    function keyOf(u) { return u.pathname + u.search; }
    function delay(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function updateChrome(route) {
        [].slice.call(nav.querySelectorAll('[data-route], .dropdown-trigger')).forEach(function (el) {
            el.classList.remove('is-current');
            el.removeAttribute('aria-current');
        });
        
        [].slice.call(document.querySelectorAll('.zk-bottom-nav-item')).forEach(function (el) {
            el.classList.remove('is-active');
        });

        var active = null;
        [].slice.call(nav.querySelectorAll('[data-route]')).forEach(function (el) {
            if (el.getAttribute('data-route') === route) active = el;
        });

        var activeBottom = null;
        // Check exact match first, or prefix match for sub-pages like /books/some-book/
        [].slice.call(document.querySelectorAll('.zk-bottom-nav-item[data-route]')).forEach(function (el) {
            var elRoute = el.getAttribute('data-route');
            if (elRoute === route) {
                activeBottom = el;
            } else if (elRoute !== '/' && route.indexOf(elRoute) === 0) {
                activeBottom = el; // Partial match for sub-pages
            }
        });

        if (activeBottom) activeBottom.classList.add('is-active');
        
        var navEl = document.getElementById('zk-bottom-nav');
        if (navEl) {
            if (route !== '/') {
                navEl.classList.add('is-collapsed');
                body.classList.add('nav-collapsed');
            } else {
                navEl.classList.remove('is-collapsed');
                body.classList.remove('nav-collapsed');
            }
        }

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
            
            var newHeadTags = Array.prototype.slice.call(doc.head.querySelectorAll('meta[name="description"], meta[property^="og:"], meta[name^="twitter:"], script[type="application/ld+json"]'))
                .map(function(el) { return el.outerHTML; })
                .join('\n');

            return {
                html:  v.innerHTML,
                route: v.getAttribute('data-route') || toRoute(new URL(href, location.origin).pathname),
                title: titleEl ? titleEl.textContent : document.title,
                headTags: newHeadTags
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
        document.body.classList.add('is-navigating');

        Promise.all([
            getView(href, key).then(function (d) { return d; }, function (err) { return { error: err }; }),
            delay(200)
        ]).then(function (res) {
            if (t !== token) return;
            var data = res[0];
            if (!data || data.error) { window.location.href = href; return; }
            if (push) history.pushState({ url: u.href }, '', u.href);

            function render() {
                viewEl.innerHTML = data.html;
                viewEl.setAttribute('data-route', data.route);
                document.title = data.title;
                
                if (data.headTags !== undefined) {
                    var oldTags = document.head.querySelectorAll('meta[name="description"], meta[property^="og:"], meta[name^="twitter:"], script[type="application/ld+json"]');
                    for (var i = 0; i < oldTags.length; i++) oldTags[i].parentNode.removeChild(oldTags[i]);
                    document.head.insertAdjacentHTML('beforeend', data.headTags);
                }

                viewEl.classList.remove('is-loading');
                document.body.classList.remove('is-navigating');
                updateChrome(data.route);
                if (announcer) announcer.textContent = (data.title || 'Page') + ' loaded';
                scrollTopInstant();
                viewEl.focus({ preventScroll: true });
                document.dispatchEvent(new CustomEvent('zk:viewChange'));
                zkTrackView(data.route);
            }

            if (document.startViewTransition) {
                document.startViewTransition(render);
            } else {
                render();
            }
        });
    }

    function isInternal(a, url) {
        if (url.origin !== location.origin) return false;
        if (a.hasAttribute('download')) return false;
        if (a.target && a.target !== '_self') return false;
        if (/\bexternal\b/.test(a.getAttribute('rel') || '')) return false;
        if (/\/wp-(admin|login|json|content|includes)\b/.test(url.pathname)) return false;
        if (/\.[a-z0-9]{1,8}$/i.test(url.pathname) && !/\.html?$/i.test(url.pathname)) return false;

        // 🔴 თუ ლინკს აქვს კლასი no-spa, SPA როუტერი იგნორირებას უკეთებს
        if (a.classList.contains('no-spa')) return false;
        
        // Exclude Book Engine isolated pages
        if (url.pathname.match(/^\/?(ka\/)?write\/?$/i)) return false;
        if (url.pathname.match(/^\/?(ka\/)?books\/[^\/]+\/?/i)) return false;

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
    var initialRoute = viewEl.getAttribute('data-route') || toRoute(location.pathname);
    updateChrome(initialRoute);
    zkTrackView(initialRoute);
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

    // 3. მყისიერი ძებნისა და ფილტრაციის ლოგიკა (Smart Filtering + Live Search)
    function applySmartFilters(wrapper) {
        var query = '';
        var searchInput = wrapper.querySelector('.zk-search-input');
        if (searchInput) query = searchInput.value.toLowerCase().trim();

        var activeFilter = 'all';
        var activePill = wrapper.querySelector('.zk-filter-pill.is-active');
        if (activePill) activeFilter = activePill.getAttribute('data-filter');

        var cards = wrapper.querySelectorAll('.zk-grid-card');

        cards.forEach(function(card) {
            var titleEl = card.querySelector('.zk-card-title');
            var catEl = card.querySelector('.zk-card-category');
            
            var title = titleEl ? titleEl.textContent.toLowerCase() : '';
            var catText = catEl ? catEl.textContent.toLowerCase() : '';
            var cardCat = card.getAttribute('data-category') || '';

            var matchesSearch = (title.indexOf(query) !== -1 || catText.indexOf(query) !== -1);
            var matchesFilter = (activeFilter === 'all' || cardCat === activeFilter);

            if (matchesSearch && matchesFilter) {
                card.style.display = '';
                card.style.opacity = '1';
            } else {
                card.style.display = 'none';
                card.style.opacity = '0';
            }
        });
    }

    function initSmartFilters(wrapper) {
        if (wrapper.querySelector('.zk-category-filters')) return; // Already initialized

        var cards = wrapper.querySelectorAll('.zk-grid-card');
        if (!cards.length) return;

        var categories = new Set();
        var catNames = {};

        cards.forEach(function(card) {
            var catEl = card.querySelector('.zk-card-category');
            if (catEl) {
                var catText = catEl.textContent.trim();
                if (catText) {
                    var catId = catText.toLowerCase();
                    categories.add(catId);
                    if (!catNames[catId]) {
                        catNames[catId] = catText;
                    }
                    // Apply it to the card dynamically so applySmartFilters works reliably
                    card.setAttribute('data-category', catId);
                }
            }
        });

        // Only create UI if there's more than 1 category
        if (categories.size > 1) {
            var controls = wrapper.querySelector('.zk-grid-controls');
            if (!controls) return;
            
            // Layout fixes for controls wrapper
            controls.style.flexWrap = window.innerWidth <= 768 ? 'wrap' : 'nowrap';
            window.addEventListener('resize', function() { controls.style.flexWrap = window.innerWidth <= 768 ? 'wrap' : 'nowrap'; });

            var hash = window.location.hash.replace('#', '').toLowerCase();
            var hasHashMatch = categories.has(hash);

            var filterWrapper = document.createElement('div');
            filterWrapper.className = 'zk-category-filters-wrapper';

            var filterContainer = document.createElement('div');
            filterContainer.className = 'zk-category-filters';

            var allBtn = document.createElement('button');
            allBtn.className = hasHashMatch ? 'zk-filter-pill' : 'zk-filter-pill is-active';
            allBtn.setAttribute('data-filter', 'all');
            allBtn.textContent = 'All';
            filterContainer.appendChild(allBtn);

            categories.forEach(function(cat) {
                var btn = document.createElement('button');
                btn.className = (hasHashMatch && hash === cat) ? 'zk-filter-pill is-active' : 'zk-filter-pill';
                btn.setAttribute('data-filter', cat);
                btn.textContent = catNames[cat] || cat;
                filterContainer.appendChild(btn);
            });

            filterWrapper.appendChild(filterContainer);

            // Insert after search box
            var searchBox = wrapper.querySelector('.zk-search-box');
            if (searchBox && searchBox.nextSibling) {
                controls.insertBefore(filterWrapper, searchBox.nextSibling);
            } else {
                controls.appendChild(filterWrapper);
            }

            // Mouse drag and vertical wheel scroll functionality
            var isDown = false;
            var startX;
            var scrollLeftInit;

            filterContainer.addEventListener('mousedown', function(e) {
                isDown = true;
                filterContainer.style.cursor = 'grabbing';
                startX = e.pageX - filterContainer.offsetLeft;
                scrollLeftInit = filterContainer.scrollLeft;
            });
            filterContainer.addEventListener('mouseleave', function() {
                isDown = false;
                filterContainer.style.cursor = '';
            });
            filterContainer.addEventListener('mouseup', function(e) {
                isDown = false;
                filterContainer.style.cursor = '';
                // Prevent accidental click if they actually dragged
                if (Math.abs(filterContainer.scrollLeft - scrollLeftInit) > 5) {
                    var pill = e.target.closest('.zk-filter-pill');
                    if (pill) pill.setAttribute('data-prevent-click', 'true');
                }
            });
            filterContainer.addEventListener('mousemove', function(e) {
                if (!isDown) return;
                e.preventDefault();
                var x = e.pageX - filterContainer.offsetLeft;
                var walk = (x - startX) * 2;
                filterContainer.scrollLeft = scrollLeftInit - walk;
            });

            // Map vertical scroll wheel to horizontal
            filterContainer.addEventListener('wheel', function(e) {
                if (e.deltaY !== 0) {
                    // Only prevent default if we actually have room to scroll horizontally
                    var maxScroll = filterContainer.scrollWidth - filterContainer.clientWidth;
                    if (maxScroll > 0) {
                        // Let users scroll the page vertically if they hit the edge of the horizontal scroll
                        var isAtLeftEdge = (filterContainer.scrollLeft <= 0 && e.deltaY < 0);
                        var isAtRightEdge = (filterContainer.scrollLeft >= maxScroll && e.deltaY > 0);
                        
                        if (!isAtLeftEdge && !isAtRightEdge) {
                            e.preventDefault();
                            filterContainer.scrollLeft += e.deltaY;
                        }
                    }
                }
            }, { passive: false });
            
            if (hasHashMatch) {
                setTimeout(function() {
                    var active = filterContainer.querySelector('.is-active');
                    if (active) active.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    applySmartFilters(wrapper);
                }, 100);
            }
        }
    }

    document.addEventListener('input', function(e) {
        var input = e.target.closest('.zk-search-input');
        if (!input) return;
        var wrapper = input.closest('.zk-grid-wrapper');
        applySmartFilters(wrapper);
    });

    document.addEventListener('click', function(e) {
        var filterPill = e.target.closest('.zk-filter-pill');
        if (filterPill) {
            // Prevent click if we were dragging
            if (filterPill.hasAttribute('data-prevent-click')) {
                filterPill.removeAttribute('data-prevent-click');
                return;
            }

            var wrapper = filterPill.closest('.zk-grid-wrapper');
            wrapper.querySelectorAll('.zk-filter-pill').forEach(function(btn) {
                btn.classList.remove('is-active');
            });
            filterPill.classList.add('is-active');
            
            var filterId = filterPill.getAttribute('data-filter');
            if (filterId === 'all') {
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.pathname + window.location.search);
                } else {
                    window.location.hash = '';
                }
            } else {
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, '#' + filterId);
                } else {
                    window.location.hash = filterId;
                }
            }
            
            filterPill.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            applySmartFilters(wrapper);
        }
    });

    // 4. მეხსიერების შემოწმება გვერდის ჩატვირთვისას და SPA გადასვლებისას
    function checkMemory() {
        try {
            var savedOrder = localStorage.getItem('zkGridSort');
            document.querySelectorAll('.zk-grid-wrapper').forEach(function(wrapper) {
                initSmartFilters(wrapper);
                if (savedOrder) {
                    applySort(wrapper, savedOrder, false);
                }
            });
        } catch(e) {}
    }

    checkMemory();
    document.addEventListener('zk:viewChange', checkMemory);
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
    document.addEventListener('zk:viewChange', initStickyBreadcrumbs);
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
            
            function triggerReveal() {
                // Double rAF ensures the browser paints the opacity:0 state before adding .is-loaded
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        reveal(img);
                    });
                });
            }

            if (img.complete && img.naturalWidth > 0) { triggerReveal(); return; }
            function done() {
                triggerReveal();
                img.removeEventListener('load', done);
                img.removeEventListener('error', done);
            }
            img.addEventListener('load', done);
            img.addEventListener('error', done); // a broken image must not stay hidden
        }

        function scan(scope) {
            var root = scope || document;
            // The lightbox image reveals itself (preload-gated focus-pull), so it
            // must NOT get .is-loaded here — that rule would override its .is-ready state.
            // Note: .zk-card-img and .zk-hero-img are now native lazy-loaded <img> tags.
            root.querySelectorAll('.page__content img:not(.zk-lightbox-img), .zk-card-img, .zk-hero-img, .zk-book-img').forEach(watchImg);
        }

        scan(document);

        /* Re-scan after each SPA navigation (matches the grid/breadcrumb modules). */
        document.addEventListener('zk:viewChange', function () { 
            var v = document.getElementById('view');
            if (v) scan(v); 
        });
    } catch (e) {
        /* Fail open — never leave images invisible if something goes wrong. */
        document.documentElement.classList.remove('zk-img-js');
    }
})();


/* ============================================================
   CINEMATIC PHOTOGRAPHY GALLERY — LOGIC (V4, Perfect Sync)
   ============================================================ */
(function () {
    var REDUCE  = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = null;

    function delay(ms) { return new Promise(function (r) { ms ? setTimeout(r, ms) : r(); }); }
    function preload(url) {
        return new Promise(function (resolve) {
            if (!url) { resolve(); return; }
            var im = new Image();
            im.onload = im.onerror = function () { resolve(); };
            im.src = url;
            if (im.complete) resolve();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!current || !current.isOpen()) return;
        if (e.key === 'Escape') { current.close(); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); current.next(); }
        else if (e.key === 'ArrowLeft')  { e.preventDefault(); current.prev(); }
    });

    function initGallery() {
        var wrap = document.querySelector('.zk-gallery-wrapper');
        if (!wrap) { current = null; return; }
        if (wrap.dataset.zkReady === '1') return;
        var lightbox = document.getElementById('zkLightbox');
        if (!lightbox) { current = null; return; }
        wrap.dataset.zkReady = '1';


// ── Sticky Filters სენსორი ──
        var filters = wrap.querySelector('.zk-gallery-filters');
        if (filters && !wrap.querySelector('.zk-filter-sentinel')) {
            var sentinel = document.createElement('div');
            sentinel.className = 'zk-filter-sentinel';
            sentinel.style.position = 'absolute';
            sentinel.style.height = '0px';
            filters.parentNode.insertBefore(sentinel, filters);

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    filters.classList.toggle('is-stuck', !entry.isIntersecting);
                });
            }, { rootMargin: '-130px 0px 0px 0px' });
            observer.observe(sentinel);
        }
        var grid     = wrap.querySelector('.zk-gallery-grid');
        var buttons  = Array.prototype.slice.call(wrap.querySelectorAll('.zk-filter-btn'));
        var allItems = Array.prototype.slice.call(wrap.querySelectorAll('.zk-gallery-item'));

        var lbImg    = lightbox.querySelector('.zk-lightbox-img');
        var lbExif   = lightbox.querySelector('.zk-lightbox-exif');
        var closeBtn = lightbox.querySelector('.zk-lightbox-close');
        var prevBtn  = lightbox.querySelector('.zk-lightbox-prev');
        var nextBtn  = lightbox.querySelector('.zk-lightbox-next');
        var thumbs   = document.getElementById('zkLightboxThumbs');

        var activeItems = allItems.slice();
        var thumbEls    = [];
        var index       = 0;
        var swapToken   = 0;
        var filterToken = 0;
        var lastFocus   = null;

        /* ---- Thumbnail strip: batched build (one reflow) + delegated clicks ---- */
        function buildThumbnails() {
            var frag = document.createDocumentFragment();
            thumbEls = [];
            activeItems.forEach(function (item) {
                var src  = item.querySelector('img');
                var cell = document.createElement('div');
                cell.className = 'zk-lightbox-thumb-item';
                var im = document.createElement('img');
                im.src = src.getAttribute('data-thumb') || src.currentSrc || src.src;
                im.loading  = 'lazy';
                im.decoding = 'async';
                im.alt = '';
                cell.appendChild(im);
                frag.appendChild(cell);
                thumbEls.push(cell);
            });
            thumbs.innerHTML = '';
            thumbs.appendChild(frag);
        }

        thumbs.addEventListener('click', function (e) {
            var cell = e.target.closest('.zk-lightbox-thumb-item');
            if (!cell) return;
            e.stopPropagation();
            var i = thumbEls.indexOf(cell);
            if (i > -1) show(i);
        });

        // ── 1. სინქრონიზაციის გასწორება (50ms დაყოვნება) ──
        function centerThumb(cell, isInitial) {
            if (!cell) return;
            setTimeout(function() {
                var target = cell.offsetLeft - (thumbs.clientWidth - cell.offsetWidth) / 2;
                target = target < 0 ? 0 : target;

                if (isInitial) {
                    var prevBehavior = thumbs.style.scrollBehavior;
                    thumbs.style.scrollBehavior = 'auto'; // პირველ გახსნაზე სმუზს ვთიშავთ
                    thumbs.scrollLeft = target;
                    requestAnimationFrame(function() {
                        thumbs.style.scrollBehavior = prevBehavior; // ვრთავთ უკან
                    });
                } else {
                    thumbs.scrollLeft = target;
                }
            }, 50);
        }

        function markActiveThumb(isInitial) {
            for (var i = 0; i < thumbEls.length; i++) {
                thumbEls[i].classList.toggle('is-active', i === index);
            }
            centerThumb(thumbEls[index], isInitial);
        }

        /* ---- Image swap: preload → focus-pull. No flash; rapid swaps are safe. ---- */
        function show(i, opts) {
            opts = opts || {};
            var n = activeItems.length;
            if (!n) return;
            index = ((i % n) + n) % n; // infinite wrap, NaN-proof

            // ── 2. აქტიური თამბნეილის მომენტალური მონიშვნა ──
            markActiveThumb(opts.initial);

            var img   = activeItems[index].querySelector('img');
            var full  = img.getAttribute('data-full');
            var exif  = img.getAttribute('data-exif') || '';
            var token = ++swapToken;
            var hadImage = lbImg.classList.contains('is-ready');

            lbImg.classList.remove('is-ready'); // begin exit (or stay hidden on open)
            var waitExit = (hadImage && !opts.initial && !REDUCE) ? 260 : 0;

            Promise.all([preload(full), delay(waitExit)]).then(function () {
                if (token !== swapToken) return; // a newer swap superseded this one
                lbImg.src = full;
                lbImg.alt = img.alt || '';
                lbExif.textContent = exif;

                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        if (token === swapToken) lbImg.classList.add('is-ready');
                    });
                });
            });
        }
        function next() { show(index + 1); }
        function prev() { show(index - 1); }
        function open(i) {
            lastFocus = document.activeElement;
            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.classList.add('zk-lb-open');
            show(i, { initial: true });
            if (closeBtn) closeBtn.focus({ preventScroll: true });
        }
        function close() {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('zk-lb-open');
            lbImg.classList.remove('is-ready');
            swapToken++;
            setTimeout(function () {
                if (!lightbox.classList.contains('is-open')) lbImg.removeAttribute('src');
            }, 600);
            if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
        }
        function isOpen() { return lightbox.classList.contains('is-open'); }

        function settleHide(item, myToken) {
            var done = false;
            function finish() {
                if (done) return;
                done = true;
                item.removeEventListener('transitionend', onEnd);
                clearTimeout(timer);
                if (myToken === filterToken && item.classList.contains('is-hidden')) {
                    item.style.display = 'none';
                    item.classList.remove('is-animating');
                }
            }
            function onEnd(e) { if (e.propertyName === 'opacity') finish(); }
            item.addEventListener('transitionend', onEnd);
            var timer = setTimeout(finish, 650);
        }

        function applyFilter(filter) {
            var myToken = ++filterToken;
            var toShow  = [];
            activeItems = [];

            allItems.forEach(function (item) {
                var match  = (filter === 'all' || item.getAttribute('data-category') === filter);
                var hidden = item.style.display === 'none' || item.classList.contains('is-hidden');
                if (match) {
                    activeItems.push(item);
                    if (hidden) {
                        item.classList.add('is-animating');
                        if (item.style.display === 'none') item.style.display = '';
                        toShow.push(item);
                    }
                } else if (!hidden) {
                    item.classList.add('is-animating', 'is-hidden');
                    settleHide(item, myToken);
                }
            });

            if (toShow.length) {
                void grid.offsetWidth;
                requestAnimationFrame(function () {
                    if (myToken !== filterToken) return;
                    toShow.forEach(function (item) { item.classList.remove('is-hidden'); });
                });
            }
            buildThumbnails();
        }

        grid.addEventListener('transitionend', function (e) {
            if (e.propertyName !== 'opacity') return;
            var item = e.target.closest('.zk-gallery-item');
            if (item && !item.classList.contains('is-hidden')) item.classList.remove('is-animating');
        });

        /* ---- Wire up Filters ---- */
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.classList.contains('is-active')) return;

                buttons.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-pressed', 'true');

                var newFilter = btn.getAttribute('data-filter');
                // 1. ვუშვებთ ფილტრაციის ლოგიკას
                applyFilter(newFilter);

                // 2. URL Hash-ის განახლება
                if (newFilter === 'all') {
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                } else {
                    history.replaceState(null, null, '#' + newFilter.replace('filter-', ''));
                }

                // 3. ── ანიმაციური სქროლი ზედაპირზე (სადაც ჰედერი ჩანს) ──
                if (window.scrollY > 100) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });

        grid.addEventListener('click', function (e) {
            var item = e.target.closest('.zk-gallery-item');
            if (!item) return;

            // ── VISUAL SORTING MAGIC (ადამიანური კითხვის ლოგიკა) ──
            // ვასწავლით სისტემას, დაალაგოს ფოტოები ეკრანზე მათი რეალური პოზიციების მიხედვით
            activeItems.sort(function(a, b) {
                // ვყოფთ სივრცეს 200px-იან ვირტუალურ ჰორიზონტალურ რიგებად
                var rowA = Math.round(a.offsetTop / 200);
                var rowB = Math.round(b.offsetTop / 200);

                if (rowA === rowB) {
                    return a.offsetLeft - b.offsetLeft; // თუ ერთ რიგშია, მიდის მარცხნიდან მარჯვნივ
                }
                return rowA - rowB; // თუ სხვადასხვა რიგშია, მიდის ზემოდან ქვემოთ
            });

            // რადგან თანმიმდევრობა ვიზუალურად დავალაგეთ, თამბნეილებს თავიდან ვხატავთ
            buildThumbnails();

            var i = activeItems.indexOf(item);
            if (i > -1) open(i);
        });

        if (nextBtn)  nextBtn.addEventListener('click', function (e) { e.stopPropagation(); next(); });
        if (prevBtn)  prevBtn.addEventListener('click', function (e) { e.stopPropagation(); prev(); });
        if (closeBtn) closeBtn.addEventListener('click', close);
        lightbox.addEventListener('click', function (e) { if (e.target === lightbox) close(); });

        var hash = window.location.hash.replace('#', '');
        if (hash === 'camera' || hash === 'mobile') {
            var targetBtn = wrap.querySelector('.zk-filter-btn[data-filter="filter-' + hash + '"]');
            if (targetBtn) {
                buttons.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                targetBtn.classList.add('is-active');
                targetBtn.setAttribute('aria-pressed', 'true');
                applyFilter('filter-' + hash);
            } else {
                buildThumbnails();
            }
        } else {
            buildThumbnails();
        }

        current = { isOpen: isOpen, next: next, prev: prev, close: close };
    }

    initGallery();
    document.addEventListener('zk:viewChange', initGallery);
})();


/* ============================================================
   MUSIC TIMELINE - DATA & LOGIC
   ============================================================ */
(function() {
    // ── 1. შენი მუსიკალური მონაცემთა ბაზა ──
    // ── 1. შენი მუსიკალური მონაცემთა ბაზა (ახლა უკვე დინამიური!) ──
    // ვამოწმებთ, მოგვაწოდა თუ არა PHP-მ მონაცემები, თუ არა — ცარიელ მასივს ვტოვებთ.
    var musicData = typeof zkDynamicMusicData !== 'undefined' ? zkDynamicMusicData : [];

    // ── 2. დარენდერების ძრავა ──
    // ── 2. დარენდერების ძრავა (SPA Proof) ──
    function renderTimeline() {
        var container = document.getElementById('zkMusicTimeline');

        // თუ კონტეინერი არ არის, ან უკვე დარენდერებულია — ვჩერდებით
        if (!container || container.dataset.rendered === 'true') return;

        // ── 1. მონაცემების ამოღება პირდაპირ HTML ატრიბუტიდან ──
        var rawData = container.getAttribute('data-music-payload');
        if (!rawData) return;

        var musicData = [];
        try {
            musicData = JSON.parse(rawData); // ტექსტს ვაქცევთ უკან JS ობიექტად
        } catch(e) {
            console.error("Music Timeline Error: ვერ წავიკითხე მონაცემები", e);
            return;
        }

        var html = '<div class="zk-timeline-line"></div>';

        musicData.forEach(function(item) {
            var embedHtml = '';
            if (item.mediaType === 'youtube' && item.mediaId) {
                embedHtml = '<div class="zk-timeline-embed youtube"><iframe src="https://www.youtube.com/embed/' + item.mediaId + '?rel=0&modestbranding=1" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
            } else if (item.mediaType === 'spotify' && item.mediaId) {
                embedHtml = '<div class="zk-timeline-embed spotify"><iframe src="https://open.spotify.com/embed/track/' + item.mediaId + '" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe></div>';
            }

            var hasSpotify = !!item.spotifyUrl;
            var spotifyClass = hasSpotify ? '' : 'is-disabled';
            var spotifyTag = hasSpotify ? 'a' : 'div';
            var spotifyHref = hasSpotify ? `href="${item.spotifyUrl}" target="_blank" rel="noopener noreferrer"` : '';
            var btnText = hasSpotify ? 'Listen on Spotify' : 'Coming to Spotify';
            var equalizer = `<div class="zk-equalizer"><span class="eq-bar eq-1"></span><span class="eq-bar eq-2"></span><span class="eq-bar eq-3"></span></div>`;

            var spotifyBtnHtml = `
                <${spotifyTag} class="zk-spotify-btn ${spotifyClass}" ${spotifyHref}>
                    <svg class="spotify-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.24 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.24 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.6.18-1.2.72-1.381 4.26-1.261 11.28-1.02 15.721 1.621.54.3.72.96.42 1.5-.3.54-.96.72-1.56.36z"/></svg>
                    <span class="btn-text">${btnText}</span>
                    ${hasSpotify ? equalizer : ''}
                </${spotifyTag}>
            `;

            var moreBtnHtml = item.moreUrl ? `
                <a href="${item.moreUrl}" target="_blank" rel="noopener noreferrer" class="zk-more-btn">
                    See more 
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            ` : '';

            // 🔴 ვშლით ტექსტს მძიმეებით, ვაშორებთ ზედმეტ სიცარიელეებს და ვქმნით ცალკეულ თეგებს
            var genreHtml = '';
            if (item.genre) {
                var genresArray = item.genre.split(',').map(function(g) { return g.trim(); }).filter(Boolean);
                var tagsHtml = genresArray.map(function(g) {
                    return '<span class="zk-timeline-tag">' + g + '</span>';
                }).join('');
                // ვსვამთ საერთო კონტეინერში
                genreHtml = '<div class="zk-timeline-tags-wrapper">' + tagsHtml + '</div>';
            }

            html += `
                <div class="zk-timeline-node">
                    <div class="zk-timeline-point"><div class="zk-point-core"></div></div>
                    <div class="zk-timeline-card">
                        <div class="zk-card-header">
                            <span class="zk-timeline-date">${item.displayDate}</span>
                            ${genreHtml}
                        </div>
                        <h3 class="zk-timeline-title">${item.title}</h3>
                        <p class="zk-timeline-subtitle">${item.subtitle}</p>
                        <div class="zk-timeline-body">${item.description}</div>
                        ${embedHtml}
                        <div class="zk-card-actions">
                            ${spotifyBtnHtml}
                            ${moreBtnHtml}
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
        container.dataset.rendered = 'true';
    }

    renderTimeline();

    // ეს უზრუნველყოფს, რომ სხვა გვერდიდან გადმოსვლისას მუდმივად შეიმოწმოს და დარენდერდეს
    document.addEventListener('zk:viewChange', renderTimeline);
})();



/* ============================================================
   ABOUT PAGE - TABS LOGIC
   ============================================================ */
(function() {
    function initTabs() {
        const nav = document.querySelector('.zk-tabs-nav');
        if (!nav) return;
        const container = document.querySelector('.zk-tabs-nav-container');
        if (container && !document.querySelector('.zk-tabs-sentinel')) {
            const sentinel = document.createElement('div');
            sentinel.className = 'zk-tabs-sentinel';
            sentinel.style.position = 'absolute';
            sentinel.style.height = '0px';
            // სენსორს ვსვამთ კონტეინერის ზუსტად ზემოთ
            container.parentNode.insertBefore(sentinel, container);

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    // თუ სენსორი აცდა ეკრანის ზედა ნაწილს (-85px-ზე, რაც უდრის header-ის სიმაღლეს + დაშორებას)
                    container.classList.toggle('is-stuck', !entry.isIntersecting);
                });
            }, { rootMargin: '-85px 0px 0px 0px' });
            observer.observe(sentinel);
        }

        const buttons = nav.querySelectorAll('.zk-tab-btn');
        const highlight = nav.querySelector('.zk-tab-highlight');
        const panels = document.querySelectorAll('.zk-tab-panel');

        // საწყისი პოზიციის დაყენება
        function setHighlight(btn) {
            highlight.style.width = btn.offsetWidth + 'px';
            highlight.style.transform = `translateX(${btn.offsetLeft - 6}px)`; // 6px არის padding
        }

        // ვამოწმებთ URL-ის პარამეტრს (hash)
        const hash = window.location.hash.replace('#', '');
        let activeBtn = nav.querySelector('.zk-tab-btn.active');
        
        if (hash) {
            const targetBtn = nav.querySelector(`.zk-tab-btn[data-target="tab-${hash}"]`);
            if (targetBtn) {
                activeBtn = targetBtn;
                buttons.forEach(b => b.classList.remove('active'));
                activeBtn.classList.add('active');
                
                panels.forEach(panel => {
                    if (panel.id === `tab-${hash}`) panel.classList.add('active');
                    else panel.classList.remove('active');
                });
            }
        }

        // ვპოულობთ აქტიურს და ვსვამთ ფონს
        if (activeBtn) setHighlight(activeBtn);

        // კლიკის ივენთები
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                // 1. ღილაკების კლასების შეცვლა
                buttons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // 2. Highlight-ის გადაადგილება
                setHighlight(this);

                // 3. პანელების შეცვლა
                const targetId = this.getAttribute('data-target');
                panels.forEach(panel => {
                    if (panel.id === targetId) {
                        panel.classList.add('active');
                    } else {
                        panel.classList.remove('active');
                    }
                });
                
                // 4. URL-ის განახლება ისე, რომ გვერდი არ დარესტარტდეს (SPA-ს სტილში)
                const newHash = targetId.replace('tab-', '');
                history.replaceState(null, null, '#' + newHash);

                // 5. ანიმაციური სქროლი დასაწყისში ტაბის შეცვლისას
                if (window.scrollY > 150) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    // SPA თავსებადობა
    initTabs();
    document.addEventListener('zk:viewChange', initTabs);
})();

/* ============================================================
   ABOUT PAGE — Life & Captures gallery
   No dedicated logic: it emits the same markup as [zk_photography]
   (grid + #zkLightbox), so the cinematic gallery IIFE above
   (initGallery + its #view observer) loads & previews it identically.
   ============================================================ */

/* ============================================================
   CINEMATIC HERO PARALLAX & STARFIELD
   ============================================================ */
(function() {
    let hero = null;
    let ambient = null;
    let inner = null;
    let rafId = null;

    let mouseX = 0;
    let mouseY = 0;
    let currentX = 0;
    let currentY = 0;

    // Starfield variables
    let canvas = null;
    let ctx = null;
    let width = 0;
    let height = 0;
    const numStars = 800;
    let stars = [];
    const baseSpeed = 0.6;
    let targetScrollSpeed = 0;
    let currentScrollSpeed = 0;
    let warpIndicator = null;

    function initHero() {
        hero = document.querySelector('.hero');
        if (!hero) {
            cancelAnimationFrame(rafId);
            return;
        }
        ambient = hero.querySelector('.hero-ambient');
        inner = hero.querySelector('.hero-inner');
        canvas = document.getElementById('zk-starfield');
        
        if (canvas) {
            ctx = canvas.getContext('2d');
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
            
            stars = [];
            for (let i = 0; i < numStars; i++) {
                stars.push(newStar(false)); // random z initially
            }
            
            setTimeout(() => {
                if (canvas) canvas.classList.add('is-active');
            }, 100);
        }

        warpIndicator = document.getElementById('warp-speed');
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('wheel', onWheel, { passive: true });
        animate();
    }

    function resizeCanvas() {
        if (!canvas || !hero) return;
        width = hero.clientWidth;
        height = hero.clientHeight;
        const dpr = window.devicePixelRatio || 1;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);
    }

    function newStar(resetZ = false) {
        return {
            x: (Math.random() - 0.5) * width * 2,
            y: (Math.random() - 0.5) * height * 2,
            z: resetZ ? width : Math.random() * width,
            pz: 0,
            color: randomStarColor()
        };
    }

    function randomStarColor() {
        const r = Math.random();
        if (r < 0.15) return 'rgba(200, 220, 255, 0.9)'; // Blueish
        if (r < 0.3)  return 'rgba(255, 240, 200, 0.9)'; // Yellowish
        if (r < 0.4)  return 'rgba(255, 180, 200, 0.8)'; // Pinkish
        return 'rgba(255, 255, 255, 0.8)'; // White
    }

    function drawStars() {
        if (!ctx) return;
        ctx.clearRect(0, 0, width, height);
        
        // Shift vanishing point slightly based on parallax for insane 3D depth
        const cx = width / 2 - currentX * 2;
        const cy = height / 2 - currentY * 2;
        
        // Speed multiplier increases when mouse moves further from center
        const speedBoost = Math.abs(currentX) * 0.05 + Math.abs(currentY) * 0.05;
        const speed = baseSpeed + speedBoost + currentScrollSpeed;
        
        for (let i = 0; i < numStars; i++) {
            let s = stars[i];
            s.pz = s.z;
            s.z -= speed;
            
            if (s.z <= 0) {
                stars[i] = newStar(true);
                continue;
            }
            
            // 3D Projection
            let x = (s.x / s.z) * 150 + cx;
            let y = (s.y / s.z) * 150 + cy;
            
            // Previous position for streak (motion blur)
            let px = (s.x / s.pz) * 150 + cx;
            let py = (s.y / s.pz) * 150 + cy;
            
            // Size scales as it gets closer
            let r = Math.max(0.1, (1 - s.z / width) * 2.5);
            
            ctx.beginPath();
            ctx.strokeStyle = s.color;
            ctx.lineWidth = r;
            ctx.moveTo(px, py);
            ctx.lineTo(x, y);
            ctx.stroke();
        }
    }

    function onMouseMove(e) {
        if (!hero) return;
        const x = e.clientX / window.innerWidth - 0.5;
        const y = e.clientY / window.innerHeight - 0.5;
        
        mouseX = x * 30; // Max movement in pixels
        mouseY = y * 30;
    }

    function onWheel(e) {
        if (!hero) return;
        targetScrollSpeed += Math.abs(e.deltaY) * 0.02;
        if (targetScrollSpeed > 60) targetScrollSpeed = 60;
    }

    function animate() {
        if (!hero) return;
        
        // Decay target speed (friction)
        targetScrollSpeed *= 0.95;
        if (targetScrollSpeed < 0.01) targetScrollSpeed = 0;
        
        // Smoothly approach target
        currentScrollSpeed += (targetScrollSpeed - currentScrollSpeed) * 0.1;
        
        if (warpIndicator) {
            const displaySpeed = (1 + currentScrollSpeed * 0.5).toFixed(1);
            warpIndicator.textContent = `WARP: ${displaySpeed}X`;
            if (currentScrollSpeed > 5) {
                warpIndicator.style.color = 'rgba(255,255,255,0.9)';
                warpIndicator.style.textShadow = '0 0 10px rgba(255,255,255,0.8)';
            } else {
                warpIndicator.style.color = '';
                warpIndicator.style.textShadow = '';
            }
        }
        
        // Smooth interpolation
        currentX += (mouseX - currentX) * 0.1;
        currentY += (mouseY - currentY) * 0.1;

        if (inner) {
            inner.style.transform = `translate(${-currentX}px, ${-currentY}px)`;
        }
        if (ambient) {
            ambient.style.transform = `translate(${currentX}px, ${currentY}px) scale(1.05)`;
        }

        drawStars();

        rafId = requestAnimationFrame(animate);
    }

    function cleanup() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('wheel', onWheel);
        window.removeEventListener('resize', resizeCanvas);
        cancelAnimationFrame(rafId);
        hero = null;
        canvas = null;
        ctx = null;
    }

    initHero();
    document.addEventListener('zk:viewChange', () => {
        cleanup();
        initHero();
    });
})();