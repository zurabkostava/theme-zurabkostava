/* Interface dictionary: editorial content and document language are independent. */
(function () {
    'use strict';
    const storageKey = 'zk-ui-language';
    const georgian = {
        'Home': 'მთავარი', 'About': 'ჩემ შესახებ', 'About Me': 'ჩემ შესახებ',
        'Music': 'მუსიკა', 'Visual': 'ვიზუალური', 'Visual Art': 'ვიზუალური ხელოვნება',
        'Art': 'ხელოვნება', 'Books': 'წიგნები', 'Blog': 'ბლოგი', 'Blogs': 'ბლოგი',
        'Projects': 'პროექტები', 'Tools': 'ხელსაწყოები', 'Contact': 'კონტაქტი',
        'Photography': 'ფოტოგრაფია', 'Video': 'ვიდეო', 'Videos': 'ვიდეოები',
        'More': 'მეტი', 'Search': 'ძებნა', 'Search projects...': 'პროექტების ძებნა...',
        'Open menu': 'მენიუს გახსნა', 'Open Menu': 'მენიუს გახსნა', 'Close menu': 'მენიუს დახურვა',
        'Primary': 'მთავარი ნავიგაცია', 'Previous': 'წინა', 'Next': 'შემდეგი', 'Topics:': 'თემები:',
        'Sort by:': 'დალაგება:', 'Newest': 'უახლესი', 'Oldest': 'უძველესი',
        'Archive': 'არქივი', 'Topic': 'თემა', 'Category': 'კატეგორია', 'Search Results': 'ძიების შედეგები',
        'Error 404': 'შეცდომა 404', 'Not found': 'ვერ მოიძებნა',
        'This page doesn’t exist yet.': 'ეს გვერდი ჯერ არ არსებობს.',
        'No posts found for this topic.': 'ამ თემაზე ჩანაწერები ვერ მოიძებნა.',
        'No posts found in this category.': 'ამ კატეგორიაში ჩანაწერები ვერ მოიძებნა.',
        'Multidisciplinary Artist From The Solar System': 'მრავალმხრივი ხელოვანი მზის სისტემიდან',
        'Born:': 'დაბადება:', 'Origin:': 'წარმოშობა:', 'Base:': 'ადგილმდებარეობა:',
        'Studio:': 'სტუდია:', 'Position:': 'პოზიცია:', 'Archetype:': 'შემოქმედებითი პროფილი:'
    };
    let language = document.documentElement.lang || 'en';
    try { 
        let stored = localStorage.getItem(storageKey);
        if (stored === 'ka' || stored === 'en') {
            localStorage.setItem(storageKey, language);
        }
    } catch (_) {}
    const originals = new WeakMap();
    const attributeOriginals = new WeakMap();
    const selector = '[data-zk-ui], #primaryNav a, #primaryNav .dropdown-trigger, #zk-bottom-nav .zk-bottom-nav-item span, .hero-sub, .zk-tags-label, .zk-nav-label, .zk-sort-label, .zk-sort-current, .zk-sort-option, .zk-vital-text strong';
    function t(text) { return language === 'ka' ? (georgian[text] || text) : text; }
    function translate(value) { return value.replace(value.trim(), t(value.trim())); }
    function apply() {
        document.documentElement.dataset.uiLanguage = language;
        document.querySelectorAll(selector).forEach(function (element) {
            // Direct label nodes only: retain icons and never walk article bodies.
            element.childNodes.forEach(function (node) {
                if (node.nodeType !== 3) return;
                let original = originals.get(node);
                if (!original) {
                    original = node.nodeValue;
                    const english = Object.keys(georgian).find(key => georgian[key] === original.trim());
                    if (english) original = original.replace(original.trim(), english);
                    originals.set(node, original);
                }
                const next = translate(original);
                if (node.nodeValue !== next) node.nodeValue = next;
                if (georgian[original.trim()]) element.setAttribute('lang', language);
            });
        });
        document.querySelectorAll('#navToggle, #primaryNav, #zk-mobile-menu-trigger, .zk-search-input').forEach(function (element) {
            let saved = attributeOriginals.get(element);
            if (!saved) { saved = {}; attributeOriginals.set(element, saved); }
            ['aria-label', 'placeholder'].forEach(function (attr) {
                const value = element.getAttribute(attr);
                if (value === null) return;
                if (!(attr in saved)) saved[attr] = value;
                if (element.id === 'navToggle' && attr === 'aria-label') {
                    saved[attr] = element.getAttribute('aria-expanded') === 'true' ? 'Close menu' : 'Open menu';
                }
                let next = t(saved[attr]);
                if (language === 'ka' && attr === 'placeholder' && saved[attr].startsWith('Search in ')) next = 'ძებნა: ' + saved[attr].slice(10);
                if (value !== next) element.setAttribute(attr, next);
            });
        });
        document.querySelectorAll('[data-ui-language]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.uiLanguage === language));
        });
    }
    function setLanguage(value, redirectUrl = null) {
        if (value !== 'en' && value !== 'ka') return;
        
        if (language === value && !redirectUrl) return; 

        language = value;
        try { localStorage.setItem(storageKey, language); } catch (_) {}
        
        if (redirectUrl) {
            window.location.href = redirectUrl;
        } else {
            apply();
        }
    }
    window.ZKUI = { t: t, apply: apply, setLanguage: setLanguage };
    document.documentElement.dataset.uiLanguage = language;
    function initialize() {
        document.querySelectorAll('.zk-language-switch').forEach(element => { element.hidden = false; });
        document.querySelectorAll('[data-ui-language]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                setLanguage(button.dataset.uiLanguage, button.dataset.url);
            });
        });
        apply();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
    document.addEventListener('zk:viewChange', apply);
    window.addEventListener('storage', function (event) {
        if (event.key === storageKey || event.key === null) {
            language = event.newValue === 'ka' ? 'ka' : 'en';
            apply();
        }
    });
})();
