/* ============================================================

   ✅ SAFE BRUTAL FIX (NO MutationObserver, NO loops)

   Paste at VERY TOP of book-new.js (before anything)

   ============================================================ */
(function() {
    let ran = false;

    function isKaUrl() {
        try {
            return (location.pathname.startsWith("/ka/") || new URLSearchParams(location.search).get("lang") === "ka" || document.documentElement.lang === "ka");
        } catch (e) {
            return location.pathname.startsWith("/ka/");
        }
    }

    function markNoTranslate(el) {
        if (!el) return;
        el.setAttribute("data-no-dynamic-translation", "");
        el.setAttribute("data-no-translation", "");
        el.setAttribute("translate", "no");
        el.classList.add("skiptranslate", "notranslate");
        // force visible instantly (kills 3–4s pop-in)
        el.style.visibility = "visible";
        el.style.opacity = "1";
    }

    function protectCriticalUI() {
        // IDs (fast)
        markNoTranslate(document.getElementById("lang-switcher-btn"));
        markNoTranslate(document.querySelector(".site-title"));
        markNoTranslate(document.getElementById("sidebar-main-title"));
        markNoTranslate(document.querySelector(".glossary-content"));
        markNoTranslate(document.querySelector(".desc-modal-content"));

        // ✅ ეს დავამატეთ: TranslatePress აღარ შეეხება Auth მოდალს!
        markNoTranslate(document.querySelector(".auth-modal-content"));

        const langBtn = document.getElementById("lang-switcher-btn");
        // 🚀 ✅ დაამატე ეს ორი ხაზი ზუსტად აქ:
        markNoTranslate(document.querySelector("#auth-modal .auth-modal-content"));
        markNoTranslate(document.getElementById("audio-proto-toggle"));
        if (langBtn) {
            langBtn.style.display = "flex";
            if (!langBtn.textContent || !langBtn.textContent.trim()) {
                langBtn.textContent = isKaUrl() ? "KA" : "EN";
            }
        }
        const sidebarTitle = document.getElementById("sidebar-main-title");
        if (sidebarTitle && (!sidebarTitle.textContent || !sidebarTitle.textContent.trim())) {
            sidebarTitle.textContent = isKaUrl() ? "სარჩევი" : "CONTENTS";
        }
    }

    function fastPaintTitleFromCache() {
        const wrapper = document.getElementById("book-engine-wrapper");
        const slug = (wrapper && wrapper.getAttribute("data-force-slug")) || (location.hash ? location.hash.slice(1) : null);
        if (!slug) return;
        const raw = localStorage.getItem("cached_book_" + slug);
        if (!raw) return;
        try {
            const data = JSON.parse(raw);
            const isEn = !isKaUrl();
            const title = isEn ? (data.title_en || data.title || "") : (data.title || "");
            const sub = isEn ? (data.subtitle_en || data.subtitle || "") : (data.subtitle || "");
            const tEl = document.getElementById("site-main-title");
            const sEl = document.getElementById("site-sub-title");
            if (tEl && (!tEl.textContent || !tEl.textContent.trim())) tEl.textContent = title;
            if (sEl && (!sEl.textContent || !sEl.textContent.trim())) sEl.textContent = sub;
        } catch (e) {}
    }
    // 🔥 IMPORTANT: never pushState-switch language when using TranslatePress.
    // Always hard-navigate between /ka/... and /...
    function forceHardLanguageSwitch() {
        const btn = document.getElementById("lang-switcher-btn");
        if (!btn) return;
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const path = location.pathname || "/";
            const goingToKa = !path.startsWith("/ka/");
            let nextPath = path;
            if (goingToKa) nextPath = "/ka" + (path.startsWith("/") ? path : "/" + path);
            else nextPath = path.replace(/^\/ka(?=\/)/, "") || "/";
            nextPath = nextPath.replace(/\/{2,}/g, "/");
            location.href = location.origin + nextPath + location.search + location.hash;
        }, true);
    }

    function run() {
        if (ran) return;
        ran = true;
        // run fast + then re-run a couple of times (without observer)
        protectCriticalUI();
        fastPaintTitleFromCache();
        setTimeout(protectCriticalUI, 150);
        setTimeout(protectCriticalUI, 600);
        setTimeout(protectCriticalUI, 1500);
    }
    document.addEventListener("DOMContentLoaded", () => {
        run();
        forceHardLanguageSwitch();
    });
})();

// ============================================================
// ANTI-BLUE HIGHLIGHT (JS LEVEL FIX)
// ============================================================
(function() {
    const style = document.createElement('style');
    style.innerHTML = `
        *, html, body, div, span, a, button, input, textarea, .paper, .book-card, .tool-btn {
            -webkit-tap-highlight-color: rgba(0,0,0,0) !important;
            touch-action: manipulation !important;
            outline: none !important;
        }
    `;
    document.head.appendChild(style);
})();

const supabaseUrl = 'https://cblxbanbssnflgyrzhah.supabase.co';
const supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImNibHhiYW5ic3NuZmxneXJ6aGFoIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjM2Mzk0NDYsImV4cCI6MjA3OTIxNTQ0Nn0.36w4C_Y8TsTJ2ifORlE5vQu-yMHYCCD-Ebetz8CpQ9A';
const sbClient = window.supabase.createClient(supabaseUrl, supabaseKey);

// Cached session — starts immediately, reused everywhere
let _sessionPromise = sbClient.auth.getSession().then(r => r.data.session);
function getCachedSession() {
    return _sessionPromise;
}

// 🚀 გლობალური ცვლადი Cloud-პროგრესისთვის
window.globalUserProgressPercent = null;
let saveProgressTimeout = null;

// ფუნქცია, რომელიც ფონურ რეჟიმში აგზავნის პროგრესს ბაზაში
async function syncProgressToDB(percent) {
    if (saveProgressTimeout) clearTimeout(saveProgressTimeout);

    // 1.5 წამიანი დაყოვნება (ფურცვლის დასრულებას ველოდებით, რომ ბაზა არ გადაიტვირთოს)
    saveProgressTimeout = setTimeout(async () => {
        const session = await getCachedSession();
        if (session && CURRENT_BOOK_SLUG) {
            await sbClient.from('user_progress').upsert({
                user_id: session.user.id,
                book_slug: CURRENT_BOOK_SLUG,
                lang: currentLanguage, // 🚀 ვატანთ მიმდინარე ენას
                progress_percent: percent,
                updated_at: new Date()
            }, { onConflict: 'user_id, book_slug, lang' });
        }
    }, 1500);
}

// 🛑 აუცილებლად დარწმუნდი, რომ ეს მეილი ზუსტად წერია
const ADMIN_EMAIL = "zurabkostava1@gmail.com";

async function checkAdminSession() {
    const session = await getCachedSession();

    if (session && session.user && session.user.email === ADMIN_EMAIL) {
        document.body.classList.add('is-admin');
        const adminElements = document.querySelectorAll('.admin-only, #edit-mode-btn, #create-new-book-btn');
        adminElements.forEach(el => {
            if (el) el.style.setProperty('display', 'flex', 'important');
        });
        return true;
    }

    // 2. თუ სხვაა, ვართმევთ კლასს
    document.body.classList.remove('is-admin');

    // 🚀 3. წაშლის (remove) მაგივრად, ვმალავთ ძალისმიერად (display: none)
    const adminElements = document.querySelectorAll('.admin-only, #edit-mode-btn, #create-new-book-btn');
    adminElements.forEach(el => {
        if (el) el.style.setProperty('display', 'none', 'important');
    });

    return false;
}
const wrapper = document.getElementById('book-engine-wrapper');
const FORCED_SLUG = wrapper ? wrapper.getAttribute('data-force-slug') : null;
const urlParams = new URLSearchParams(window.location.search);
let CURRENT_BOOK_SLUG = FORCED_SLUG || (window.location.hash ? window.location.hash.substring(1) : null);
let currentBookId = null;
const DEFAULT_META = {
    title: "UNTITLED",
    subtitle: "Draft",
    coverImage: null
};
const DEFAULT_CHAPTERS = [{
    id: 'ch1',
    title: "Chapter 1",
    content: `<h2>Chapter 1</h2><p>Start writing...</p>`
}];
const isKaPath = window.location.pathname.includes('/ka/');
const isKaParam = urlParams.get('lang') === 'ka';
let currentLanguage = (isKaPath || isKaParam) ? 'ka' : 'en';
let editorLanguage = currentLanguage;
let allPageData = [];
let chaptersData = [];
let bookMeta = {};
let quill;
let selectedChapterIndex = 0;
let paperToChapterMap = [];
let isEditingSettings = false;
let botExposureTimer = null;
let footnoteQuill = null; // გლობალური ცვლადი პატარა ედიტორისთვის
/* =======================================================

   ☠️ BRUTAL FIX: INSTANT UI FORCE-RENDER (NO DELAY) ☠️

   ======================================================= */
(function brutalFix() {
    try {
        const isKaBrutal = window.location.pathname.includes('/ka/') || window.location.search.includes('lang=ka');
        const style = document.createElement('style');
        style.innerHTML = `
            #lang-switcher-btn, #site-main-title, #site-sub-title, #sidebar-main-title, 
            .desc-modal-content, .glossary-content,
            /* 🚀 ✅ დაამატე ეს ორი სელექტორი: */
            #auth-modal .auth-modal-content, 
            #audio-proto-toggle {
                opacity: 1 !important;
                visibility: visible !important;
                display: flex !important;
                transition: none !important;
                animation: none !important;
            }
            /* 🚀 ✅ ეს აუცილებელია, რომ აუდიო პლეერის გახსნისას ღილაკი დაიმალოს */
            #audio-proto-toggle.hidden {
                display: none !important;
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }
            /* TranslatePress-ის მიერ დამატებული loading კლასების გაუქმება */
            .trp-loading, .skiptranslate, .notranslate { opacity: 1 !important; }
        `;
        document.head.appendChild(style);
        // 3. ელემენტების მოხელთება
        const t_el = document.getElementById('site-main-title');
        const s_el = document.getElementById('site-sub-title');
        const sb_el = document.getElementById('sidebar-main-title');
        const l_btn = document.getElementById('lang-switcher-btn');
        // 4. TranslatePress-ის დაბლოკვა ამ ელემენტებზე (კლასის მიცემა)
        if (t_el) t_el.parentElement.classList.add('skiptranslate');
        if (sb_el) sb_el.parentElement.classList.add('skiptranslate');
        // 5. ტექსტების მომენტალური გადაწერა LocalStorage-დან (თუ არსებობს)
        // ვცდილობთ სლაგის გამოთვლას ლოკალურად
        let slugRaw = window.location.hash ? window.location.hash.substring(1) : null;
        const wrapRaw = document.getElementById('book-engine-wrapper');
        if (wrapRaw && wrapRaw.getAttribute('data-force-slug')) slugRaw = wrapRaw.getAttribute('data-force-slug');
        if (slugRaw) {
            const cachedRaw = localStorage.getItem('cached_book_' + slugRaw);
            if (cachedRaw) {
                const data = JSON.parse(cachedRaw);
                // ტექსტების ჩასხმა "უხეშად"
                if (isKaBrutal) {
                    if (l_btn) {
                        l_btn.innerText = "KA";
                        l_btn.style.display = 'flex';
                    }
                    if (t_el) t_el.innerText = data.title;
                    if (s_el) s_el.innerText = data.subtitle;
                    if (sb_el) sb_el.innerText = "სარჩევი";
                } else {
                    if (l_btn) {
                        l_btn.innerText = "EN";
                        l_btn.style.display = 'flex';
                    }
                    if (t_el) t_el.innerText = data.title_en || data.title;
                    if (s_el) s_el.innerText = data.subtitle_en || data.subtitle;
                    if (sb_el) sb_el.innerText = "CONTENTS";
                }
            }
        }
    } catch (e) {
        /* error silenced for production */
    }
})();
/* ======================================================= */
/* ============================

   QUILL CUSTOM BLOT: FOOTNOTE

   ============================ */
const Inline = Quill.import('blots/inline');
class FootnoteBlot extends Inline {
    static create(value) {
        let node = super.create();
        node.setAttribute('class', 'footnote-trigger');
        // Value შეიძლება იყოს ობიექტი {content: "...", title: "..."} ან უბრალოდ სტრინგი
        if (typeof value === 'object' && value !== null) {
            node.setAttribute('data-content', value.content);
            // თუ სათაური მოგვაწოდეს, ვწერთ, თუ არა - ცარიელი რჩება (და მერე ტექსტს აიღებს)
            if (value.title) node.setAttribute('data-title', value.title);
        } else {
            node.setAttribute('data-content', value);
        }
        return node;
    }
    static formats(node) {
        const content = node.getAttribute('data-content');
        // მხოლოდ ნამდვილი footnote-ები: კლასი + კონტენტი უნდა ჰქონდეს
        // თუ არა - undefined ვაბრუნებთ, რომ paste-ზე ჩვეულებრივ span-ებს არ ეხუროს ფორმატი
        if (!content || content === 'null' || !node.classList.contains('footnote-trigger')) {
            return undefined;
        }
        return {
            content,
            title: node.getAttribute('data-title')
        };
    }
}
FootnoteBlot.blotName = 'footnote';
FootnoteBlot.tagName = 'span';
Quill.register(FootnoteBlot);

function debounce(func, timeout = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            func.apply(this, args);
        }, timeout);
    };
}
const debouncedRender = debounce(() => {
    if (CURRENT_BOOK_SLUG) renderBook();
}, 300);
document.addEventListener("DOMContentLoaded", async () => {
    function addLibHomeButton() {
        const root = document.getElementById("digital-library-root");
        if (!root) return;

        const nav = root.querySelector(".nav-toolbar");
        if (!nav) return;

        if (root.querySelector("#lib-home-btn")) return;

        const a = document.createElement("a");
        a.id = "lib-home-btn";
        a.className = "lib-home-btn notranslate skiptranslate";
        a.setAttribute("translate", "no");
        a.setAttribute("data-no-translation", "");
        a.setAttribute("data-no-dynamic-translation", "");

        // 🛠️ დინამიური სტილები იდეალური ვიზუალისთვის
        a.style.display = "inline-flex";
        a.style.alignItems = "center";
        a.style.justifyContent = "center";
        a.style.gap = "8px"; // დაშორება იკონსა და ტექსტს შორის

        const isKa = location.pathname.startsWith("/ka/") ||
            document.documentElement.lang === "ka" ||
            document.body.classList.contains("lang-ka");

        a.href = isKa ? "https://zurabkostava.com/ka/books" : "https://zurabkostava.com/books";

        // 🎨 იკონი და ტექსტი ერთი ფერით
        a.innerHTML = `
        <span class="material-icons-outlined" style="font-size: 18px; color: inherit; display: block;">open_in_new</span>
        <span style="line-height: 1; color: inherit; font-weight: inherit;">LIB</span>
    `;

        a.title = isKa ? "მთავარ საიტზე" : "Go to main site";
        a.rel = "noopener";

        nav.appendChild(a);
    }
    // Already inside DOMContentLoaded — run directly
    addLibHomeButton();
    setTimeout(addLibHomeButton, 200);
    setTimeout(addLibHomeButton, 800);
    // ============================
    // ✅ INSTANT FIX: PRIORITY UI UPDATES
    // ============================
    // 1. ენის ღილაკის ტექსტის მომენტალური დაფიქსირება
    const langBtn = document.getElementById('lang-switcher-btn');
    if (langBtn) {
        // თუ url-ში ka-ა, ეგრევე დააწერე KA, არ დაელოდო არაფერს
        langBtn.innerText = currentLanguage.toUpperCase();
        langBtn.style.display = 'block'; // გამოაჩინე მომენტალურად
        langBtn.onclick = () => {
            // ... (აქ ძველი ლოგიკა რჩება) ...
            if (FORCED_SLUG) {
                const origin = window.location.origin;
                let path = window.location.pathname;
                if (currentLanguage === 'en') {
                    if (!path.startsWith('/ka')) path = '/ka' + path;
                    path = path.replace('//', '/');
                    window.location.href = origin + path + '';
                } else {
                    path = path.replace('/ka', '');
                    if (path === '') path = '/';
                    path = path.replace('//', '/');
                    window.location.href = origin + path;
                }
            } else {
                currentLanguage = currentLanguage === 'ka' ? 'en' : 'ka';
                editorLanguage = currentLanguage;
                langBtn.innerText = currentLanguage.toUpperCase();
                document.body.classList.remove('loaded');
                const loader = document.getElementById('book-loader');
                if (loader) loader.classList.remove('hidden');
                updateStaticUI();
                renderBook().then(() => {
                    setTimeout(() => {
                        if (loader) loader.classList.add('hidden');
                        document.body.classList.add('loaded');
                    }, 300);
                });
            }
        };
    }
    // 2. სათაურის მომენტალური წამოღება LocalStorage-დან (სანამ ბაზა იტვირთება)
    if (CURRENT_BOOK_SLUG) {
        const cachedString = localStorage.getItem('cached_book_' + CURRENT_BOOK_SLUG);
        if (cachedString) {
            try {
                const parsed = JSON.parse(cachedString);
                applyBookData(parsed); // მონაცემების ჩასხმა
                updateStaticUI(); // ✅ სათაურის დახატვა მომენტალურად!
            } catch (e) {
                /* cache error silenced */
            }
        }
    }
    // ============================
    // 🌓 SMART THEME LOGIC (იგივე რჩება)
    // ============================
    const themeBtn = document.getElementById('theme-toggle-btn');
    const savedTheme = localStorage.getItem('book_theme');
    const systemPrefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
    const shouldBeLight = savedTheme === 'light' || (!savedTheme && systemPrefersLight);
    if (shouldBeLight) {
        document.body.classList.add('light-mode');
        if (themeBtn) themeBtn.innerHTML = '<span class="material-icons-outlined">dark_mode</span>';
    } else {
        if (themeBtn) themeBtn.innerHTML = '<span class="material-icons-outlined">light_mode</span>';
    }
    if (themeBtn) {
        themeBtn.onclick = () => {
            document.body.classList.toggle('light-mode');
            const isLight = document.body.classList.contains('light-mode');
            themeBtn.innerHTML = isLight ? '<span class="material-icons-outlined">dark_mode</span>' : '<span class="material-icons-outlined">light_mode</span>';
            localStorage.setItem('book_theme', isLight ? 'light' : 'dark');
        };
    }
    // ... დანარჩენი ინიციალიზაცია ...
    const btnMinus = document.getElementById('font-size-minus');
    const btnPlus = document.getElementById('font-size-plus');
    let currentFontSize = parseFloat(localStorage.getItem('user_font_size')) || 0.95;
    const MIN_FONT = 0.7;
    const MAX_FONT = 1.4;
    const STEP = 0.05;

    function updateFontSize(newSize) {
        if (newSize < MIN_FONT || newSize > MAX_FONT) return;
        currentFontSize = parseFloat(newSize.toFixed(2));
        const rootElement = document.getElementById('digital-library-root');
        if (rootElement) {
            rootElement.style.setProperty('--p-font-size', currentFontSize + 'rem');
        }
        if (btnMinus) btnMinus.onclick = () => updateFontSize(currentFontSize - STEP);
        if (btnPlus) btnPlus.onclick = () => updateFontSize(currentFontSize + STEP);
        localStorage.setItem('user_font_size', currentFontSize);
        debouncedRender();
    }
    document.documentElement.style.setProperty('--p-font-size', currentFontSize + 'rem');
    if (btnMinus) btnMinus.onclick = () => updateFontSize(currentFontSize - STEP);
    if (btnPlus) btnPlus.onclick = () => updateFontSize(currentFontSize + STEP);
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggle-btn');
    const openSidebarBtn = document.getElementById('open-sidebar-btn');

    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-closed');
    }
    if (toggleBtn) toggleBtn.onclick = toggleSidebar;
    if (openSidebarBtn) openSidebarBtn.onclick = toggleSidebar;
    document.addEventListener('click', (e) => {
        if (window.innerWidth > 768) return;
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('open-sidebar-btn');
        if (!sidebar.classList.contains('collapsed') && !sidebar.contains(e.target) && !openBtn.contains(e.target)) {
            toggleSidebar();
        }
    });
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        document.body.classList.add('sidebar-closed');
    }
    window.onresize = debouncedRender;
    if (!FORCED_SLUG) {
        window.addEventListener('hashchange', () => window.location.reload());
    }
    // Auth runs in parallel — does NOT block book rendering
    setupAdminAuth();
    setupUserAuth();
    if (!CURRENT_BOOK_SLUG) initLibraryMode();
    else await initReaderMode();
    if (CURRENT_BOOK_SLUG) {
        setTimeout(initShareButton, 500); // მცირე დაყოვნება, რომ UI ბოლომდე ჩაიტვირთოს
    }
    // DESCRIPTION MODAL LOGIC
    const descBtn = document.getElementById('open-desc-btn');
    const descModal = document.getElementById('description-modal');
    const closeDescBtn = document.getElementById('close-desc-modal');
    const descBody = document.getElementById('description-body');
    if (descBtn && descModal && descBody) {
        descBtn.onclick = () => {
            // ვირჩევთ ტექსტს ენის მიხედვით
            let text = (currentLanguage === 'en') ? (bookMeta.description_en || bookMeta.description) : bookMeta.description;
            // სათაურის შეცვლა ენის მიხედვით
            const headerTitle = descModal.querySelector('h3');
            if (headerTitle) headerTitle.innerText = (currentLanguage === 'en') ? "SYNOPSIS" : "სინოპსისი";
            if (!text || text.trim() === "") {
                text = (currentLanguage === 'en') ? "No description available." : "აღწერა დამატებული არ არის.";
            }
            descBody.innerText = text; // უსაფრთხო ტექსტი
            descModal.classList.add('active');
        };
        if (closeDescBtn) {
            closeDescBtn.onclick = () => descModal.classList.remove('active');
        }
        descModal.onclick = (e) => {
            if (e.target === descModal) descModal.classList.remove('active');
        };
    }
});
async function setupAdminAuth() {
    const loginBtn = document.getElementById('admin-login-btn');
    if (!loginBtn) return;
    const isAdmin = await checkAdminSession();
    loginBtn.innerText = isAdmin ? "🔓" : "🔒";
    loginBtn.onclick = async () => {
        if (document.body.classList.contains('is-admin')) {
            if (confirm("Logout?")) {
                await sbClient.auth.signOut();
                document.body.classList.remove('is-admin');
                loginBtn.innerText = "🔒";
                window.location.reload();
            }
        } else {
            const password = prompt("Enter Master Password:");
            if (password !== null) {
                loginBtn.innerText = "⏳";
                const {
                    data,
                    error
                } = await sbClient.auth.signInWithPassword({
                    email: ADMIN_EMAIL,
                    password: password
                });
                if (error) {
                    alert("Access Denied: " + error.message);
                    loginBtn.innerText = "🔒";
                } else {
                    document.body.classList.add('is-admin');
                    loginBtn.innerText = "🔓";
                    window.location.reload();
                }
            }
        }
    };
}

/* ============================================================
   👤 USER AUTHENTICATION ENGINE (Supabase) - ATOMIC STABILITY
   ============================================================ */
async function setupUserAuth() {
    const authBtn = document.getElementById('user-auth-btn');
    const authModal = document.getElementById('auth-modal');
    const closeBtn = document.getElementById('close-auth-modal');

    const nameGroup = document.getElementById('auth-name-group');
    const nameInput = document.getElementById('auth-name');
    const emailInput = document.getElementById('auth-email');
    const passwordInput = document.getElementById('auth-password');

    const submitBtn = document.getElementById('auth-submit-btn');
    const toggleLink = document.getElementById('auth-toggle-link');
    const toggleText = document.getElementById('auth-toggle-text');
    const title = document.getElementById('auth-modal-title');
    const errorMsg = document.getElementById('auth-error');
    const googleBtn = document.getElementById('auth-google-btn');
    const googleText = document.getElementById('auth-google-text');

    let isLoginMode = true;

    // 1. მომენტალური UI განახლება სესიის მიხედვით
    const updateAuthUI = (session) => {
        if (!authBtn) return;

        // Wrapper-ის და Dropdown-ის მომზადება (მხოლოდ ერთხელ)
        let wrapper = document.getElementById('user-menu-wrapper');
        let dropdown = document.getElementById('user-dropdown');

        if (!wrapper && authBtn.parentNode) {
            wrapper = document.createElement('div');
            wrapper.id = 'user-menu-wrapper';
            wrapper.className = 'notranslate skiptranslate';
            wrapper.style.position = 'relative';
            wrapper.style.display = 'inline-flex';

            authBtn.parentNode.insertBefore(wrapper, authBtn);
            wrapper.appendChild(authBtn);

            dropdown = document.createElement('div');
            dropdown.id = 'user-dropdown';
            dropdown.className = 'user-dropdown-menu notranslate skiptranslate';
            wrapper.appendChild(dropdown);
        }

        if (session) {
            authBtn.classList.add('logged-in-avatar');
            const userMeta = session.user.user_metadata || {};
            const avatarUrl = userMeta.avatar_url || userMeta.picture;
            let fullName = userMeta.full_name || userMeta.display_name || session.user.email.split('@')[0];

            let avatarHTML = avatarUrl ? `<img src="${avatarUrl}" alt="Profile" class="avatar-img">` : `<div class="avatar-initials">U</div>`;
            authBtn.innerHTML = `<span class="user-display-name">${fullName}</span>${avatarHTML}`;

            if (dropdown) {
                const logoutText = currentLanguage === 'ka' ? "გამოსვლა" : "Sign Out";
                dropdown.innerHTML = `<button class="dropdown-item logout-item" id="dropdown-logout-btn"><span class="material-icons-outlined">logout</span>${logoutText}</button>`;
                dropdown.style.display = 'flex';

                // 🚀 ატომური გამოსვლა
                document.getElementById('dropdown-logout-btn').onclick = async (e) => {
                    e.preventDefault(); e.stopPropagation();
                    if (confirm(currentLanguage === 'ka' ? "ნამდვილად გსურთ გამოსვლა?" : "Are you sure?")) {
                        await sbClient.auth.signOut();
                        window.location.assign(window.location.origin + window.location.pathname); // სუფთა რესეტი
                    }
                };
            }

            wrapper.onmouseenter = () => dropdown.classList.add('show');
            wrapper.onmouseleave = () => dropdown.classList.remove('show');
        } else {
            authBtn.classList.remove('logged-in-avatar');
            const btnText = currentLanguage === 'ka' ? "შესვლა" : "Sign In";
            authBtn.innerHTML = `<span class="material-icons-outlined" style="font-size: 18px !important;">login</span><span class="auth-btn-text" style="margin-left: 5px !important;">${btnText}</span>`;

            if (wrapper) { wrapper.onmouseenter = null; wrapper.onmouseleave = null; }
            if (dropdown) { dropdown.classList.remove('show'); dropdown.style.display = 'none'; }
        }
    };

    // სესიის ინიციალიზაცია
    const session = await getCachedSession();
    updateAuthUI(session);

    // 🚀 მთავარი ღილაკის კლიკი
    authBtn.onclick = (e) => {
        e.preventDefault();
        if (authBtn.classList.contains('logged-in-avatar')) {
            document.getElementById('user-dropdown')?.classList.toggle('show');
        } else {
            isLoginMode = true;
            updateModalTexts();
            errorMsg.style.display = 'none';
            authModal.classList.add('active');
        }
    };

    // 🚀 შესვლის/რეგისტრაციის ფორმის გაგზავნა
    submitBtn.onclick = async () => {
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const fullName = nameInput ? nameInput.value.trim() : '';

        if (!email || !password || (!isLoginMode && !fullName)) {
            showError(currentLanguage === 'ka' ? "შეავსეთ ყველა ველი" : "Fill all fields");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = currentLanguage === 'ka' ? "მოიცადეთ..." : "Processing...";

        const { data, error } = isLoginMode
            ? await sbClient.auth.signInWithPassword({ email, password })
            : await sbClient.auth.signUp({ email, password, options: { data: { display_name: fullName, full_name: fullName } } });

        if (error) {
            showError(error.message);
            submitBtn.disabled = false;
            updateModalTexts();
        } else {
            // ✅ წარმატების შემთხვევაში ეგრევე ვაძალებთ გვერდს გადატვირთვას
            window.location.reload();
        }
    };

    // დანარჩენი დამხმარე ფუნქციები (Google Login, Toggle და ა.შ.)
    if (googleBtn) {
        googleBtn.onclick = async (e) => {
            e.preventDefault();
            await sbClient.auth.signInWithOAuth({ provider: 'google', options: { redirectTo: window.location.origin + window.location.pathname } });
        };
    }

    if (toggleLink) {
        toggleLink.onclick = (e) => {
            e.preventDefault();
            isLoginMode = !isLoginMode;
            updateModalTexts();
        };
    }

    if (closeBtn) closeBtn.onclick = () => authModal.classList.remove('active');
    function showError(msg) { errorMsg.innerText = msg; errorMsg.style.display = 'block'; }
    function updateModalTexts() {
        const isKa = currentLanguage === 'ka';
        const nameGroup = document.getElementById('auth-name-group');

        // 1. ინფუთების ლეიბლები
        document.getElementById('auth-email-label').innerText = isKa ? "ელ-ფოსტა" : "Email Address";
        document.getElementById('auth-pass-label').innerText = isKa ? "პაროლი" : "Password";

        const nameLabel = document.getElementById('auth-name-label');
        if (nameLabel) nameLabel.innerText = isKa ? "სახელი და გვარი" : "Full Name";

        // 2. რეჟიმების მართვა
        if (isLoginMode) {
            title.innerText = isKa ? "შესვლა" : "Sign In";
            submitBtn.innerText = isKa ? "შესვლა" : "Sign In";

            // 🚀 ახალი ტექსტი: "Don't have an account?"-ის ნაცვლად
            toggleText.innerText = ""; // ამას ვასუფთავებთ
            toggleLink.innerText = isKa ? "იმეილით რეგისტრაცია" : "Register with Email";

            if (nameGroup) nameGroup.style.setProperty('display', 'none', 'important');
        } else {
            title.innerText = isKa ? "რეგისტრაცია" : "Create Account";
            submitBtn.innerText = isKa ? "რეგისტრაცია" : "Sign Up";

            // რეგისტრაციის დროს უკან დაბრუნების ტექსტი
            toggleText.innerText = isKa ? "უკვე გაქვთ ანგარიში?" : "Already have an account?";
            toggleLink.innerText = isKa ? "შესვლა" : "Sign In";

            if (nameGroup) nameGroup.style.setProperty('display', 'flex', 'important');
        }
    }
}
async function initLibraryMode() {
    if (FORCED_SLUG) return;
    document.getElementById('library-view').classList.add('active');
    document.getElementById('main-content').style.display = 'none';
    // ✅ NEW: ვმალავთ საიდბარს და ვეუბნებით CSS-ს, რომ "დაკეტილია"
    document.getElementById('sidebar').style.display = 'none';
    document.body.classList.add('sidebar-closed'); // <--- აი ეს ასწორებს ტულბარს!
    document.getElementById('lang-switcher-btn').style.display = 'none';
    const grid = document.getElementById('books-grid');
    const createBtn = document.getElementById('create-new-book-btn');
    grid.innerHTML = '<p style="color:#666;">Loading library...</p>';
    const {
        data,
        error
    } = await sbClient.from('book_projects').select('id, title, cover_image, slug').order('id', {
        ascending: false
    });
    if (error) {
        grid.innerHTML = `<p style="color:red;">Error: ${error.message}</p>`;
        return;
    }
    grid.innerHTML = '';
    if (data.length === 0) grid.innerHTML = '<p style="color:#444;">No books found.</p>';
    data.forEach(book => {
        const card = document.createElement('div');
        card.className = 'book-card';
        const contentHtml = book.cover_image ? `<img src="${book.cover_image}" alt="${book.title}">` : `<div class="no-cover-placeholder">${book.title}</div>`;
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'delete-book-btn admin-only';
        deleteBtn.innerHTML = '&times;';
        deleteBtn.onclick = async (e) => {
            e.stopPropagation();
            if (confirm(`Delete "${book.title}"?`)) {
                card.style.opacity = '0.5';
                const {
                    error: delErr
                } = await sbClient.from('book_projects').delete().eq('id', book.id);
                if (!delErr) card.remove();
                else alert("Delete failed");
            }
        };
        card.innerHTML = contentHtml;
        card.appendChild(deleteBtn);
        card.onclick = () => {
            if (book.slug) window.location.hash = book.slug;
            else alert("Slug missing.");
        };
        grid.appendChild(card);
    });
    createBtn.onclick = async () => {
        const title = prompt("Book Title:");
        if (!title) return;
        let slug = title.toLowerCase().trim().replace(/[\s\W-]+/g, '-');
        if (!slug) slug = "untitled";
        createBtn.innerText = "...";
        const {
            data: newBook,
            error: createError
        } = await sbClient.from('book_projects').insert([{
            title: title,
            slug: slug,
            subtitle: "By Zurab Kostava",
            chapters: DEFAULT_CHAPTERS,
            cover_image: null
        }]).select().single();
        if (createError) {
            if (createError.code === '23505') alert("Name exists. Choose another.");
            else alert("Error: " + createError.message);
            createBtn.innerText = "+ New Book";
        } else {
            window.location.hash = newBook.slug;
        }
    };
}
async function initReaderMode() {
    window.INITIAL_URL_HASH = window.location.hash;
    const loader = document.getElementById('book-loader');
    const libView = document.getElementById('library-view');
    if (libView) libView.classList.remove('active');

    // 🚀🚀🚀 NEW: CLOUD SYNC & SMART LANGUAGE DETECT
    const session = await getCachedSession();
    if (session && CURRENT_BOOK_SLUG) {
        try {
            // მოგვაქვს ბოლოს შენახული პროგრესი (იმ ენის, რომელზეც ბოლოს იყო)
            const { data } = await sbClient.from('user_progress')
                .select('progress_percent, lang')
                .eq('user_id', session.user.id)
                .eq('book_slug', CURRENT_BOOK_SLUG)
                .order('updated_at', { ascending: false })
                .limit(1)
                .single();

            // if (data) {
            //     window.globalUserProgressPercent = data.progress_percent;

            //     // 🚀 თუ ბაზაში სხვა ენაა შენახული, ვიდრე ახლა URL-შია, ავტომატურად ვცვლით
            //     if (data.lang !== currentLanguage) {
            //         currentLanguage = data.lang;
            //         editorLanguage = currentLanguage;

            //         const origin = window.location.origin;
            //         let path = window.location.pathname;
            //         if (currentLanguage === 'ka') {
            //             if (!path.startsWith('/ka')) path = '/ka' + path;
            //         } else {
            //             path = path.replace('/ka', '');
            //             if (path === '') path = '/';
            //         }
            //         path = path.replace('//', '/');

            //         // URL-ის შეცვლა ისე, რომ გვერდი არ დარეფრეშდეს
            //         window.history.replaceState(null, '', origin + path + window.location.search + window.location.hash);
            //     }
            // }
        } catch(e) {}
    }
    // ============================================================
    // 1. ღილაკების ინიციალიზაცია (PRIORITY)
    // ============================================================
    // --- EN/KA ღილაკი (INSTANT SWITCH) ---
    const langBtn = document.getElementById('lang-switcher-btn');
    if (langBtn) {
        langBtn.style.display = 'flex';
        langBtn.innerText = currentLanguage.toUpperCase(); // საწყისი ტექსტი
        langBtn.onclick = async (e) => {
            e.preventDefault();
            // 1. ვცვლით ენას ცვლადში
            currentLanguage = (currentLanguage === 'ka') ? 'en' : 'ka';
            editorLanguage = currentLanguage;

            // 🚀 2. უმნიშვნელოვანესი: ვანახლებთ ენას ბაზაში (რომ "Smart Detect"-მა უკან არ დაგვაბრუნოს)
            const session = await getCachedSession();
            if (session && CURRENT_BOOK_SLUG) {
                await sbClient.from('user_progress').upsert({
                    user_id: session.user.id,
                    book_slug: CURRENT_BOOK_SLUG,
                    lang: currentLanguage, // აქ ვინახავთ ახალ არჩევანს
                    updated_at: new Date()
                }, { onConflict: 'user_id, book_slug, lang' });
            }

            // 3. UI-ის მომენტალური განახლება
            updateStaticUI();

            // 4. URL-ის შეცვლა
            const origin = window.location.origin;
            let path = window.location.pathname;
            if (currentLanguage === 'ka') {
                if (!path.startsWith('/ka')) path = '/ka' + path;
            } else {
                path = path.replace('/ka', '');
            }
            path = path.replace('//', '/');
            const newUrl = origin + path + window.location.search + window.location.hash;

            // ვიყენებთ assign-ს (reload-ისთვის), რომ სუფთად ჩაიტვირთოს ახალი ენა
            window.location.assign(newUrl);
        };
    }
    // --- DESCRIPTION BUTTON (MEMORY BASED) ---
    const descBtn = document.getElementById('open-desc-btn');
    const descModal = document.getElementById('description-modal');
    const closeDescBtn = document.getElementById('close-desc-modal');
    const descBody = document.getElementById('description-body');
    if (descBtn) {
        descBtn.style.display = 'flex';
        descBtn.onclick = (e) => {
            e.preventDefault();
            // ვიღებთ პირდაპირ მეხსიერებიდან (არანაირი await)
            const isEn = (currentLanguage === 'en');
            let text = "";
            if (bookMeta) {
                text = isEn ? (bookMeta.description_en || bookMeta.description) : bookMeta.description;
            }
            const headerTitle = descModal.querySelector('h3');
            if (headerTitle) headerTitle.innerText = isEn ? "SYNOPSIS" : "სინოპსისი";
            if (!text || text.trim() === "") text = isEn ? "No description available." : "აღწერა დამატებული არ არის.";
            descBody.innerText = text;
            descModal.classList.add('active');
        };
        if (closeDescBtn) closeDescBtn.onclick = () => descModal.classList.remove('active');
        if (descModal) descModal.onclick = (e) => {
            if (e.target === descModal) descModal.classList.remove('active');
        };
    }
    // --- GLOSSARY BUTTON ---
    const glossaryBtn = document.getElementById('open-glossary-btn');
    const glossaryModal = document.getElementById('glossary-modal');
    const closeGlossary = document.getElementById('close-glossary-modal');
    if (glossaryBtn) {
        glossaryBtn.style.display = 'flex';
        glossaryBtn.onclick = (e) => {
            e.preventDefault();
            if (typeof window.renderGlossary === 'function') window.renderGlossary();
            glossaryModal.classList.add('active');
        };
        if (closeGlossary) closeGlossary.onclick = () => glossaryModal.classList.remove('active');
        if (glossaryModal) glossaryModal.onclick = (e) => {
            if (e.target === glossaryModal) glossaryModal.classList.remove('active');
        };
    }
    // --- LIBRARY ICON ---
    const libraryIcon = document.getElementById('go-to-library-icon');
    if (FORCED_SLUG) {
        if (libraryIcon) libraryIcon.style.display = 'none';
    } else {
        if (libraryIcon) {
            libraryIcon.style.display = 'block';
            libraryIcon.onclick = () => {
                history.pushState("", document.title, window.location.pathname + window.location.search);
                window.location.reload();
            };
        }
    }
    // --- EDIT BUTTON ---
    const editBtn = document.getElementById('edit-mode-btn');
    if (editBtn) editBtn.style.display = '';
    // ============================================================
    // 2. მონაცემების ჩატვირთვა (CACHE FIRST Strategy)
    // ============================================================
    initQuill();
    setupEditorEvents();
    setupAllButtons(); // ღილაკების მიბმა
    // ვცდილობთ ქეშის გამოყენებას
    const cachedData = localStorage.getItem('cached_book_' + CURRENT_BOOK_SLUG);
    let isCached = false;
    if (cachedData) {
        try {
            const parsed = JSON.parse(cachedData);
            // 🛑 ჯერ მონაცემებს ვავსებთ
            applyBookData(parsed);
            // 🛑 მერე ეგრევე ვხატავთ UI-ს (რომ ჰარდ რეფრეშზეც არ დაიგვიანოს)
            updateStaticUI();
            isCached = true;
            // ფონტებს ველოდებით და ვხატავთ წიგნს
            document.fonts.ready.then(async () => {
                await renderBook();
                if (loader) loader.classList.add('hidden');
                document.body.classList.add('loaded');
                navigateToHashOnLoad();
            });

        } catch (e) {
            /* cache error silenced */
        }
    }
    // ბაზიდან განახლება (ეს ხდება ბოლოს, ჩუმად)
    await loadBookData(CURRENT_BOOK_SLUG, isCached);
// 🚀 AUDIO PROTOTYPE AUTO-START (დაყოვნების გარეშე)
    if (typeof initAudioPrototype === 'function') {
        initAudioPrototype();
    }
}
function applyBookData(data) {
    currentBookId = data.id;
    bookMeta = {
        title: data.title,
        subtitle: data.subtitle,
        coverImage: data.cover_image,
        title_en: data.title_en,
        subtitle_en: data.subtitle_en,
        seo_description: data.seo_description || "",
        seo_description_en: data.seo_description_en || "",
        // ✅ NEW FIELDS
        description: data.description || "",
        description_en: data.description_en || "",
        genre_ka: data.genre_ka || "",
        genre_en: data.genre_en || "",
        published_year: data.published_year || ""
    };
    // ... (დანარჩენი კოდი იგივე რჩება)
    if (data.chapters && data.chapters[0] && data.chapters[0].meta_en) {
        if (!bookMeta.title_en) bookMeta.title_en = data.chapters[0].meta_en.title;
        if (!bookMeta.subtitle_en) bookMeta.subtitle_en = data.chapters[0].meta_en.subtitle;
    }
    chaptersData = data.chapters || DEFAULT_CHAPTERS;
}
async function loadBookData(slug, hasCacheRendered) {
    const loader = document.getElementById('book-loader');
    try {
        const {
            data,
            error
        } = await sbClient.from('book_projects').select('*').eq('slug', slug).single();
        if (error) throw error;
        const newDataString = JSON.stringify(data);
        const cachedString = localStorage.getItem('cached_book_' + slug);
        if (hasCacheRendered && newDataString === cachedString) {
            return;
        }
        localStorage.setItem('cached_book_' + slug, newDataString);
        applyBookData(data);
        updateStaticUI();
        if (!hasCacheRendered) {
            document.fonts.ready.then(async () => {
                await renderBook();
                if (loader) loader.classList.add('hidden');
                document.body.classList.add('loaded');
                navigateToHashOnLoad();
                initAudioPrototype(); // ✅ ეგრევე ვრთავთ!
            });
        } else {
            await renderBook();
        }
    } catch (err) {
        /* load error silenced */
        if (loader) loader.classList.add('hidden');
        if (!hasCacheRendered) {
            if (FORCED_SLUG) alert("Book not found!");
            else {
                alert("Book not found.");
                history.pushState("", document.title, window.location.pathname + window.location.search);
                window.location.reload();
            }
        }
    }
}
async function uploadCoverToStorage(file) {
    if (!file) return null;
    const fileExt = file.name.split('.').pop();
    const fileName = `${Date.now()}_${Math.random().toString(36).substr(2, 9)}.${fileExt}`;
    const filePath = `covers/${fileName}`;
    const {
        data,
        error
    } = await sbClient.storage.from('covers').upload(fileName, file);
    if (error) {
        /* upload error silenced */
        alert("Cover upload failed: " + error.message);
        return null;
    }
    const {
        data: publicData
    } = sbClient.storage.from('covers').getPublicUrl(fileName);
    return publicData.publicUrl;
}

function updateStaticUI() {
    // 1. ენის კლასის შეცვლა
    document.body.classList.remove('lang-ka', 'lang-en');
    document.body.classList.add('lang-' + currentLanguage);

    // 2. ელემენტების პოვნა
    const siteTitleEl = document.getElementById('site-main-title');
    const siteSubEl = document.getElementById('site-sub-title');
    const sidebarHeader = document.getElementById('sidebar-main-title');
    const langBtn = document.getElementById('lang-switcher-btn');

    // 3. ტექსტების განსაზღვრა
    const isEn = (currentLanguage === 'en');
    const langText = isEn ? "EN" : "KA";
    const sidebarText = isEn ? "CONTENTS" : "სარჩევი";

    const title = isEn ? (bookMeta.title_en || bookMeta.title || "LOADING...") : (bookMeta.title || "იტვირთება...");
    const subtitle = isEn ? (bookMeta.subtitle_en || bookMeta.subtitle || "") : (bookMeta.subtitle || "");

    // 4. DOM-ის განახლება
    if (langBtn) langBtn.innerText = langText;

    if (siteTitleEl) {
        siteTitleEl.innerText = title;
        siteTitleEl.parentElement.classList.add('skiptranslate');
    }

    if (siteSubEl) siteSubEl.innerText = subtitle;

    if (sidebarHeader) {
        sidebarHeader.innerText = sidebarText;
        sidebarHeader.parentElement.classList.add('skiptranslate');
    }

}


// როცა ახალ თავს ვტვირთავთ, ბრაუზერს ვეუბნებით რომ ნუმერაცია დაარესეტოს
function resetFootnoteCounters() {
    const book = document.getElementById('book');
    if (book) {
        book.style.counterReset = 'footnote-counter';
    }
}
/* renderBook() #1 removed - was dead code, overridden by the real renderBook() below */

function highlightDifferences(publicHTML, draftHTML) {
    const pubDiv = document.createElement('div');
    pubDiv.innerHTML = publicHTML;
    const draftDiv = document.createElement('div');
    draftDiv.innerHTML = draftHTML;
    const pubNodes = Array.from(pubDiv.children);
    const draftNodes = Array.from(draftDiv.children);
    // ეს არის ჩვენი "კურსორი", რომელიც დაიმახსოვრებს სად გავჩერდით პუბლიკში
    let pubIndex = 0;
    draftNodes.forEach((node) => {
        const draftText = node.innerText.trim();
        // ცარიელ ენთერებს არ ვეხებით
        if (draftText.length === 0) return;
        let matchFound = false;
        // 🔍 ძებნის ლოგიკა:
        // ვეძებთ ამ ტექსტს პუბლიკ მასივში, დაწყებული ბოლო ნაპოვნი ადგილიდან (pubIndex)
        // (შემოვიფარგლოთ 50 აბზაცით წინ, რომ ძალიან შორს არ წავიდეს და არ აირიოს)
        for (let i = pubIndex; i < Math.min(pubIndex + 50, pubNodes.length); i++) {
            const pubText = pubNodes[i].innerText.trim();
            if (pubText === draftText) {
                // ✅ ვიპოვეთ! ესეიგი ეს აბზაცი ძველია და უცვლელი.
                matchFound = true;
                // კურსორს გადავწევთ ნაპოვნი ადგილის შემდეგ
                // რომ შემდეგი აბზაცი უკვე ამის ქვემოთ ვეძებოთ
                pubIndex = i + 1;
                break; // ციკლიდან გამოვდივართ
            }
        }
        // ❌ თუ ვერსად ვიპოვეთ (ვერც იმავე ადგილას, ვერც ქვემოთ)
        if (!matchFound) {
            // ესე იგი ეს აბზაცი ან ახალია, ან შეცვლილია!
            node.classList.add('modified-content');
            // ⚠️ მნიშვნელოვანია: pubIndex-ს აქ არ ვცვლით!
            // რადგან ეს აბზაცი ახალია, შემდეგი აბზაცი ისევ ძველ ადგილას უნდა ვეძებოთ.
        }
    });
    return draftDiv.innerHTML;
}
// ეს ფუნქცია შლის ჩვენს დამატებულ კლასებს, რომ ბაზაში სუფთა ტექსტი წავიდეს
function removeHighlightClasses(htmlContent) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlContent;
    // ვპოულობთ ყველა ელემენტს, რომელსაც აქვს ჩვენი კლასი
    const coloredElements = tempDiv.querySelectorAll('.modified-content');
    coloredElements.forEach(el => {
        el.classList.remove('modified-content');
        // თუ კლასის წაშლის შემდეგ class ატრიბუტი ცარიელი დარჩა, ისიც მოვაშოროთ
        if (el.getAttribute('class') === '') {
            el.removeAttribute('class');
        }
    });
    return tempDiv.innerHTML;
}

function getChapterContent(chapter, lang, useDraft = false) {
    let pub = (lang === 'en') ? (chapter.content_en || "") : (chapter.content || "");
    if (useDraft) {
        // ადმინისთვის: ვაბრუნებთ დრაფტს. თუ დრაფტი არ არის, ვაბრუნებთ პუბლიკს.
        if (lang === 'en') {
            return (chapter.draft_content_en !== undefined) ? chapter.draft_content_en : pub;
        } else {
            return (chapter.draft_content !== undefined) ? chapter.draft_content : pub;
        }
    } else {
        // მკითხველისთვის:
        if (lang === 'en' && !chapter.content_en) {
            return "<p><i>(No English translation yet)</i></p>";
        }
        return pub;
    }
}

function getChapterTitle(chapter, lang) {
    if (lang === 'en') {
        return chapter.title_en || chapter.title;
    }
    return chapter.title;
}
async function generateBookStructure() {
    const container = document.getElementById('measure-container');
    const bookScene = document.querySelector('.book-scene');

    if (!container || !bookScene) return { pages: [], chapterStartMap: [] };

    const rect = bookScene.getBoundingClientRect();

    let safeHeight = rect.height;
    if (safeHeight < 100) {
        /* height fallback silenced */
        safeHeight = window.innerHeight * 0.8;
    }

    container.style.width = (rect.width || window.innerWidth) + 'px';
    container.style.height = safeHeight + 'px';

    const style = getComputedStyle(container);
    const h = safeHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom) - 15;

    if (h < 50) {
        /* height too small silenced */
        return { pages: [{ html: "<h1>Error: Layout too small</h1>", isCover: false }], chapterStartMap: [0] };
    }

    let pages = [];
    let map = [0];
    const isAdmin = document.body.classList.contains('is-admin');

    let displayTitle = bookMeta.title;
    let displaySubtitle = bookMeta.subtitle;
    if (currentLanguage === 'en') {
        displayTitle = bookMeta.title_en || (bookMeta.title + " (EN)");
        displaySubtitle = bookMeta.subtitle_en || bookMeta.subtitle;
    }

    let coverHTML = bookMeta.coverImage ?
        `<img src="${bookMeta.coverImage}" class="cover-img">` :
        `<div class="cover-design"><h1>${displayTitle}</h1><p>${displaySubtitle}</p></div>`;

    pages.push({ html: coverHTML, isCover: true });

    const preloaderDiv = document.createElement('div');
    preloaderDiv.style.position = 'absolute';
    preloaderDiv.style.visibility = 'hidden';
    preloaderDiv.style.width = container.style.width;
    document.body.appendChild(preloaderDiv);

    let currentChapterNumber = 1;

    // 🚀 CPU OPTIMIZATION: ვიყენებთ სტანდარტულ ციკლს
    for (let i = 0; i < chaptersData.length; i++) {
        const ch = chaptersData[i];
        let contentToRender = getChapterContent(ch, currentLanguage, isAdmin);

        if (!contentToRender || contentToRender.trim() === "" || contentToRender === "<p><br></p>") {
            currentChapterNumber++;
            continue;
        }

        const tempContainer = document.createElement('div');
        tempContainer.innerHTML = contentToRender;

        let syncCounter = 0;
        tempContainer.querySelectorAll('p, h1, h2, h3, blockquote, li').forEach(el => {
            if (el.innerText.trim().length > 0) {
                el.classList.add('sync-block', `sync-ch-${currentChapterNumber}`, `sync-id-${syncCounter}`);
                syncCounter++;
            }
        });

        const footnotes = tempContainer.querySelectorAll('.footnote-trigger');
        footnotes.forEach((fn, index) => {
            fn.setAttribute('data-fn-index', index + 1);
        });
        contentToRender = tempContainer.innerHTML;

        const hyph = applyCustomGeorgianHyphenation(contentToRender);

        // 🚀 NETWORK OPTIMIZATION: აქ ამოღებულია waitForImages! ბრაუზერი აღარ ელოდება ფოტოების გადმოწერას.

        const pgs = paginateContent(hyph, h, pages.length);

        const startPage = pages.length;
        if (pgs.length > 0) {
            const isMobile = window.innerWidth <= 768;
            const visualStartPage = isMobile ? startPage : Math.floor(startPage / 2);
            map.push(visualStartPage);
        }
        pgs.forEach(p => pages.push({ html: p, isCover: false }));

        currentChapterNumber++;

        // 🚀 CPU ANTI-FREEZE: სუსტი მოწყობილობებისთვის ვაძლევთ ბრაუზერს "სუნთქვის" (15 მილიწამი) საშუალებას
        await new Promise(resolve => setTimeout(resolve, 15));
    }

    document.body.removeChild(preloaderDiv);
    return { pages, chapterStartMap: map };
}
async function renderBook() {
    const bookContainer = document.getElementById('book');
    bookContainer.innerHTML = '';
    paperToChapterMap = [];
    allPageData = [];
    const {
        pages,
        chapterStartMap
    } = await generateBookStructure();
    const isMobile = window.innerWidth <= 768;
    const totalPapers = isMobile ? pages.length : Math.ceil(pages.length / 2);
    for (let p = 0; p < totalPapers; p++) {
        const face = isMobile ? p : p * 2;
        let chIdx = 0;
        for (let c = 0; c < chapterStartMap.length; c++) {
            if (chapterStartMap[c] <= face) chIdx = c;
            else break;
        }
        paperToChapterMap.push(chIdx);
    }
    for (let i = 0; i < totalPapers; i++) {
        let front, back;
        if (isMobile) {
            front = pages[i];
            back = null;
        } else {
            front = pages[i * 2];
            back = pages[i * 2 + 1];
        }
        const frontNum = isMobile ? (i + 1) : (i * 2 + 1);
        const backNum = isMobile ? '' : (i * 2 + 2);
        let fClass = 'front';
        if (front && front.isCover) fClass += ' hardcover-front';
        let bClass = 'back';
        if (back && back.isCover) bClass += ' hardcover-back';
        // ✅ დავაბრუნეთ სუფთა HTML (კლასების გარეშე)
        const paperHTML = `

        <div class="${fClass}">

            <div class="page-content">${front ? front.html : ''}</div>

            ${(front && !front.isCover) ? `<span class="page-number">${frontNum}</span>` : ''}

        </div>

        <div class="${bClass}">

            <div class="page-content">${back ? back.html : ''}</div>

            ${(back && !back.isCover) ? `<span class="page-number">${backNum}</span>` : ''}

        </div>`;
        allPageData.push(paperHTML);
    }
    buildDynamicSidebar(totalPapers);
    initPhysics(totalPapers);
    setupReaderInteractions();
}

function buildDynamicSidebar(totalPapers) {
    const sidebarList = document.getElementById('chapter-list-ui');
    sidebarList.innerHTML = '';

    // 1. ღილაკის შექმნა სათაურში
    const sidebarHeaderTitle = document.getElementById('sidebar-main-title');
    const oldBtn = document.getElementById('global-toggle-btn');
    if (oldBtn) oldBtn.remove();

    const isMobile = window.innerWidth <= 768;
    const expandedStateKey = 'sidebar_expanded_' + CURRENT_BOOK_SLUG;

    let expandedTitles = [];
    try {
        expandedTitles = JSON.parse(localStorage.getItem(expandedStateKey)) || [];
    } catch (e) {
        expandedTitles = [];
    }

    const toggleAllBtn = document.createElement('span');
    toggleAllBtn.id = 'global-toggle-btn';
    toggleAllBtn.className = "sidebar-toggle-all material-icons-outlined";
    toggleAllBtn.innerText = "unfold_more";
    toggleAllBtn.title = "Expand/Collapse All";
    toggleAllBtn.style.marginLeft = "auto";

    let areAllExpanded = false;

    // 🟢 CLICK LOGIC (განახლებული)
    toggleAllBtn.onclick = (e) => {
        e.stopPropagation();
        e.preventDefault();

        areAllExpanded = !areAllExpanded;
        toggleAllBtn.innerText = areAllExpanded ? "unfold_less" : "unfold_more";

        const allH1s = document.querySelectorAll('.toc-h1');

        // სთორეჯის გასუფთავება (თუ ვკეცავთ)
        if (!areAllExpanded) expandedTitles = [];

        allH1s.forEach(h1 => {
            const arrow = h1.querySelector('.toc-arrow');
            const titleText = h1.querySelector('span:not(.toc-arrow)')?.innerText;

            if (areAllExpanded) {
                // --- EXPAND MODE: ყველაფერს ვხსნით ---
                if (arrow) arrow.classList.remove('collapsed');
                if (titleText && !expandedTitles.includes(titleText)) expandedTitles.push(titleText);
            } else {
                // --- COLLAPSE MODE: ყველაფერს ვკეცავთ ---
                // 🛑 მნიშვნელოვანი: ისარს ყოველთვის ვკეცავთ, მაშინაც კი თუ აქტიურია!
                // ეს აიძულებს სისტემას, რომ ჩართოს "Smart Filter" რეჟიმი.
                if (arrow) arrow.classList.add('collapsed');

                // აქტიური თავი სთორეჯში არ გვინდა (რადგან "შეკეცილი" სტატუსი გვინდა)
                expandedTitles = expandedTitles.filter(t => t !== titleText);
            }
        });

        // Save State
        localStorage.setItem(expandedStateKey, JSON.stringify(expandedTitles));

        // 🟢 UI Update Loop (Smart Visibility)
        // ეს უზრუნველყოფს, რომ შეკეცილ მდგომარეობაში აქტიური ქვეთავი დარჩეს
        allH1s.forEach(h1 => {
            const arrow = h1.querySelector('.toc-arrow');
            const isCollapsed = arrow && arrow.classList.contains('collapsed');

            let sibling = h1.nextElementSibling;
            while(sibling) {
                if (sibling.classList.contains('toc-h1') || sibling.classList.contains('toc-cover')) break;

                if (isCollapsed) {
                    // 🛑 თუ შეკეცილია: მხოლოდ აქტიური რჩება!
                    if (sibling.classList.contains('active')) {
                        sibling.classList.remove('visual-hidden');
                    } else {
                        sibling.classList.add('visual-hidden');
                    }
                } else {
                    // ✅ თუ გაშლილია: ყველა ჩანს
                    sibling.classList.remove('visual-hidden');
                }
                sibling = sibling.nextElementSibling;
            }
        });
    };

    if (sidebarHeaderTitle) sidebarHeaderTitle.appendChild(toggleAllBtn);

    // 2. COVER ITEM
    const coverLi = document.createElement('li');
    coverLi.className = "toc-h1 toc-cover";
    coverLi.setAttribute('data-virtual-id', -1);
    const coverText = document.createElement('span');
    coverText.innerText = (currentLanguage === 'en') ? "Cover" : "გარეკანი";
    coverText.style.flex = "1";
    coverLi.appendChild(coverText);

    coverLi.onclick = () => {
        const event = new CustomEvent('book-nav', {
            detail: { pageIndex: 0, total: totalPapers, side: 'front' }
        });
        document.dispatchEvent(event);
        closeSidebarMobile();
    };
    sidebarList.appendChild(coverLi);

    // 3. CHAPTERS GENERATION (უცვლელი)
    allPageData.forEach((htmlString, paperIndex) => {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlString;
        const headings = tempDiv.querySelectorAll('h1, h2');

        headings.forEach(heading => {
            if (heading.classList.contains('split-continuation')) return;
            const li = document.createElement('li');
            const fullText = heading.getAttribute('data-full-text');
            const labelText = fullText ? fullText : heading.innerText;
            const tagName = heading.tagName.toLowerCase();
            li.classList.add(`toc-${tagName}`);
            const isBack = isMobile ? false : (heading.closest('.back') !== null);
            const side = isBack ? 'back' : 'front';
            let virtualId = isMobile ? paperIndex : (paperIndex * 2) + (isBack ? 1 : 0);
            li.setAttribute('data-virtual-id', virtualId);
            li.setAttribute('data-paper-index', paperIndex); // 🟢 ვინახავთ ფურცლის ზუსტ ნომერს
            // ვქმნით URL-სთვის მორგებულ სახელს და ვანიჭებთ ელემენტს
            const itemSlug = createChapterSlug(labelText) || `page-${virtualId}`;
            li.setAttribute('data-slug', itemSlug);
            const textSpan = document.createElement('span');
            textSpan.innerText = labelText;
            textSpan.title = labelText;
            li.appendChild(textSpan);

            if (tagName === 'h1') {
                const arrow = document.createElement('span');
                arrow.className = 'toc-arrow material-icons-outlined';
                arrow.innerText = 'expand_more';
                const isExpanded = expandedTitles.includes(labelText);
                if (!isExpanded) arrow.classList.add('collapsed');

                arrow.onclick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    arrow.classList.toggle('collapsed');
                    const isNowHidden = arrow.classList.contains('collapsed');
                    if (isNowHidden) {
                        expandedTitles = expandedTitles.filter(t => t !== labelText);
                    } else {
                        if (!expandedTitles.includes(labelText)) expandedTitles.push(labelText);
                    }
                    localStorage.setItem(expandedStateKey, JSON.stringify(expandedTitles));

                    // ინდივიდუალური ისრის ლოგიკა
                    let nextSibling = li.nextElementSibling;
                    while (nextSibling) {
                        if (nextSibling.classList.contains('toc-h1') || nextSibling.classList.contains('toc-cover')) break;
                        const isActive = nextSibling.classList.contains('active');
                        if (isNowHidden) {
                            if (isActive) nextSibling.classList.remove('visual-hidden');
                            else nextSibling.classList.add('visual-hidden');
                        } else {
                            nextSibling.classList.remove('visual-hidden');
                        }
                        nextSibling = nextSibling.nextElementSibling;
                    }
                };
                li.appendChild(arrow);
            }
            li.onclick = () => {
                const event = new CustomEvent('book-nav', {
                    detail: { pageIndex: paperIndex, total: totalPapers, side: side }
                });
                document.dispatchEvent(event);
                closeSidebarMobile();
            };
            sidebarList.appendChild(li);
        });
    });

    // 4. INITIAL RENDER STATE (აქაც ვამოწმებთ აქტიურს)
    const arrows = sidebarList.querySelectorAll('.toc-arrow');
    arrows.forEach(arrow => {
        if (arrow.classList.contains('collapsed')) {
            const li = arrow.parentElement;
            let nextSibling = li.nextElementSibling;
            while (nextSibling) {
                if (nextSibling.classList.contains('toc-h1') || nextSibling.classList.contains('toc-cover')) break;
                if (!nextSibling.classList.contains('active')) {
                    nextSibling.classList.add('visual-hidden');
                }
                nextSibling = nextSibling.nextElementSibling;
            }
        }
    });
}

function applyCustomGeorgianHyphenation(html) {
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;

    function traverse(node) {
        if (node.nodeType === 3) {
            const words = node.nodeValue.split(' ');
            const processedWords = words.map(word => hyphenateWord(word));
            node.nodeValue = processedWords.join(' ');
        } else {
            for (let child of node.childNodes) traverse(child);
        }
    }
    traverse(tempDiv);
    return tempDiv.innerHTML;
}

function hyphenateWord(word) {
    if (word.length < 5) return word;
    if (!/[ა-ჰ]/.test(word)) return word;
    const vowels = "აეიოუ";
    const isV = (c) => vowels.includes(c);
    const isC = (c) => !vowels.includes(c) && c !== undefined;
    let result = "";
    let chars = word.split('');
    for (let i = 0; i < chars.length; i++) {
        result += chars[i];
        if (i >= chars.length - 2) continue;
        if (i < 1) continue;
        let cur = chars[i],
            next = chars[i + 1],
            after = chars[i + 2],
            prev = chars[i - 1];
        if (isV(cur) && isV(next)) {
            result += '\u00AD';
            continue;
        }
        if (isV(cur) && isC(next) && isV(after)) {
            result += '\u00AD';
            continue;
        }
        if (isC(cur) && isC(next)) {
            if (isV(prev)) {
                result += '\u00AD';
                continue;
            }
        }
    }
    return result;
}

function paginateContent(htmlContent, maxContentHeight, startPageIndex = 0) {
    const measureContainer = document.getElementById('measure-container');
    measureContainer.innerHTML = '';
    const innerMeasurer = document.createElement('div');
    innerMeasurer.style.width = '100%';
    innerMeasurer.style.margin = '0';
    innerMeasurer.style.padding = '0';
    innerMeasurer.style.overflow = 'hidden';
    measureContainer.appendChild(innerMeasurer);
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = htmlContent;
    let nodesQueue = Array.from(tempDiv.children);
    let pages = [];
    let currentPageContent = document.createElement('div');

    while (nodesQueue.length > 0) {
        let node = nodesQueue.shift();
        const imgElement = node.querySelector('img') || (node.tagName === 'IMG' ? node : null);
        if (imgElement) {
            if (currentPageContent.innerHTML.trim() !== '') {
                pages.push(currentPageContent.innerHTML);
                currentPageContent = document.createElement('div');
            }
            if (window.innerWidth > 768) {
                const currentTotalPages = startPageIndex + pages.length;
                if (currentTotalPages % 2 === 0) {
                    pages.push('<div class="spacer-page" style="width:100%;height:100%;"></div>');
                }
            }
            const imgSrc = imgElement.getAttribute('src');
            // 🚀 NETWORK OPTIMIZATION: დაემატა loading="lazy", რომ ინტერნეტი არ გაიჭედოს
            // 🚀 GPU OPTIMIZATION: decoding="async" ეუბნება ბრაუზერს, რომ ფოტო ფონურ ძაფზე (thread) გაშიფროს და ანიმაცია არ გაჭედოს.
            const fullPageImgHTML = `<div class="full-page-img-wrapper"><img src="${imgSrc}" loading="lazy" decoding="async"></div>`;
            pages.push(fullPageImgHTML);
            continue;
        }
        innerMeasurer.appendChild(node.cloneNode(true));
        if (innerMeasurer.offsetHeight <= maxContentHeight) {
            currentPageContent.appendChild(node.cloneNode(true));
        } else {
            innerMeasurer.removeChild(innerMeasurer.lastChild);
            const {
                fittedNode,
                remainingNode
            } = splitNodeByWords(node, innerMeasurer, maxContentHeight);
            if (fittedNode) currentPageContent.appendChild(fittedNode);
            pages.push(currentPageContent.innerHTML);
            innerMeasurer.innerHTML = '';
            currentPageContent = document.createElement('div');
            if (remainingNode) nodesQueue.unshift(remainingNode);
        }
    }
    if (currentPageContent.innerHTML.trim() !== '') {
        pages.push(currentPageContent.innerHTML);
    }
    return pages;
}
function splitNodeByWords(originalNode, containerState, limit) {
    if (originalNode.tagName !== 'P' && !originalNode.tagName.startsWith('H') && originalNode.tagName !== 'BLOCKQUOTE') {
        return {
            fittedNode: null,
            remainingNode: originalNode
        };
    }
    const type = originalNode.tagName;
    const fullText = originalNode.innerText;

    // 🛡️ FIX: ვიცავთ HTML ტეგებს გაჭრისგან
    // ტეგების შიგნით არსებულ სფეისებს დროებით ვცვლით '%%SPACE%%'-ით
    const protectedHTML = originalNode.innerHTML.replace(/<[^>]+>/g, (tag) => {
        return tag.replace(/\s+/g, '%%SPACE%%');
    });

    const words = protectedHTML.split(' ');

    const tempNode = document.createElement(type);
    tempNode.className = originalNode.className;
    containerState.appendChild(tempNode);

    let low = 0;
    let high = words.length;
    let bestFitIndex = 0;

    while (low <= high) {
        const mid = Math.floor((low + high) / 2);
        // აღდგენა: შემოწმებისას ვაბრუნებთ რეალურ სფეისებს
        const testStr = words.slice(0, mid).join(' ').replace(/%%SPACE%%/g, ' ');
        tempNode.innerHTML = testStr;

        if (containerState.offsetHeight <= limit) {
            bestFitIndex = mid;
            low = mid + 1;
        } else {
            high = mid - 1;
        }
    }

    containerState.removeChild(tempNode);

    if (bestFitIndex === 0) {
        return {
            fittedNode: null,
            remainingNode: originalNode
        };
    }

    const fittedNode = document.createElement(type);
    // საბოლოო ჩაწერისას ვაბრუნებთ სფეისებს
    fittedNode.innerHTML = words.slice(0, bestFitIndex).join(' ').replace(/%%SPACE%%/g, ' ');
    fittedNode.className = originalNode.className;
    fittedNode.setAttribute('data-full-text', fullText);

    let remainingNode = null;
    if (bestFitIndex < words.length) {
        remainingNode = document.createElement(type);
        // აქაც ვაბრუნებთ სფეისებს
        remainingNode.innerHTML = words.slice(bestFitIndex).join(' ').replace(/%%SPACE%%/g, ' ');
        remainingNode.className = originalNode.className;
        remainingNode.classList.add('split-continuation');
    }

    return {
        fittedNode,
        remainingNode
    };
}

// 🚀 ამოწმებს ლინკს და შლის წიგნს შესაბამის თავზე (Smart Memory Fix)
function navigateToHashOnLoad() {
    const hashToUse = window.INITIAL_URL_HASH || window.location.hash;
    window.INITIAL_URL_HASH = null;

    if (hashToUse) {
        const targetSlug = hashToUse.substring(1);
        const targetItem = document.querySelector(`.chapter-list li[data-slug="${targetSlug}"]`);

        if (targetItem) {
            // 🛑 SMART FIX: თუ ეს თავი უკვე აქტიურია (რადგან localStorage-მა ზუსტ გვერდზე დაგვაბრუნა),
            // აღარ გადავფურცლოთ თავის დასაწყისში! ვინარჩუნებთ ზუსტ გვერდს.
            if (targetItem.classList.contains('active')) {
                return; // ფუნქცია ჩერდება და არაფერს აფუჭებს
            }

            const pIndex = parseInt(targetItem.getAttribute('data-paper-index'));
            const isBack = targetItem.closest('.back') !== null;
            const side = isBack ? 'back' : 'front';

            // ვასიმულირებთ კლიკს (მხოლოდ მაშინ, თუ სხვა თავში გადავდივართ)
            setTimeout(() => {
                const totalPapers = document.querySelectorAll('.paper').length;
                const event = new CustomEvent('book-nav', {
                    detail: { pageIndex: pIndex, total: totalPapers, side: side }
                });
                document.dispatchEvent(event);
            }, 100);
        }
    }
}

// 🔗 URL Slug გენერატორი (მხარს უჭერს ქართულ ასოებს)
// 🔗 URL Slug გენერატორი (ქართულის ლათინურად გადაყვანით)
function createChapterSlug(text) {
    if (!text) return "";
    let slug = text.toString().toLowerCase().trim();

    // 🇬🇪 ქართული ასოების ლათინურზე ტრანსლიტერაციის ლექსიკონი
    const geoMap = {
        'ა':'a', 'ბ':'b', 'გ':'g', 'დ':'d', 'ე':'e', 'ვ':'v', 'ზ':'z',
        'თ':'t', 'ი':'i', 'კ':'k', 'ლ':'l', 'მ':'m', 'ნ':'n', 'ო':'o',
        'პ':'p', 'ჟ':'zh', 'რ':'r', 'ს':'s', 'ტ':'t', 'უ':'u', 'ფ':'f',
        'ქ':'k', 'ღ':'gh', 'ყ':'y', 'შ':'sh', 'ჩ':'ch', 'ც':'ts', 'ძ':'dz',
        'წ':'ts', 'ჭ':'ch', 'ხ':'kh', 'ჯ':'j', 'ჰ':'h'
    };

    // ვცვლით ქართულ ასოებს შესაბამისი ლათინურით
    slug = slug.split('').map(char => geoMap[char] || char).join('');

    return slug
        .replace(/\s+/g, '-') // სფეისები ხდება ტირეები
        .replace(/[^\w\-]+/g, '') // ვტოვებთ მხოლოდ ინგლისურ ასოებს, ციფრებს და ტირეს (ქართული უკვე გადაყვანილია)
        .replace(/\-\-+/g, '-') // ვშლით ზედმეტ ტირეებს
        .replace(/^-+/, '') // ვასუფთავებთ თავში
        .replace(/-+$/, ''); // ვასუფთავებთ ბოლოში
}
function closeSidebarMobile() {
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.add('collapsed');
        document.body.classList.add('sidebar-closed');
    }
}

function initPhysics(totalPapers) {
    const bookContainer = document.getElementById('book');

    // 🚀 1. ვიღებთ პროცენტს ენის მიხედვით: ჯერ Cloud-დან, თუ არა და LocalStorage-დან
    // მაგალითად: book_percent_ka_beta
    const percentStorageKey = `book_percent_${currentLanguage}_${CURRENT_BOOK_SLUG}`;

    let savedPercent = window.globalUserProgressPercent !== null ?
        window.globalUserProgressPercent :
        parseFloat(localStorage.getItem(percentStorageKey));

    let savedLocation = null;

    if (!isNaN(savedPercent) && savedPercent !== null) {
        // პროცენტს (0-დან 1-მდე) ვაქცევთ კონკრეტული მოწყობილობის გვერდად
        savedLocation = Math.floor(savedPercent * totalPapers) + 1;
    } else {
        // Fallback ძველ ვერსიაზე (რომ პროგრესი არ დაეკარგოთ)
        const oldSaved = localStorage.getItem('book_cursor_' + CURRENT_BOOK_SLUG);
        if (oldSaved) savedLocation = parseInt(oldSaved);
    }

    let currentLocation = savedLocation || 1;
    if (currentLocation > totalPapers + 1) currentLocation = 1;
    const maxLocation = totalPapers + 1;
    let mobileShowBack = false;
    let isBusy = false;
    let touchStartX = 0;
    let touchStartY = 0;

    function renderVisiblePapers(loc) {
        const range = 2;
        const start = Math.max(0, loc - range - 1);
        const end = Math.min(totalPapers, loc + range);
        const existingPapers = Array.from(bookContainer.children);
        const existingIds = existingPapers.map(p => parseInt(p.getAttribute('data-index')));
        existingPapers.forEach(p => {
            const idx = parseInt(p.getAttribute('data-index'));
            if (idx < start || idx >= end) {
                p.remove();
            }
        });
        for (let i = start; i < end; i++) {
            if (!existingIds.includes(i)) {
                const paper = document.createElement('div');
                paper.classList.add('paper');
                paper.id = `p${i + 1}`;
                paper.setAttribute('data-index', i);
                paper.innerHTML = allPageData[i];
                if (i < currentLocation - 1) {
                    paper.classList.add('flipped');
                }
                const currentChildren = bookContainer.children;
                let nextSibling = null;
                for (let c = 0; c < currentChildren.length; c++) {
                    if (parseInt(currentChildren[c].getAttribute('data-index')) > i) {
                        nextSibling = currentChildren[c];
                        break;
                    }
                }
                if (nextSibling) {
                    bookContainer.insertBefore(paper, nextSibling);
                } else {
                    bookContainer.appendChild(paper);
                }
                paper.onclick = (e) => {
                    if (window.innerWidth <= 768) return;
                    if (isBusy) return;
                    if ((i + 1) < currentLocation) prevDesk();
                    else nextDesk();
                };
            }
        }
    }

    function syncVisuals(instant = false, targetSide = 'front') {
        renderVisiblePapers(currentLocation);
        const papers = Array.from(bookContainer.children);
        papers.forEach(p => {
            const i = parseInt(p.getAttribute('data-index'));
            if (instant) p.style.transition = 'none';
            if (i < currentLocation - 1) {
                p.classList.add('flipped');
                p.style.zIndex = i;
                if (window.innerWidth <= 768) p.style.display = 'none';
            } else {
                p.classList.remove('flipped');
                p.style.zIndex = totalPapers - i;
                if (window.innerWidth <= 768) p.style.display = 'block';
            }
            p.classList.remove('mobile-view-back');
        });
        if (window.innerWidth <= 768) {
            mobileShowBack = (targetSide === 'back');
            if (mobileShowBack) {
                const currentPaper = papers.find(p => parseInt(p.getAttribute('data-index')) === currentLocation - 1);
                if (currentPaper) currentPaper.classList.add('mobile-view-back');
            }
        } else {
            mobileShowBack = false;
        }
        updateState();
        if (instant) {
            setTimeout(() => {
                papers.forEach(p => p.style.transition = '');
            }, 100);
        }
    }

    document.addEventListener('book-nav', (e) => {
        const { pageIndex, side } = e.detail;
        let targetLocation = pageIndex + 1;
        if (window.innerWidth > 768 && side === 'back') targetLocation += 1;
        currentLocation = targetLocation;
        syncVisuals(true, side);
    });

    bookContainer.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });

    bookContainer.addEventListener('touchend', (e) => {
        if (window.innerWidth > 768) return;
        if (isBusy) return;
        const isModalOpen = !!document.getElementById('mobile-footnote-portal');
        if (isModalOpen) return;
        const touchEndX = e.changedTouches[0].screenX;
        const touchEndY = e.changedTouches[0].screenY;
        const diffX = touchEndX - touchStartX;
        const diffY = touchEndY - touchStartY;
        if (Math.abs(diffY) > Math.abs(diffX)) return;
        if (Math.abs(diffX) > 50) {
            if (diffX < 0) nextMob();
            else prevMob();
        } else if (Math.abs(diffX) < 10 && Math.abs(diffY) < 10) {
            if (touchEndX > window.innerWidth / 2) nextMob();
            else prevMob();
        }
    }, { passive: true });

    bookContainer.onclick = (e) => {
        if (window.innerWidth <= 768) return;
        if (isBusy) return;
        if (e.target === bookContainer) {
            if (currentLocation > 1) prevDesk();
        }
    };

    function nextMob() {
        lockInput(200);
        if (currentLocation > totalPapers) return;
        const p = Array.from(bookContainer.children).find(el => parseInt(el.getAttribute('data-index')) === currentLocation - 1);
        if (p) {
            p.classList.add('flipped');
            setTimeout(() => { syncVisuals(); }, 300);
        }
        currentLocation++;
        mobileShowBack = false;
        updateState();
    }

    function prevMob() {
        lockInput(200);
        if (currentLocation === 1) return;
        currentLocation--;
        syncVisuals();
        const p = Array.from(bookContainer.children).find(el => parseInt(el.getAttribute('data-index')) === currentLocation - 1);
        if (p) p.classList.remove('flipped');
        mobileShowBack = false;
    }

    function nextDesk() {
        if (currentLocation < maxLocation) {
            lockInput(300);
            renderVisiblePapers(currentLocation);
            const p = Array.from(bookContainer.children).find(el => parseInt(el.getAttribute('data-index')) === currentLocation - 1);
            if (p) {
                p.classList.add("moving", "flipped");
                p.style.zIndex = maxLocation + 1;
            }
            currentLocation++;
            updateState();
            setTimeout(() => {
                if (p) {
                    p.classList.remove("moving");
                    syncVisuals();
                }
            }, 400);
        }
    }

    function prevDesk() {
        if (currentLocation > 1) {
            lockInput(300);
            renderVisiblePapers(currentLocation - 1);
            const p = Array.from(bookContainer.children).find(el => parseInt(el.getAttribute('data-index')) === currentLocation - 2);
            if (p) {
                p.classList.add("moving");
                p.classList.remove("flipped");
                p.style.zIndex = maxLocation + 1;
            }
            currentLocation--;
            updateState();
            setTimeout(() => {
                if (p) {
                    p.classList.remove("moving");
                    syncVisuals();
                }
            }, 400);
        }
    }

    let lastTrackedLocation = null;
    function updateState(forceMobileBack = false) {
        if (window.hasBookLoadedForAnalytics && lastTrackedLocation !== currentLocation) {
            window.pendingPageTurns = (window.pendingPageTurns || 0) + 1;
        }
        window.hasBookLoadedForAnalytics = true;
        lastTrackedLocation = currentLocation;

        updateBookState(currentLocation);
        highlightActiveSidebarItem(currentLocation, forceMobileBack || mobileShowBack);
        updateProgressBar(currentLocation, totalPapers);
// 🚀 1. AMBIENT BACKGROUND განახლება
        if (typeof updateAmbientBackground === 'function') {
            updateAmbientBackground(currentLocation);
        }

        if (CURRENT_BOOK_SLUG) {
            // 🚀 2. ვითვლით პროცენტს (მაგ: 0.5 ნიშნავს 50%-ს)
            const percent = totalPapers > 1 ? (currentLocation - 1) / totalPapers : 0;

            // 🚀 3. ვინახავთ ლოკალურად ენის მიხედვით (ახალი ლოგიკა)
            localStorage.setItem(`book_percent_${currentLanguage}_${CURRENT_BOOK_SLUG}`, percent);

            // 🚀 4. ვაგზავნით Cloud-ში (ფუნქცია თვითონ ამოწმებს ავტორიზაციას)
            if (typeof syncProgressToDB === 'function') {
                syncProgressToDB(percent);
            }
        }
    }

    function lockInput(time) {
        isBusy = true;
        setTimeout(() => { isBusy = false; }, time);
    }

    if (window.bookKeydownHandler) {
        document.removeEventListener('keydown', window.bookKeydownHandler);
    }

    window.bookKeydownHandler = (e) => {
        if (e.target.closest('input, textarea, .ql-editor')) return;
        if (window.innerWidth <= 768) return;
        if (isBusy) return;

        if (e.key === 'ArrowRight') {
            e.preventDefault();
            nextDesk();
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (currentLocation > 1) prevDesk();
        }
    };

    document.addEventListener('keydown', window.bookKeydownHandler);
    syncVisuals(true, 'front');
}

function highlightActiveSidebarItem(currentLocation, isMobileBack) {
    const items = Array.from(document.querySelectorAll('#chapter-list-ui li'));

    // 1. ვასუფთავებთ ყველაფერს - სრული რესეტი
    items.forEach(item => item.classList.remove('active'));

    // 2. ვითვლით მიმდინარე ვიზუალურ პოზიციას
    // currentLocation 1-დან იწყება, მასივი 0-დან.
    const pIndex = currentLocation - 1;
    const isMobile = window.innerWidth <= 768;

    // Desktop-ზე ყოველი ფურცელი 2 გვერდია (0, 2, 4...), Mobile-ზე 1 (0, 1, 2...)
    // ეს არის ჩვენი "მიმდინარე კოორდინატი"
    let currentVisualId = isMobile ? pIndex : (pIndex * 2);

    // 3. 🎯 "LAST PASSED ITEM" LOGIC (მთავარი ფიქსი)
    // ვეძებთ ელემენტს, რომლის ID არის ყველაზე ახლოს ჩვენს პოზიციასთან (მაგრამ არა წინ)

    let activeItem = null;
    let maxIdFound = -999; // ვიწყებთ მინუსიდან, რადგან Cover -1-ია

    items.forEach(item => {
        const vId = parseInt(item.getAttribute('data-virtual-id'));

        // თუ ეს ელემენტი უკვე გავიარეთ ან ახლა ვდგავართ მასზე
        if (vId <= currentVisualId) {
            // და თუ მისი ID უფრო დიდია ვიდრე წინა ნაპოვნის...
            if (vId > maxIdFound) {
                maxIdFound = vId;
                activeItem = item;
            }
        }
    });

    // 4. ვააქტიურებთ მხოლოდ ერთ გამარჯვებულს
    if (activeItem) {
        activeItem.classList.add('active');

        // თუ ეს ქვეთავია, მშობელი H1-იც გავააქტიუროთ (მაგრამ Active კლასის გარეშე, უბრალოდ ვიზუალი რომ ჰქონდეს)
        // შენი CSS-ის მიხედვით, მშობელსაც 'active' სჭირდება რომ ისარი გააფერადოს.
        if (!activeItem.classList.contains('toc-h1') && !activeItem.classList.contains('toc-cover')) {
            let prev = activeItem.previousElementSibling;
            while (prev) {
                if (prev.classList.contains('toc-h1')) {
                    prev.classList.add('active');
                    break;
                }
                prev = prev.previousElementSibling;
            }
        }

        // სქროლი აქტიურ ელემენტთან (მხოლოდ დესკტოპზე)
        if (!isMobile) {
            activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // 🚀 NEW: URL HASH-ის ჩუმი განახლება (მხოლოდ dedicated reader გვერდზე)
        // book-manager-ზე hash = book slug, ამიტომ chapter slug-ით არ ვცვლით
        if (FORCED_SLUG) {
            const slugText = activeItem.getAttribute('data-slug');
            if (slugText) {
                const currentHash = window.location.hash;
                const newHash = '#' + slugText;
                // ვიყენებთ replaceState-ს, რომ History-ში არ ჩაიწეროს 100 გადაფურცვლა (Back ღილაკი რომ არ გაფუჭდეს)
                if (currentHash !== newHash) {
                    history.replaceState(null, null, newHash);
                }
            }
        }
    }

    // 5. 🧠 SMART VISIBILITY LOGIC (შენარჩუნებულია)
    // ეს ბლოკი მართავს, რა გამოჩნდეს და რა არა (შეკეცვა/გაშლა)
    const allH1 = document.querySelectorAll('.toc-h1');

    allH1.forEach(h1 => {
        const arrow = h1.querySelector('.toc-arrow');
        // თუ ისარი აქვს და კლასი 'collapsed' ადევს -> ესეიგი შეკეცილია
        const isCollapsed = arrow && arrow.classList.contains('collapsed');

        let sibling = h1.nextElementSibling;
        while (sibling) {
            if (sibling.classList.contains('toc-h1') || sibling.classList.contains('toc-cover')) break;

            if (isCollapsed) {
                // 🛑 თუ შეკეცილია: მხოლოდ აქტიურს ვაჩენთ!
                if (sibling.classList.contains('active')) {
                    sibling.classList.remove('visual-hidden');
                } else {
                    sibling.classList.add('visual-hidden');
                }
            } else {
                // ✅ თუ გაშლილია: ყველაფერს ვაჩენთ
                sibling.classList.remove('visual-hidden');
            }

            sibling = sibling.nextElementSibling;
        }
    });
    // 🚀 NEW: აქ ვამატებთ ფონის განახლებას!
    // რადგან საიდბარი უკვე განახლდა და ვიცით რომელია active
    if (typeof updateAmbientBackground === 'function') {
        updateAmbientBackground();
    }
}

function updateBookState(loc) {
    const book = document.getElementById('book');
    if (window.innerWidth > 768) {
        if (loc === 1) book.classList.add('closed');
        else book.classList.remove('closed');
    }
}
// ============================================================
// QUILL EDITOR WITH CUSTOM MODAL
// ============================================================
let currentFootnoteCallback = null; // აქ შევინახავთ ფუნქციას შენახვისთვის
// --- GLOBAL VARIABLES FOR SPLIT VIEW ---
let quillDual = null;
let isSplitView = false;

function initQuill() {
    // 1. თუ მთავარი ედიტორიც არ არის, საერთოდ გავჩერდეთ
    if (!document.getElementById('editor-container')) return;

    // ხატულა Footnote-ისთვის
    const icons = Quill.import('ui/icons');
    icons['footnote'] = `<svg class="ql-stroke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path class="ql-stroke" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>`;

    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'footnote'],
        [{ 'header': 1 }, { 'header': 2 }, { 'header': 3 }],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'align': [] }],
        ['image', 'clean']
    ];

    // 2. მთავარი ედიტორის შექმნა (ეს ყოველთვის არის ადმინ პანელში)
    quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: toolbarOptions,
                handlers: { image: () => imageHandler(quill), footnote: () => footnoteHandler(quill) }
            },
            history: { delay: 2000, maxStack: 500, userOnly: true }
        }
    });

    // 3. 🛑 SAFETY CHECK: მეორე ედიტორს ვქმნით მხოლოდ თუ HTML-ში არსებობს!
    const dualContainer = document.getElementById('editor-container-dual');
    if (dualContainer) {
        quillDual = new Quill('#editor-container-dual', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: toolbarOptions,
                    handlers: { image: () => imageHandler(quillDual), footnote: () => footnoteHandler(quillDual) }
                },
                history: { delay: 2000, maxStack: 500, userOnly: true }
            }
        });
    } else {
        quillDual = null; // თუ არ არის, ცარიელი იყოს
    }

    // 4. Event Listeners (მხოლოდ არსებულ ედიტორებზე)
    const activeEditors = [quill];
    if (quillDual) activeEditors.push(quillDual);

    activeEditors.forEach(q => {
        q.root.addEventListener('click', (ev) => {
            const target = ev.target;
            if (target.classList.contains('footnote-trigger')) {
                const wordInText = target.innerText;
                const blot = Quill.find(target);
                const index = q.getIndex(blot);
                const length = blot.length();
                const currentData = {
                    content: target.getAttribute('data-content'),
                    title: target.getAttribute('data-title')
                };
                openFootnoteEditor(wordInText, currentData, (newData) => {
                    if (newData === null) q.removeFormat(index, length);
                    else q.formatText(index, length, 'footnote', newData);
                });
            }
        });
    });
}

// დამხმარე ჰენდლერები (რომ ორივე ედიტორზე იმუშაოს)
function footnoteHandler(qInstance) {
    const range = qInstance.getSelection();
    if (range) {
        if (range.length == 0) { alert('ჯერ მონიშნეთ სიტყვა.'); return; }
        const text = qInstance.getText(range.index, range.length);
        const format = qInstance.getFormat(range);
        const currentData = format.footnote || null;
        openFootnoteEditor(text, currentData, (newData) => {
            if (newData === null) qInstance.format('footnote', false);
            else qInstance.format('footnote', newData);
        });
    }
}

/* imageHandler(qInstance) removed - was dead code, overridden by imageHandler() below */
// ============================================================
// CUSTOM FOOTNOTE MODAL LOGIC
// ============================================================
function openFootnoteEditor(wordInText, currentData, onSave) {
    const modal = document.getElementById('footnote-editor-modal');
    const wordDisplay = document.getElementById('footnote-word-display');
    // ღილაკები
    const saveBtn = document.getElementById('save-footnote-btn');
    const deleteBtn = document.getElementById('delete-footnote-btn');
    const cancelBtn = document.getElementById('cancel-footnote-btn');
    const closeBtn = document.getElementById('close-footnote-modal');
    // მონაცემების პარსვა (რადგან შეიძლება იყოს სტრინგი ან ობიექტი)
    let contentHtml = "";
    let dictionaryTitle = wordInText; // დეფოლტად ვიღებთ ტექსტში რაც წერია
    if (typeof currentData === 'object' && currentData !== null) {
        contentHtml = currentData.content || "";
        // თუ უკვე შენახული გვაქვს სათაური (მაგ: "პლანეტა"), ვიყენებთ მას
        if (currentData.title) dictionaryTitle = currentData.title;
    } else {
        contentHtml = currentData || "";
    }
    // ველის მომზადება
    wordDisplay.disabled = false;
    wordDisplay.value = dictionaryTitle; // აქ ვსვამთ სათაურს (მაგ: პლანეტა)
    // Mini Quill-ის ინიციალიზაცია
    if (!footnoteQuill) {
        footnoteQuill = new Quill('#footnote-mini-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    ['clean']
                ]
            }
        });
    }
    footnoteQuill.setText('');
    const delta = footnoteQuill.clipboard.convert(contentHtml);
    footnoteQuill.setContents(delta, 'silent');
    modal.classList.add('active');
    const closeModal = () => {
        modal.classList.remove('active');
        saveBtn.onclick = null;
        deleteBtn.onclick = null;
    };
    // Save
    saveBtn.onclick = () => {
        const html = footnoteQuill.root.innerHTML;
        const text = footnoteQuill.getText().trim();
        // ვიღებთ შეცვლილ სათაურს ინპუტიდან
        const newTitle = wordDisplay.value.trim();
        if (text.length > 0 && newTitle.length > 0) {
            // ვაბრუნებთ ობიექტს: { content, title }
            onSave({
                content: html,
                title: newTitle
            });
            closeModal();
        } else {
            if (newTitle.length === 0) {
                alert("სათაური არ შეიძლება იყოს ცარიელი!");
                return;
            }
            if (confirm("განმარტება ცარიელია. წავშალოთ?")) {
                onSave(null);
                closeModal();
            }
        }
    };
    // Delete
    deleteBtn.onclick = () => {
        if (confirm("ნამდვილად გსურთ წაშლა?")) {
            onSave(null);
            closeModal();
        }
    };
    cancelBtn.onclick = closeModal;
    closeBtn.onclick = closeModal;
    modal.onclick = (e) => {
        if (e.target === modal) closeModal();
    };
}

function imageHandler() {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();
    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;
        const range = quill.getSelection();
        quill.insertText(range.index, 'Uploading image...', 'bold', true);
        try {
            const fileExt = file.name.split('.').pop();
            const fileName = `content_${Date.now()}_${Math.random().toString(36).substr(2, 9)}.${fileExt}`;
            const {
                data,
                error
            } = await sbClient.storage.from('covers').upload(fileName, file);
            if (error) throw error;
            const {
                data: publicData
            } = sbClient.storage.from('covers').getPublicUrl(fileName);
            const url = publicData.publicUrl;
            quill.deleteText(range.index, 16);
            quill.insertEmbed(range.index, 'image', url);
            quill.setSelection(range.index + 1);
        } catch (error) {
            /* image upload error silenced */
            alert("Image upload failed!");
            quill.deleteText(range.index, 16);
        }
    };
}

function setupEditorEvents() {
    // 🛑 STOP: თუ ედიტორი არ არის (ანუ მკითხველი ვარ), გაჩერდი!
    if (!document.getElementById('editor-modal')) return;
    const modal = document.getElementById('editor-modal');
    const editBtn = document.getElementById('edit-mode-btn');
    const langTabs = document.querySelectorAll('.lang-tab');
    const loader = document.getElementById('editor-loading-overlay');
    const mobileToggleBtn = document.getElementById('mobile-expand-toggle');
    const modalBody = document.querySelector('.modal-body');
    const settingsForm = document.getElementById('settings-form');
    const openSettingsBtn = document.getElementById('open-settings-btn');
    const closeSettingsBtn = document.getElementById('close-settings-btn');

    // 🟢 SPLIT VIEW BUTTON
    const splitBtn = document.getElementById('split-view-btn');
    const syncBtn = document.getElementById('sync-scroll-btn'); // 👈 ეს ხაზი დაამატე
    if (mobileToggleBtn) {
        mobileToggleBtn.onclick = () => {
            modalBody.classList.toggle('expanded-mode');
            const icon = mobileToggleBtn.querySelector('.material-icons-outlined');
            icon.innerText = modalBody.classList.contains('expanded-mode') ? "expand_more" : "expand_less";
        };
    }

    // 🟢 TOGGLE LOGIC (SPLIT VS SINGLE)
    if (splitBtn) {
        splitBtn.onclick = () => {
            if(loader) loader.classList.remove('hidden');

            // 1. ჯერ ვინახავთ იმას, რაც ახლა ეკრანზეა (მეხსიერებაში)
            saveCurrentStateToMemory();

            setTimeout(() => {
                isSplitView = !isSplitView; // რეჟიმის გადართვა

                const wrapper = document.getElementById('editors-wrapper');
                const colPrimary = document.getElementById('col-primary');
                const colSecondary = document.getElementById('col-secondary');

                // ლეიბლები (ENGLISH / GEORGIAN)
                const labels = document.querySelectorAll('.editor-col-label');


                if (isSplitView) {
// ✅ NEW: Sync ღილაკის გამოჩენა და ფუნქციის გაშვება
                    if(syncBtn) {
                        syncBtn.style.display = 'flex';
                        // სინქრონიზაციის ინიციალიზაცია (თუ უკვე გაშვებული არ არის)
                        if (!window.syncInitialized) {
                            enableSmartSync();
                            window.syncInitialized = true;
                        }
                    }
                    // --- REJIM: SPLIT VIEW (გვერდი-გვერდ) ---
                    splitBtn.classList.add('active');
                    wrapper.classList.add('split-active');

                    // 🛑 FORCE SHOW: იძულებით ვაჩენთ მეორე სვეტს
                    colSecondary.style.setProperty('display', 'flex', 'important');

                    // ლეიბლების გამოჩენა
                    labels.forEach(l => l.style.display = 'block');

                    // ტაბებს ვთიშავთ (რადგან ორივე ენა ჩანს)
                    langTabs.forEach(t => {
                        t.style.opacity = '0.3';
                        t.style.pointerEvents = 'none';
                    });

                    // მარცხნივ -> ინგლისური, მარჯვნივ -> ქართული
                    loadContentIntoQuill(quill, 'en');
                    loadContentIntoQuill(quillDual, 'ka');

                } else {

                    // ✅ NEW: Sync ღილაკის დამალვა
                    if(syncBtn) {
                        syncBtn.style.display = 'none';
                        syncBtn.classList.remove('active'); // გავთიშოთ თუ ჩართული იყო
                    }
                    // --- REJIM: SINGLE VIEW (მხოლოდ ერთი ენა) ---
                    splitBtn.classList.remove('active');
                    wrapper.classList.remove('split-active');

                    // 🛑 FORCE HIDE: იძულებით ვმალავთ მეორე სვეტს (CSS-ს რომ გადაუაროს)
                    colSecondary.style.setProperty('display', 'none', 'important');

                    // ლეიბლების დამალვა
                    labels.forEach(l => l.style.display = 'none');

                    // ტაბებს ვაბრუნებთ
                    langTabs.forEach(t => {
                        t.style.opacity = '1';
                        t.style.pointerEvents = 'auto';
                    });

                    // მთავარ ედიტორში ვტვირთავთ იმ ენას, რომელიც არჩეულია ტაბებში
                    loadContentIntoQuill(quill, editorLanguage);
                }

                if(loader) loader.classList.add('hidden');
            }, 100);
        };
    }

    // 🟢 LANGUAGE SWITCH (მუშაობს მხოლოდ Single რეჟიმში)
    langTabs.forEach(tab => {
        tab.onclick = () => {
            // თუ Split View ჩართულია, ტაბები არ უნდა მუშაობდეს
            if(isSplitView) return;

            const targetLang = tab.getAttribute('data-lang');
            if (editorLanguage === targetLang) return; // იგივეზე დაჭერა

            if (loader) loader.classList.remove('hidden');

            setTimeout(() => {
                // 1. ვინახავთ ძველს (სანამ გადავრთავთ)
                saveCurrentStateToMemory();

                // 2. ვიზუალური გადართვა
                langTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                editorLanguage = targetLang;

                // 3. მთავარ ედიტორში ახალი ენის ჩატვირთვა
                loadContentIntoQuill(quill, editorLanguage);

                renderChaptersList();
                if (loader) loader.classList.add('hidden');
            }, 50);
        };
    });

    if (editBtn) {
        editBtn.onclick = () => {
            modal.classList.add('active');
            if (loader) loader.classList.remove('hidden');
            setTimeout(() => {
                // RESET: ყოველთვის ვიწყებთ ინგლისურით და Single რეჟიმით
                isSplitView = false;
                editorLanguage = 'en';

                if(splitBtn) splitBtn.classList.remove('active');
                const wrapper = document.getElementById('editors-wrapper');
                if(wrapper) wrapper.classList.remove('split-active');

                // 🛑 FORCE HIDE RESET
                const colSecondary = document.getElementById('col-secondary');
                if(colSecondary) colSecondary.style.setProperty('display', 'none', 'important');

                // ლეიბლების დამალვა
                document.querySelectorAll('.editor-col-label').forEach(l => l.style.display = 'none');

                // ტაბების რესეტი
                langTabs.forEach(t => t.classList.remove('active'));
                langTabs.forEach(t => t.style.opacity = '1');
                langTabs.forEach(t => t.style.pointerEvents = 'auto');

                const enTab = document.querySelector('.lang-tab[data-lang="en"]');
                if(enTab) enTab.classList.add('active');

                if(settingsForm) settingsForm.style.display = 'none';
                isEditingSettings = false;

                // სათაურების ამოღება (დაზღვევა)
                if (chaptersData[selectedChapterIndex]) {
                    const kaC = chaptersData[selectedChapterIndex].draft_content || "";
                    const enC = chaptersData[selectedChapterIndex].draft_content_en || "";
                    if(typeof extractTitleFromHTML === 'function') {
                        chaptersData[selectedChapterIndex].title = extractTitleFromHTML(kaC);
                        chaptersData[selectedChapterIndex].title_en = extractTitleFromHTML(enC);
                    }
                }

                renderChaptersList();
                loadChapter(selectedChapterIndex);
                if (loader) loader.classList.add('hidden');
            }, 50);
        };
    }

    document.getElementById('close-modal').onclick = () => modal.classList.remove('active');

    if (openSettingsBtn) {
        openSettingsBtn.onclick = () => {
            isEditingSettings = true;
            settingsForm.style.display = 'flex';
            fillSettingsInputs();
        };
    }
    if (closeSettingsBtn) {
        closeSettingsBtn.onclick = () => {
            isEditingSettings = false;
            settingsForm.style.display = 'none';
        };
    }

    setupAllButtons();
}


// ეს ფუნქცია ჩასვი სადმე გლობალურად (მაგალითად ფაილის ბოლოში ან setupEditorEvents-ის წინ)
function extractTitleFromHTML(html) {
    if (!html || html === "<p><br></p>") return "Untitled";
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const headings = temp.querySelectorAll('h1, h2, h3');
    for (let h of headings) {
        const text = h.textContent.replace(/\s+/g, ' ').trim();
        if (text.length > 0) return text;
    }
    const firstP = temp.querySelector('p');
    if (firstP && firstP.textContent.trim().length > 0) {
        let pText = firstP.textContent.trim();
        return pText.length > 25 ? pText.substring(0, 25) + "..." : pText;
    }
    return "Untitled";
}

// დამხმარე: მეხსიერებაში შენახვა (Quill -> Array)
function saveCurrentStateToMemory() {
    if (!chaptersData[selectedChapterIndex]) return;

    const ch = chaptersData[selectedChapterIndex];

    if (isSplitView) {
        // Split-ის დროს ორივე ედიტორიდან ვიღებთ
        ch.draft_content_en = quill.root.innerHTML;
        ch.draft_content = quillDual.root.innerHTML; // ka
    } else {
        // Single-ის დროს მხოლოდ აქტიურიდან
        if (editorLanguage === 'ka') ch.draft_content = quill.root.innerHTML;
        else ch.draft_content_en = quill.root.innerHTML;
    }
}

// დამხმარე: კონტენტის ჩატვირთვა კონკრეტულ Quill-ში
function loadContentIntoQuill(qInstance, lang) {
    if (!chaptersData[selectedChapterIndex]) return;
    const ch = chaptersData[selectedChapterIndex];

    let content = "";
    if (lang === 'ka') content = ch.draft_content || ch.content || "";
    else content = ch.draft_content_en || ch.content_en || ""; // en

    qInstance.enable(false);
    qInstance.setText('');
    qInstance.clipboard.dangerouslyPasteHTML(0, content);
    qInstance.history.clear();
    qInstance.enable(true);
}
// 2. ეს ფუნქცია ავსებს ინპუტებს (ცალკე გამოტანილი)
function fillSettingsInputs() {
    document.getElementById('input-book-title').value = bookMeta.title || "";
    document.getElementById('input-book-subtitle').value = bookMeta.subtitle || "";
    document.getElementById('input-book-title-en').value = bookMeta.title_en || "";
    document.getElementById('input-book-subtitle-en').value = bookMeta.subtitle_en || "";
    if (document.getElementById('input-book-desc')) document.getElementById('input-book-desc').value = bookMeta.description || "";
    if (document.getElementById('input-book-desc-en')) document.getElementById('input-book-desc-en').value = bookMeta.description_en || "";
    document.getElementById('input-book-genre').value = bookMeta.genre_ka || "";
    document.getElementById('input-book-genre-en').value = bookMeta.genre_en || "";
    document.getElementById('input-book-year').value = bookMeta.published_year || "";
    const coverPreview = document.getElementById('cover-preview');
    if (bookMeta.coverImage) {
        coverPreview.style.backgroundImage = `url(${bookMeta.coverImage})`;
        coverPreview.innerText = "";
    } else {
        coverPreview.style.backgroundImage = "none";
        coverPreview.innerText = "No image selected";
    }
}
// 3. ეს არის მთავარი - შენახვის ლოგიკა (ცალკე, რომ არაფერში აირიოს)
// 3. ეს არის მთავარი - შენახვის ლოგიკა (ყველა ღილაკისთვის)
function setupSettingsLogic() {
    const saveMetaBtn = document.getElementById('save-meta-btn');
    const settingsForm = document.getElementById('settings-form');
    // --- დამხმარე ფუნქცია: ბაზაში გაგზავნა (ყველასთვის) ---
    // --- დამხმარე ფუნქცია: ბაზაში გაგზავნა (SAFE MODE) ---
    const pushToDB = async (statusEl) => {
        if (!currentBookId) {
            alert("Error: No ID");
            return;
        }
        if (statusEl) statusEl.innerText = "Saving...";
        try {
            // 1. ვამოწმებთ, გახსნილია თუ არა სეთინგები და არის თუ არა ინპუტებში რამე
            const titleInput = document.getElementById('input-book-title');
            // 🛑 SAFETY CHECK: მხოლოდ მაშინ განვაახლოთ bookMeta ინპუტებიდან,
            // თუ ინპუტები არსებობს და ცარიელი არ არის!
            if (titleInput && titleInput.value.trim() !== "") {
                bookMeta.title = titleInput.value;
                bookMeta.subtitle = document.getElementById('input-book-subtitle').value;
                bookMeta.title_en = document.getElementById('input-book-title-en').value;
                bookMeta.subtitle_en = document.getElementById('input-book-subtitle-en').value;
                if (document.getElementById('input-book-desc')) bookMeta.description = document.getElementById('input-book-desc').value;
                if (document.getElementById('input-book-desc-en')) bookMeta.description_en = document.getElementById('input-book-desc-en').value;
                bookMeta.genre_ka = document.getElementById('input-book-genre').value;
                bookMeta.genre_en = document.getElementById('input-book-genre-en').value;
                bookMeta.published_year = document.getElementById('input-book-year').value;

                if (seoInput) bookMeta.seo_description = seoInput.value;
                if (seoInputEn) bookMeta.seo_description_en = seoInputEn.value;
            } else {
            }
            // 2. ვაგზავნით Supabase-ში (ახლა უკვე უსაფრთხოა)
            const {
                error
            } = await sbClient.from('book_projects').update({
                title: bookMeta.title,
                subtitle: bookMeta.subtitle,
                cover_image: bookMeta.coverImage,
                chapters: chaptersData, // ტექსტები აქ არის
                title_en: bookMeta.title_en,
                subtitle_en: bookMeta.subtitle_en,
                description: bookMeta.description,
                description_en: bookMeta.description_en,
                seo_description: bookMeta.seo_description,
                seo_description_en: bookMeta.seo_description_en,
                genre_ka: bookMeta.genre_ka,
                genre_en: bookMeta.genre_en,
                published_year: bookMeta.published_year
            }).eq('id', currentBookId);
            if (error) throw error;
            // 3. ლოკალური ქეშის განახლება
            localStorage.setItem('cached_book_' + CURRENT_BOOK_SLUG, JSON.stringify({
                ...bookMeta,
                id: currentBookId,
                chapters: chaptersData,
                cover_image: bookMeta.coverImage,
                slug: CURRENT_BOOK_SLUG
            }));
            updateStaticUI();
            if (statusEl) statusEl.innerText = "Saved!";
            setTimeout(() => {
                if (statusEl) statusEl.innerText = "";
            }, 2000);
        } catch (err) {
            /* save error silenced */
            alert("Save Failed: " + err.message);
            if (statusEl) statusEl.innerText = "Error!";
            throw err;
        }
    };
    // --- ფოტოს ატვირთვის ლოგიკა ---
    const coverInput = document.getElementById('input-cover-image');
    const removeCoverBtn = document.getElementById('remove-cover-btn');
    const coverPreview = document.getElementById('cover-preview');
    if (coverInput) {
        coverInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                window.pendingCoverFile = file;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    coverPreview.style.backgroundImage = `url(${ev.target.result})`;
                    coverPreview.innerText = "";
                };
                reader.readAsDataURL(file);
            }
        };
    }
    if (removeCoverBtn) {
        removeCoverBtn.onclick = () => {
            coverInput.value = "";
            coverPreview.style.backgroundImage = "none";
            coverPreview.innerText = "Cover Removed";
            bookMeta.coverImage = null;
            window.pendingCoverFile = null;
        };
    }
    // --- 1. SETTINGS SAVE BUTTON ---
    if (saveMetaBtn) {
        const newBtn = saveMetaBtn.cloneNode(true); // ძველი ივენთების გასუფთავება
        saveMetaBtn.parentNode.replaceChild(newBtn, saveMetaBtn);
        newBtn.onclick = async (e) => {
            e.preventDefault();
            const originalText = newBtn.innerText;
            newBtn.innerText = "SAVING...";
            newBtn.disabled = true;
            try {
                // ფოტოს ატვირთვა (თუ არის)
                if (window.pendingCoverFile) {
                    const uploadedUrl = await uploadCoverToStorage(window.pendingCoverFile);
                    if (uploadedUrl) {
                        bookMeta.coverImage = uploadedUrl;
                        window.pendingCoverFile = null;
                    }
                }
                await pushToDB(null); // ვიძახებთ საერთო ფუნქციას
                newBtn.innerText = "SAVED! ✓";
                newBtn.style.backgroundColor = "#28a745";
                setTimeout(() => {
                    newBtn.innerText = originalText;
                    newBtn.disabled = false;
                    newBtn.style.backgroundColor = "";
                    isEditingSettings = false;
                    settingsForm.style.display = 'none';
                }, 1500);
            } catch (error) {
                newBtn.innerText = "ERROR";
                newBtn.style.backgroundColor = "#d9534f";
                setTimeout(() => {
                    newBtn.innerText = originalText;
                    newBtn.disabled = false;
                    newBtn.style.backgroundColor = "";
                }, 2000);
            }
        };
    }
    // --- 2. SAVE DRAFT BUTTON (FIXED) ---
    // --- 2. SAVE DRAFT ---
    const draftBtn = document.getElementById('save-draft-btn');
    if (draftBtn) {
        draftBtn.onclick = async () => {
            const status = document.getElementById('save-status');
            draftBtn.innerText = "Saving...";
            draftBtn.disabled = true;
            // აღარ გვჭირდება removeHighlightClasses
            const htmlContent = quill.root.innerHTML;
            if (editorLanguage === 'ka') {
                chaptersData[selectedChapterIndex].draft_content = htmlContent;
            } else {
                chaptersData[selectedChapterIndex].draft_content_en = htmlContent;
            }
            renderChaptersList(); // ეს განაახლებს ყვითელ წერტილს სიაში
            try {
                await globalPushToDB(status);
                // loadChapter-ის გამოძახება აღარ არის აუცილებელი, რადგან ფერები არ იცვლება ტექსტში
            } catch (e) {}
            draftBtn.innerText = "Save Draft";
            draftBtn.disabled = false;
        };
    }
    // --- 3. PUBLISH BUTTON ---
    const pubBtn = document.getElementById('publish-btn');
    if (pubBtn) {
        const newPubBtn = pubBtn.cloneNode(true);
        pubBtn.parentNode.replaceChild(newPubBtn, pubBtn);
        newPubBtn.onclick = async () => {
            if (!confirm("Publish changes to readers?")) return;
            const status = document.getElementById('save-status');
            newPubBtn.innerText = "Publishing...";
            newPubBtn.disabled = true;
            const dirtyHTML = quill.root.innerHTML;
            const cleanHTML = removeHighlightClasses(dirtyHTML);
            // სათაურის ამოღება ტექსტიდან (ავტომატური სათაური)
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = cleanHTML;
            const hTag = tempDiv.querySelector('h1, h2');
            const extractedTitle = hTag ? hTag.innerText.trim() : "Untitled";
            if (editorLanguage === 'ka') {
                chaptersData[selectedChapterIndex].draft_content = cleanHTML;
                chaptersData[selectedChapterIndex].content = cleanHTML; // ✅ Public-შიც გადაგვაქვს
                chaptersData[selectedChapterIndex].title = extractedTitle;
            } else {
                chaptersData[selectedChapterIndex].draft_content_en = cleanHTML;
                chaptersData[selectedChapterIndex].content_en = cleanHTML; // ✅ Public-შიც გადაგვაქვს
                chaptersData[selectedChapterIndex].title_en = extractedTitle;
            }
            renderChaptersList();
            try {
                await pushToDB(status);
                loadChapter(selectedChapterIndex);
                renderBook();
            } catch (e) {}
            newPubBtn.innerText = "Publish";
            newPubBtn.disabled = false;
        };
    }
    // --- 4. UNPUBLISH BUTTON ---
    const unpubBtn = document.getElementById('unpublish-btn');
    if (unpubBtn) {
        const newUnpubBtn = unpubBtn.cloneNode(true);
        unpubBtn.parentNode.replaceChild(newUnpubBtn, unpubBtn);
        newUnpubBtn.onclick = async () => {
            if (!confirm("Hide this chapter from readers? (It will remain in Draft)")) return;
            const status = document.getElementById('save-status');
            newUnpubBtn.innerText = "...";
            const currentHTML = quill.root.innerHTML;
            if (editorLanguage === 'ka') {
                chaptersData[selectedChapterIndex].draft_content = currentHTML;
                chaptersData[selectedChapterIndex].content = ""; // 🛑 ვასუფთავებთ Public-ს
            } else {
                chaptersData[selectedChapterIndex].draft_content_en = currentHTML;
                chaptersData[selectedChapterIndex].content_en = ""; // 🛑 ვასუფთავებთ Public-ს
            }
            renderChaptersList();
            await pushToDB(status);
            renderBook();
            newUnpubBtn.innerText = "Unpublish";
        };
    }
    // --- 5. DISCARD BUTTON ---
    const discardBtn = document.getElementById('discard-draft-btn');
    if (discardBtn) {
        const newDiscardBtn = discardBtn.cloneNode(true);
        discardBtn.parentNode.replaceChild(newDiscardBtn, discardBtn);
        newDiscardBtn.onclick = async () => {
            if (!confirm("Discard all draft changes and revert to published version?")) return;
            const status = document.getElementById('save-status');
            newDiscardBtn.innerText = "...";
            const ch = chaptersData[selectedChapterIndex];
            // ვაბრუნებთ Public ვერსიას Draft-ში
            if (editorLanguage === 'ka') {
                ch.draft_content = ch.content || "";
            } else {
                ch.draft_content_en = ch.content_en || "";
            }
            // ედიტორის განახლება
            const restoredContent = (editorLanguage === 'ka') ? ch.draft_content : ch.draft_content_en;
            quill.enable(false);
            quill.setText('');
            const delta = quill.clipboard.convert(restoredContent);
            quill.setContents(delta, 'silent');
            quill.history.clear();
            quill.enable(true);
            renderChaptersList();
            await pushToDB(status);
            await renderBook();
            newDiscardBtn.innerText = "Discard Draft";
        };
    }
}

let _chapterDragIndex = null;

function renderChaptersList() {
    const list = document.getElementById('editable-pages-list');
    list.innerHTML = '';
    chaptersData.forEach((ch, i) => {
        const li = document.createElement('li');
        li.setAttribute('draggable', 'true');
        li.setAttribute('data-chapter-index', i);
        const displayTitle = getChapterTitle(ch, editorLanguage);
        // --- სტატუსის ლოგიკა (ეს ტოვებს ფერს სიაში) ---
        let statusColor = '#28a745'; // მწვანე (Published)
        let tooltip = "Published";
        let pub, drf;
        if (editorLanguage === 'en') {
            pub = ch.content_en || "";
            drf = ch.draft_content_en || "";
        } else {
            pub = ch.content || "";
            drf = ch.draft_content || "";
        }
        // თუ განსხვავებაა, ვანთებთ ყვითლად
        if (pub.trim() === "") {
            statusColor = '#ffc107'; // ყვითელი (New Draft)
            tooltip = "Draft (Not Published)";
        } else if (pub !== drf) {
            statusColor = '#ffc107'; // ყვითელი (Modified)
            tooltip = "Unpublished Changes";
        }
        // ------------------------------------------------
        // Drag handle
        const dragHandle = document.createElement('span');
        dragHandle.className = 'chapter-drag-handle material-icons-outlined';
        dragHandle.textContent = 'drag_indicator';
        dragHandle.title = 'Drag to reorder';

        const titleSpan = document.createElement('span');
        titleSpan.style.flexGrow = "1";
        titleSpan.style.display = "flex";
        titleSpan.style.alignItems = "center";
        titleSpan.style.gap = "10px";
        // სტატუსის წერტილი
        const dot = document.createElement('span');
        dot.style.width = "8px";
        dot.style.height = "8px";
        dot.style.borderRadius = "50%";
        dot.style.backgroundColor = statusColor;
        dot.style.display = "inline-block";
        dot.style.boxShadow = `0 0 5px ${statusColor}40`;
        dot.title = tooltip;
        const textNode = document.createTextNode(displayTitle || "Untitled");
        titleSpan.appendChild(dot);
        titleSpan.appendChild(textNode);
        // ... (დანარჩენი onclick და delete იგივე რჩება)
        titleSpan.onclick = () => {
            // ...
            selectedChapterIndex = i;
            loadChapter(i);
        };
        // ...
        li.appendChild(dragHandle);
        li.appendChild(titleSpan);
        // ... delBtn append ...
        if (i === selectedChapterIndex && !isEditingSettings) li.classList.add('selected');

        // --- Drag & Drop events ---
        li.addEventListener('dragstart', (e) => {
            _chapterDragIndex = i;
            li.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        li.addEventListener('dragend', () => {
            li.classList.remove('dragging');
            _chapterDragIndex = null;
            list.querySelectorAll('li').forEach(el => el.classList.remove('drag-over-above', 'drag-over-below'));
        });
        li.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (_chapterDragIndex === null || _chapterDragIndex === i) return;
            const rect = li.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            li.classList.remove('drag-over-above', 'drag-over-below');
            if (e.clientY < midY) {
                li.classList.add('drag-over-above');
            } else {
                li.classList.add('drag-over-below');
            }
        });
        li.addEventListener('dragleave', () => {
            li.classList.remove('drag-over-above', 'drag-over-below');
        });
        li.addEventListener('drop', (e) => {
            e.preventDefault();
            li.classList.remove('drag-over-above', 'drag-over-below');
            if (_chapterDragIndex === null || _chapterDragIndex === i) return;
            const rect = li.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            const dropAfter = e.clientY >= midY;
            const fromIdx = _chapterDragIndex;
            // Remove the dragged chapter
            const [moved] = chaptersData.splice(fromIdx, 1);
            // Calculate new insertion index
            let toIdx = i;
            if (fromIdx < i) toIdx--; // adjust since we removed before
            if (dropAfter) toIdx++;
            chaptersData.splice(toIdx, 0, moved);
            // Update selectedChapterIndex to follow the selected chapter
            if (selectedChapterIndex === fromIdx) {
                selectedChapterIndex = toIdx;
            } else if (fromIdx < selectedChapterIndex && toIdx >= selectedChapterIndex) {
                selectedChapterIndex--;
            } else if (fromIdx > selectedChapterIndex && toIdx <= selectedChapterIndex) {
                selectedChapterIndex++;
            }
            _chapterDragIndex = null;
            renderChaptersList();
        });

        list.appendChild(li);
    });
}

function loadChapter(i) {
    if (!chaptersData[i]) return;
    selectedChapterIndex = i;

    // Split View ლოგიკა
    if (isSplitView) {
        loadContentIntoQuill(quill, 'en');
        loadContentIntoQuill(quillDual, 'ka');
    } else {
        loadContentIntoQuill(quill, editorLanguage);
    }

    requestAnimationFrame(() => {
        const list = document.getElementById('editable-pages-list');
        if (list) {
            const items = list.querySelectorAll('li');
            items.forEach((li, idx) => {
                if (idx === i) li.classList.add('selected');
                else li.classList.remove('selected');
            });
        }
        document.getElementById('save-status').innerText = "";
        const loader = document.getElementById('editor-loading-overlay');
        if (loader) loader.classList.add('hidden');

        if (typeof window.renderGlossary === 'function' && document.getElementById('glossary-modal').classList.contains('active')) {
            window.renderGlossary();
        }
    });
}




/* =========================================

   ANALYTICS ENGINE (Lightweight Heartbeat)

   ========================================= */
let analyticsInterval = null;
let isActiveSession = true;
// დამხმარე: დროის ფორმატირება (წამები -> საათი/წუთი)
// დამხმარე: დროის ფორმატირება (აჩვენებს წამებსაც!)
function formatDuration(seconds) {
    if (!seconds) return "0s";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);

    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`; // თუ 1 წუთზე ნაკლებია, გიჩვენებს ზუსტ წამს (მაგ: 30s)
}
/* =========================================

   MASTER ANALYTICS ENGINE (Unified)

   ========================================= */
// მკითხველის პროფილის გენერაცია
function getOrCreateReaderProfile() {
    let id = localStorage.getItem('reader_visitor_id');
    let name = localStorage.getItem('reader_display_name');
    if (!id) {
        id = 'rdr_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
        const adjectives = ["Cyber", "Neon", "Silent", "Quantum", "Lost", "Deep", "Cosmic", "Beta", "Solar", "Lunar"];
        const nouns = ["Voyager", "Monk", "Reader", "Walker", "Drifter", "Mind", "Surfer", "Pilot", "Ghost"];
        const adj = adjectives[Math.floor(Math.random() * adjectives.length)];
        const noun = nouns[Math.floor(Math.random() * nouns.length)];
        const num = Math.floor(Math.random() * 999);
        name = `${adj} ${noun} #${num}`;
        localStorage.setItem('reader_visitor_id', id);
        localStorage.setItem('reader_display_name', name);
    }
    return {
        id,
        name
    };
}

async function initAnalytics() {
    const currentUrl = window.location.href.toLowerCase();

    // 🚀 1. ჯერ ვიღებთ სესიას, რომ მეილი შევამოწმოთ
    const session = await getCachedSession();

    // 🛑 2. ადმინის ფილტრი (შენი ორიგინალი + მეილის კონტროლი)
    if (
        (session && session.user.email === 'zurabkostava1@gmail.com') ||
        currentUrl.includes('manager') ||
        document.body.classList.contains('is-admin')
    ) {
        if (typeof setupAnalyticsUI === 'function') setupAnalyticsUI();
        return; // აქ წყდება ყველაფერი ადმინისთვის
    }

    let trackingSlug = CURRENT_BOOK_SLUG;
    if (!trackingSlug || trackingSlug === 'books') return;

    // 🕵️‍♂️ შენი მოწყობილობის დეტექტორი (უცვლელი)
    const getDetailedDeviceInfo = () => {
        const ua = navigator.userAgent;
        let browser = "Web";
        let model = "";

        if (ua.includes("FBAN") || ua.includes("FBAV")) browser = "Facebook App";
        else if (ua.includes("Instagram")) browser = "Instagram App";
        else if (ua.includes("CriOS") || ua.includes("Chrome")) browser = "Chrome";
        else if (ua.includes("FxiOS") || ua.includes("Firefox")) browser = "Firefox";
        else if (ua.includes("Safari")) browser = "Safari";
        else if (ua.includes("Edg")) browser = "Edge";

        if (/Android/.test(ua)) {
            const match = ua.match(/; ([^;]+) Build\//);
            model = (match && match[1]) ? match[1].trim() : "Android Device";
        } else if (/iPhone/.test(ua)) model = "iPhone";
        else if (/iPad/.test(ua)) model = "iPad";
        else if (/Macintosh/.test(ua)) model = "Mac";
        else if (/Windows/.test(ua)) model = "Windows PC";
        else model = "Device";

        return `${model} • ${browser}`;
    };

    // შენი პროფილის გენერაციის ლოგიკა (უცვლელი)
    let reader = getOrCreateReaderProfile();
    let authUserId = null;

    if (session) {
        authUserId = session.user.id;
        const userMeta = session.user.user_metadata || {};
        reader.name = userMeta.full_name || userMeta.display_name || session.user.email.split('@')[0];
        localStorage.setItem('reader_display_name', reader.name);
    }

    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const deviceType = isMobile ? 'mobile' : 'desktop';
    const fullInfo = getDetailedDeviceInfo();

    const sessionKey = 'session_active_' + trackingSlug;
    let isNewSession = !sessionStorage.getItem(sessionKey);
    let hasRegisteredInDB = false;
    let lastPingTime = Date.now();
    let lastUserActivity = Date.now();
    let totalFlipsInSession = 0;

    const recordActivity = () => { lastUserActivity = Date.now(); };
    ['scroll', 'click', 'keydown', 'mousemove', 'touchstart'].forEach(ev => window.addEventListener(ev, recordActivity, { passive: true }));

    document.addEventListener('book-nav', () => {
        recordActivity();
        totalFlipsInSession++;
        window.pendingPageTurns = (window.pendingPageTurns || 0) + 1;
    });

    // შენი მონაცემების გაგზავნის ფუნქცია შეცდომების ლოგირებით (უცვლელი)
    const sendDataToDB = async (secs, isNew, turns = 0) => {
        try {
            const { error } = await sbClient.rpc('track_activity_master', {
                p_slug: trackingSlug,
                p_seconds: secs,
                p_visitor_id: reader.id,
                p_display_name: reader.name,
                p_is_new_session: isNew,
                p_page_turns: turns,
                p_device: deviceType,
                p_browser: fullInfo,
                p_auth_user_id: authUserId
            });

            if (error) {
                /* analytics error silenced */
            } else {
                hasRegisteredInDB = true;
            }
        } catch (e) { /* network error silenced */ }
    };

    if (isNewSession) sessionStorage.setItem(sessionKey, 'true');

    const sendPing = async (isClosing = false) => {
        const now = Date.now();
        const currentTurns = window.pendingPageTurns || 0;

        if (!hasRegisteredInDB && totalFlipsInSession === 0) {
            if (isClosing || (now - lastUserActivity) > 300000) return;
        }

        // 🚀 თუ აუდიო უკრავს, AFK რეჟიმი არ ირთვება!
        const isAudioPlaying = (typeof syncAudioPlayer !== 'undefined' && syncAudioPlayer && !syncAudioPlayer.paused);
        const isAFK = (now - lastUserActivity) > 300000 && !isAudioPlaying;

        if ((document.hidden || isAFK) && !isClosing) {
            lastPingTime = now;
            return;
        }
        const elapsedSeconds = Math.floor((now - lastPingTime) / 1000);
        if (elapsedSeconds < 1 && currentTurns === 0) return;

        await sendDataToDB(elapsedSeconds, (isNewSession && !hasRegisteredInDB), currentTurns);
        lastPingTime = now;
        window.pendingPageTurns = 0;
    };

    if (window.analyticsInterval) clearInterval(window.analyticsInterval);
    window.analyticsInterval = setInterval(() => sendPing(false), 20000);
    window.addEventListener('pagehide', () => sendPing(true));
}/* =========================================
   FULL ANALYTICS UI LOGIC (With Reset & Sync)
   ========================================= */
function setupAnalyticsUI() {
    const btn = document.getElementById('analytics-btn');
    const modal = document.getElementById('analytics-modal');
    const closeBtn = document.getElementById('close-analytics-btn');
    const resetBtn = document.getElementById('reset-stats-btn');

    const valVisits = document.getElementById('stat-visits');
    const valTime = document.getElementById('stat-time');
    const valAvg = document.getElementById('stat-avg');
    const valLastSeen = document.getElementById('stat-last-seen');
    const topReadersList = document.getElementById('top-readers-list');

    let uiAutoRefresh = null;

    const loadAnalyticsData = async () => {
        if (!btn) return;

        // მონაცემების წამოღება
        const { data: bookData } = await sbClient.from('book_analytics').select('*').eq('slug', CURRENT_BOOK_SLUG).single();
        const { data: readersData } = await sbClient.from('reader_profiles').select('*').eq('last_book_slug', CURRENT_BOOK_SLUG).gt('total_seconds', 0).order('total_seconds', { ascending: false }).limit(50);
        const { data: latestVisitors } = await sbClient.from('reader_profiles').select('display_name, last_seen').eq('last_book_slug', CURRENT_BOOK_SLUG).order('last_seen', { ascending: false }).limit(5);

        // 1. ბარათები
        if (bookData) {
            // თუ ვიზიტები მაინც 0-ია ეკრანზე, ვაჩვენებთ 1-ს (რადგან ვიღაცამ ხომ დააგენერირა ეგ წამები)
            const displayTotalVisits = Math.max(bookData.total_visits || 1, 1);
            const displayUniqueVisits = Math.max(bookData.unique_visits || 1, 1);

            valVisits.innerHTML = `<span class="val-white">${displayTotalVisits}</span><span class="val-dim">/</span><span class="val-accent">${displayUniqueVisits}</span>`;
            valTime.innerText = formatDuration(bookData.total_seconds);

            // 🚀 უსაფრთხო მათემატიკა საშუალო დროისთვის (Total Seconds / Total Visits)
            let avgSeconds = Math.round((bookData.total_seconds || 0) / displayTotalVisits);
            valAvg.innerText = formatDuration(avgSeconds);

            const turnsEl = document.getElementById('stat-turns');
            if (turnsEl) turnsEl.innerText = bookData.page_turns || 0;
        }

        // 2. Last Activity
        if (latestVisitors && latestVisitors.length > 0) {
            valLastSeen.innerHTML = '';
            latestVisitors.forEach(v => {
                const date = new Date(v.last_seen);
                valLastSeen.innerHTML += `
                    <div style="display:flex; justify-content:space-between; border-bottom:1px solid #333; padding-bottom:2px; margin-bottom: 4px;">
                        <span style="color:#ccc;">${v.display_name}</span>
                        <span style="color:#666; font-size:0.7rem;">${date.toLocaleString('ka-GE', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    </div>`;
            });
        } else {
            valLastSeen.innerText = "Never";
        }

        // 3. Top Readers List
        if (readersData && topReadersList) {
            topReadersList.innerHTML = '';
            const isAdmin = document.body.classList.contains('is-admin');

            readersData.forEach((reader, index) => {
                const row = document.createElement('div');
                row.className = 'reader-row';
                row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; padding: 10px 0; border-bottom: 1px solid #222;';

                const lastActive = new Date(reader.last_seen);
                const isOnline = (new Date() - lastActive) < 5 * 60 * 1000;
                const statusDot = isOnline ? '<span class="status-dot online" title="Online Now"></span>' : '<span class="status-dot offline" title="Offline"></span>';

                // Left: Rank & Name
                const leftSection = document.createElement('div');
                leftSection.style.cssText = 'display:flex; align-items:center; gap:12px;';
                leftSection.innerHTML = `<div class="reader-rank">${index + 1}</div>`;

                const nameContainer = document.createElement('div');
                nameContainer.style.cursor = isAdmin ? 'pointer' : 'default';
                nameContainer.title = isAdmin ? "Double click to rename" : "";

                // 🚀 "Verified User" ლოგიკა
                const isRegistered = reader.user_id !== null;
                const verifiedBadge = isRegistered ?
                    `<span class="material-icons-outlined" style="font-size: 14px; color: #10b981; margin-left: 4px; vertical-align: middle;" title="Registered User">verified</span>`
                    : '';

                nameContainer.innerHTML = `<span style="font-weight:bold; color:#fff;">${reader.display_name}</span>${verifiedBadge} ${statusDot}`;

                if (isAdmin) {
                    const triggerEdit = async () => {
                        const newName = prompt("შეიყვანე ახალი სახელი:", reader.display_name);
                        if (newName && newName.trim() !== "" && newName !== reader.display_name) {
                            await sbClient.from('reader_profiles').update({ display_name: newName.trim() }).eq('visitor_id', reader.visitor_id);
                            loadAnalyticsData();
                        }
                    };
                    nameContainer.ondblclick = triggerEdit;
                    let lastTap = 0;
                    nameContainer.addEventListener('touchstart', (e) => {
                        const now = Date.now();
                        if (now - lastTap < 300) { e.preventDefault(); triggerEdit(); }
                        lastTap = now;
                    });
                }
                leftSection.appendChild(nameContainer);

                // Right: Icons & Stats
                const rightSection = document.createElement('div');
                rightSection.style.cssText = 'display:flex; align-items:center; gap:15px;';

                const metaIcons = document.createElement('div');
                metaIcons.style.cssText = 'display:flex; align-items:center; gap:10px;';

                // ვიზიტები (დაზღვეული)
                const visitCount = Math.max(reader.visit_count || 1, 1);
                const badgeHtml = `<span style="display:inline-flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:bold; background:rgba(168, 85, 247, 0.15); color:#d8b4fe; border-radius:50%; width:22px; height:22px; border:1px solid rgba(168, 85, 247, 0.3);" title="სულ ვიზიტი: ${visitCount}">${visitCount}</span>`;

                const dType = reader.device_type || 'desktop';
                const bInfo = reader.browser_info || 'Unknown';
                const deviceIcon = (dType === 'mobile') ? 'smartphone' : 'laptop';

                const deviceHtml = `<span class="material-icons-outlined" 
                    style="font-size:17px; color:#555; cursor:help;" 
                    title="${dType} / ${bInfo}">${deviceIcon}</span>`;

                metaIcons.innerHTML = badgeHtml + deviceHtml;

                const statsDiv = document.createElement('div');
                statsDiv.style.cssText = 'text-align:right; min-width:90px;';
                statsDiv.innerHTML = `
                    <div style="color:#a855f7; font-weight:bold; font-size:0.9rem;">${formatDuration(reader.total_seconds)}</div>
                    <div style="font-size:0.7rem; color:#10b981;">📖 ${reader.page_turns || 0} flips</div>`;

                rightSection.appendChild(metaIcons);
                rightSection.appendChild(statsDiv);

                row.appendChild(leftSection);
                row.appendChild(rightSection);
                topReadersList.appendChild(row);
            });
        }
    };

    const stopRefresh = () => {
        modal.classList.remove('active');
        if (uiAutoRefresh) { clearInterval(uiAutoRefresh); uiAutoRefresh = null; }
    };

    if (btn) {
        btn.onclick = async () => {
            modal.classList.add('active');
            if (!uiAutoRefresh) uiAutoRefresh = setInterval(loadAnalyticsData, 5000);
            await loadAnalyticsData();
        };
    }

    if (closeBtn) closeBtn.onclick = stopRefresh;
    if (modal) modal.onclick = (e) => { if (e.target === modal) stopRefresh(); };

    if (resetBtn) {
        resetBtn.onclick = async () => {
            if (!confirm("სტატისტიკის სრული განულება?")) return;
            await sbClient.rpc('reset_book_analytics', { p_slug: CURRENT_BOOK_SLUG });
            location.reload();
        };
    }
}
/* ============================================================

   GLOSSARY & PORTAL SYSTEM - SCOPE FIXED

   ============================================================ */
// 1. ფუნქციების გლობალური დეკლარირება (Window Scope)
window.showGlossaryPopup = function(word, text) {
    const existing = document.getElementById('glossary-portal-popup');
    if (existing) existing.remove();
    const portalPop = document.createElement('div');
    portalPop.id = 'glossary-portal-popup';
    portalPop.className = 'portal-overlay';
    portalPop.innerHTML = `

        <div class="portal-content">

            <div class="portal-header">

                <span class="portal-title">${word}</span>

                <button class="portal-close" id="close-portal-btn">&times;</button>

            </div>

            <div class="portal-body">${text}</div>

        </div>

    `;
    document.body.appendChild(portalPop);
    // ანიმაციის გააქტიურება
    requestAnimationFrame(() => portalPop.classList.add('active'));
    const closePortal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        portalPop.classList.remove('active');
        setTimeout(() => portalPop.remove(), 300);
        document.removeEventListener('click', handleOutsideClick);
    };
    portalPop.querySelector('#close-portal-btn').onclick = closePortal;
    const handleOutsideClick = (e) => {
        const content = portalPop.querySelector('.portal-content');
        if (content && !content.contains(e.target) && e.target.id !== 'close-portal-btn') {
            closePortal();
        }
    };
    setTimeout(() => {
        document.addEventListener('click', handleOutsideClick);
    }, 100);
};
window.renderGlossary = function() {
    const glossaryList = document.getElementById('glossary-list');
    if (!glossaryList) return;
    glossaryList.innerHTML = '';
    chaptersData.forEach((chapter) => {
        const content = (currentLanguage === 'en') ? (chapter.content_en || "") : (chapter.content || "");
        const temp = document.createElement('div');
        temp.innerHTML = content;
        const footnotes = temp.querySelectorAll('.footnote-trigger');
        if (footnotes.length > 0) {
            const group = document.createElement('div');
            group.className = 'glossary-chapter-group collapsed';
            const chTitle = document.createElement('div');
            chTitle.className = 'glossary-chapter-title';
            chTitle.innerHTML = `

                <span class="chapter-label">${getChapterTitle(chapter, currentLanguage)}</span>

                <div class="chapter-arrow-container">

                    <span class="chapter-arrow"></span>

                </div>

            `;
            chTitle.onclick = () => group.classList.toggle('collapsed');
            const itemsContainer = document.createElement('div');
            itemsContainer.className = 'glossary-items-container';
            footnotes.forEach((fn, fnIndex) => {
                const item = document.createElement('div');
                item.className = 'glossary-item';
                // ✅ FIX: აქ ვიღებთ ჯერ დარედაქტირებულ სათაურს, და თუ არ არის - ტექსტს
                const word = fn.getAttribute('data-title') || fn.innerText;
                const definition = fn.getAttribute('data-content');
                item.innerHTML = `

                    <span class="glossary-item-number">${fnIndex + 1}</span>

                    <span class="glossary-item-word">${word}</span>

                `;
                item.onclick = (e) => {
                    e.stopPropagation();
                    window.showGlossaryPopup(word, definition);
                };
                itemsContainer.appendChild(item);
            });
            group.appendChild(chTitle);
            group.appendChild(itemsContainer);
            glossaryList.appendChild(group);
        }
    });
};
// 2. Event Listeners — run immediately (script loads after DOM is ready)
(function() {
    const glossaryBtn = document.getElementById('open-glossary-btn');
    const glossaryModal = document.getElementById('glossary-modal');
    const closeGlossary = document.getElementById('close-glossary-modal');
    if (glossaryBtn) {
        glossaryBtn.onclick = () => {
            window.renderGlossary();
            glossaryModal.classList.add('active');
        };
    }
    if (closeGlossary) {
        closeGlossary.onclick = () => glossaryModal.classList.remove('active');
    }
    if (glossaryModal) {
        glossaryModal.onclick = (e) => {
            if (e.target === glossaryModal) glossaryModal.classList.remove('active');
        };
    }
})();
// სისტემის გაშვება
setTimeout(initAnalytics, 2000);

function updateProgressBar(currentLoc, totalPaps) {
    const bar = document.getElementById('reading-progress-bar');
    const label = document.getElementById('reading-progress-label');
    const container = document.getElementById('reading-progress-container');
    if (!bar || !label) return;
    let percentage = 0;
    if (currentLoc > 1) {
        percentage = ((currentLoc - 1) / totalPaps) * 100;
    }
    if (percentage > 100) percentage = 100;
    if (percentage < 0) percentage = 0;
    // 1. ზოლის განახლება
    bar.style.width = percentage + "%";
    label.innerText = Math.round(percentage) + "%";
    // 2. ✅ SMART POSITIONING LOGIC
    // ვასუფთავებთ ძველ კლასებს
    label.classList.remove('near-start', 'near-end');
    if (percentage < 5) {
        // თუ 5%-ზე ნაკლებია -> დასაწყისის რეჟიმი (მარცხნივ არ გავარდეს)
        label.classList.add('near-start');
    } else if (percentage > 95) {
        // თუ 95%-ზე მეტია -> დასასრულის რეჟიმი (მარჯვნივ არ გავარდეს)
        label.classList.add('near-end');
    }
    // თუ 5% და 95% შორისაა -> დეფოლტი (შუაში), კლასების გარეშე
    // გამოჩენა
    if (container) {
        container.classList.add('active');
        clearTimeout(window.progressTimeout);
        window.progressTimeout = setTimeout(() => {
            container.classList.remove('active');
        }, 2000);
    }
}
// ==========================================
// ✅ UNIVERSAL BUTTON BINDER
// ==========================================
function setupAllButtons() {
    // დამხმარე: ბაზაში გაგზავნა
    const globalPushToDB = async (statusEl) => {
        if (!currentBookId) {
            alert("Error: No ID");
            throw new Error("No ID");
        }
        if (statusEl) statusEl.innerText = "Saving...";
        try {
            // META UPDATE (თუ სეთინგები ღიაა)
            const titleInput = document.getElementById('input-book-title');
            if (titleInput && titleInput.value.trim() !== "") {
                bookMeta.title = titleInput.value;
                bookMeta.subtitle = document.getElementById('input-book-subtitle').value;
                bookMeta.title_en = document.getElementById('input-book-title-en').value;
                bookMeta.subtitle_en = document.getElementById('input-book-subtitle-en').value;
                if (document.getElementById('input-book-desc')) bookMeta.description = document.getElementById('input-book-desc').value;
                if (document.getElementById('input-book-desc-en')) bookMeta.description_en = document.getElementById('input-book-desc-en').value;
                bookMeta.genre_ka = document.getElementById('input-book-genre').value;
                bookMeta.genre_en = document.getElementById('input-book-genre-en').value;
                bookMeta.published_year = document.getElementById('input-book-year').value;
                const seoInput = document.getElementById('input-book-seo');
                const seoInputEn = document.getElementById('input-book-seo-en');

            }
            const {
                error
            } = await sbClient.from('book_projects').update({
                title: bookMeta.title,
                subtitle: bookMeta.subtitle,
                cover_image: bookMeta.coverImage,
                chapters: chaptersData,
                title_en: bookMeta.title_en,
                subtitle_en: bookMeta.subtitle_en,
                description: bookMeta.description,
                description_en: bookMeta.description_en,
                seo_description: bookMeta.seo_description,
                seo_description_en: bookMeta.seo_description_en,
                genre_ka: bookMeta.genre_ka,
                genre_en: bookMeta.genre_en,
                published_year: bookMeta.published_year
            }).eq('id', currentBookId);
            if (error) throw error;
            localStorage.setItem('cached_book_' + CURRENT_BOOK_SLUG, JSON.stringify({
                ...bookMeta,
                id: currentBookId,
                chapters: chaptersData,
                cover_image: bookMeta.coverImage,
                slug: CURRENT_BOOK_SLUG
            }));
            updateStaticUI();
            if (statusEl) statusEl.innerText = "Saved!";
            setTimeout(() => {
                if (statusEl) statusEl.innerText = "";
            }, 2000);
        } catch (err) {
            /* save error silenced */
            if (statusEl) statusEl.innerText = "Error!";
            throw err;
        }
    };
    // 1. SAVE SETTINGS
    const saveMetaBtn = document.getElementById('save-meta-btn');
    if (saveMetaBtn) {
        saveMetaBtn.onclick = async (e) => {
            e.preventDefault();
            const originalText = saveMetaBtn.innerText;
            saveMetaBtn.innerText = "SAVING...";
            saveMetaBtn.disabled = true;
            try {
                if (window.pendingCoverFile) {
                    const uploadedUrl = await uploadCoverToStorage(window.pendingCoverFile);
                    if (uploadedUrl) {
                        bookMeta.coverImage = uploadedUrl;
                        window.pendingCoverFile = null;
                    }
                }
                await globalPushToDB(null);
                saveMetaBtn.innerText = "SAVED! ✓";
                saveMetaBtn.style.backgroundColor = "#28a745";
                setTimeout(() => {
                    saveMetaBtn.innerText = originalText;
                    saveMetaBtn.disabled = false;
                    saveMetaBtn.style.backgroundColor = "";
                    document.getElementById('settings-form').style.display = 'none';
                    isEditingSettings = false;
                }, 1500);
            } catch (error) {
                saveMetaBtn.innerText = "ERROR";
                saveMetaBtn.style.backgroundColor = "#d9534f";
                setTimeout(() => {
                    saveMetaBtn.innerText = originalText;
                    saveMetaBtn.disabled = false;
                    saveMetaBtn.style.backgroundColor = "";
                }, 2000);
            }
        };
    }
    // 2. SAVE DRAFT
    const draftBtn = document.getElementById('save-draft-btn');
    if (draftBtn) {
        draftBtn.onclick = async () => {
            const status = document.getElementById('save-status');
            draftBtn.innerText = "Saving...";
            draftBtn.disabled = true;

            // 🛑 CRITICAL CHANGE: ვინახავთ მიმდინარე მდგომარეობას (Split იქნება თუ Single)
            saveCurrentStateToMemory();

            renderChaptersList();
            try {
                await globalPushToDB(status);
            } catch (e) {}
            draftBtn.innerText = "Save Draft";
            draftBtn.disabled = false;
        };
    }

    // 3. PUBLISH
    const pubBtn = document.getElementById('publish-btn');
    if (pubBtn) {
        pubBtn.onclick = async () => {
            if(!confirm("Publish changes?")) return;
            const status = document.getElementById('save-status');
            pubBtn.innerText = "Publishing...";
            pubBtn.disabled = true;

            try {
                // 🛑 CRITICAL CHANGE: ჯერ ვიღებთ ინფორმაციას ედიტორებიდან
                saveCurrentStateToMemory();

                // შემდეგ ვაახლებთ Public ველებს (content/content_en) და სათაურებს
                const ch = chaptersData[selectedChapterIndex];

                // KA Public
                ch.content = ch.draft_content;
                const tempKa = document.createElement('div'); tempKa.innerHTML = ch.content;
                ch.title = extractTitleFromHTML(ch.content); // ეს ფუნქცია ქვემოთ დავამატე

                // EN Public
                ch.content_en = ch.draft_content_en;
                const tempEn = document.createElement('div'); tempEn.innerHTML = ch.content_en;
                ch.title_en = extractTitleFromHTML(ch.content_en);

                renderChaptersList();
                await globalPushToDB(status);
                loadChapter(selectedChapterIndex);
                await renderBook();

                if (typeof window.renderGlossary === 'function') window.renderGlossary();

            } catch(e) {
                /* publish error silenced */
                alert("Error saving!");
            }

            pubBtn.innerText = "Publish";
            pubBtn.disabled = false;
        };
    }
    // 4. UNPUBLISH
    const unpubBtn = document.getElementById('unpublish-btn');
    if (unpubBtn) {
        unpubBtn.onclick = async () => {
            if (!confirm("Hide this chapter?")) return;
            const status = document.getElementById('save-status');
            unpubBtn.innerText = "...";
            const currentHTML = quill.root.innerHTML;
            if (editorLanguage === 'ka') {
                chaptersData[selectedChapterIndex].draft_content = currentHTML;
                chaptersData[selectedChapterIndex].content = "";
            } else {
                chaptersData[selectedChapterIndex].draft_content_en = currentHTML;
                chaptersData[selectedChapterIndex].content_en = "";
            }
            renderChaptersList();
            await globalPushToDB(status);
            renderBook();
            unpubBtn.innerText = "Unpublish";
        };
    }
    // 5. DISCARD DRAFT
    const discardBtn = document.getElementById('discard-draft-btn');
    if (discardBtn) {
        discardBtn.onclick = async () => {
            if (!confirm("Discard changes?")) return;
            const status = document.getElementById('save-status');
            discardBtn.innerText = "...";
            discardBtn.disabled = true;
            const ch = chaptersData[selectedChapterIndex];
            if (editorLanguage === 'ka') ch.draft_content = ch.content || "";
            else ch.draft_content_en = ch.content_en || "";
            loadChapter(selectedChapterIndex);
            renderChaptersList();
            await globalPushToDB(status);
            await renderBook();
            discardBtn.innerText = "Discard Draft";
            discardBtn.disabled = false;
        };
    }
    // 6. ADD CHAPTER
    const addPageBtn = document.getElementById('add-page-btn');
    if (addPageBtn) {
        addPageBtn.onclick = () => {
            if (chaptersData[selectedChapterIndex]) {
                const currentVal = quill.root.innerHTML;
                if (editorLanguage === 'ka') chaptersData[selectedChapterIndex].draft_content = currentVal;
                else chaptersData[selectedChapterIndex].draft_content_en = currentVal;
            }
            chaptersData.push({
                id: Date.now(),
                title: "New Chapter",
                content: "",
                content_en: "",
                draft_content: "<h1>New Chapter</h1><p>Write here...</p>",
                draft_content_en: "<h1>New Chapter</h1><p>Write here...</p>",
                title_en: "New Chapter"
            });
            renderChaptersList();
            selectedChapterIndex = chaptersData.length - 1;
            loadChapter(selectedChapterIndex);
        };
    }
    // 7. COVER IMAGES
    const coverInput = document.getElementById('input-cover-image');
    const removeCoverBtn = document.getElementById('remove-cover-btn');
    const coverPreview = document.getElementById('cover-preview');
    if (coverInput) {
        coverInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                window.pendingCoverFile = file;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    coverPreview.style.backgroundImage = `url(${ev.target.result})`;
                    coverPreview.innerText = "";
                };
                reader.readAsDataURL(file);
            }
        };
    }
    if (removeCoverBtn) {
        removeCoverBtn.onclick = () => {
            coverInput.value = "";
            coverPreview.style.backgroundImage = "none";
            coverPreview.innerText = "Cover Removed";
            bookMeta.coverImage = null;
            window.pendingCoverFile = null;
        };
    }
}
/* ============================================================

   READER INTERACTION (DYNAMIC TOOLTIP FIX)

   ============================================================ */
let _readerInteractionsInitialized = false;
function setupReaderInteractions() {
    if (_readerInteractionsInitialized) return;
    _readerInteractionsInitialized = true;

    let globalTooltip = document.getElementById('global-footnote-tooltip');
    if (!globalTooltip) {
        globalTooltip = document.createElement('div');
        globalTooltip.id = 'global-footnote-tooltip';
        document.body.appendChild(globalTooltip);
    }
    let hideTimer = null;
    const isMobile = window.innerWidth <= 768;

    if (isMobile) {
        attachMobileTriggers();
    }

    function handleMouseOver(e) {
        if (isMobile) return;
        const target = e.target.closest('.footnote-trigger');
        const isTooltip = e.target.closest('#global-footnote-tooltip');
        if (target) {
            clearTimeout(hideTimer);
            showTooltip(target, globalTooltip);
        } else if (isTooltip) {
            clearTimeout(hideTimer);
        }
    }

    function handleMouseOut(e) {
        if (isMobile) return;
        const target = e.target.closest('.footnote-trigger');
        const isTooltip = e.target.closest('#global-footnote-tooltip');
        if (target || isTooltip) {
            hideTimer = setTimeout(() => {
                globalTooltip.classList.remove('active');
                setTimeout(() => {
                    if (!globalTooltip.classList.contains('active')) {
                        globalTooltip.style.display = 'none';
                    }
                }, 200);
            }, 300);
        }
    }

    document.body.addEventListener('mouseover', handleMouseOver);
    document.body.addEventListener('mouseout', handleMouseOut);
}
// ცალკე ფუნქცია Tooltip-ის ასაწყობად და საჩვენებლად
function showTooltip(target, globalTooltip) {
    const content = target.getAttribute('data-content');
    const index = target.getAttribute('data-fn-index') || "";
    const displayTitle = target.getAttribute('data-title') || target.textContent;
    if (!content) return;
    globalTooltip.innerHTML = `

        <div class="footnote-title">

            <span style="color: #a855f7; margin-right: 8px !important;">${index}.</span>&nbsp;${displayTitle}

        </div>

        <div class="footnote-content" style="padding: 10px 14px;">${content}</div>

    `;
    globalTooltip.style.display = 'flex';
    globalTooltip.style.opacity = '0';
    const rect = target.getBoundingClientRect();
    const tooltipWidth = 320;
    let left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
    // ეკრანის საზღვრების შემოწმება
    if (left < 10) left = 10;
    if (left + tooltipWidth > window.innerWidth - 10) left = window.innerWidth - tooltipWidth - 10;
    const realHeight = globalTooltip.offsetHeight || 150;
    let top = rect.top - realHeight - 15;
    if (top < 10) top = rect.bottom + 15; // თუ ზემოდან არ ეტევა, ქვემოთ ვაჩვენოთ
    globalTooltip.style.left = `${left}px`;
    globalTooltip.style.top = `${top}px`;
    requestAnimationFrame(() => {
        globalTooltip.classList.add('active');
        globalTooltip.style.opacity = '1';
    });
}

function attachMobileTriggers() {
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) return;

    // 🚀 დელეგირება: ვუსმენთ მთელ დოკუმენტს contextmenu-ზე.
    // მობილურზე contextmenu არის იგივე "Long Press".
    document.addEventListener('contextmenu', function(e) {
        const target = e.target.closest('.footnote-trigger');

        if (target) {
            e.preventDefault(); // ბლოკავს აიფონის სისტემურ მენიუს
            e.stopPropagation(); // ბლოკავს გვერდის გადაფურცვლას

            const content = target.getAttribute('data-content');
            const index = target.getAttribute('data-fn-index') || "";
            const displayTitle = target.getAttribute('data-title') || target.textContent;
            const titleHtml = `<span style="color:#a855f7; margin-right: 8px !important;">${index}.</span>&nbsp;${displayTitle}`;

            window.showMobilePortal(titleHtml, content);

            if (navigator.vibrate) navigator.vibrate(40);
        }
    }, { capture: true });

    // დამატებითი დაცვა: Tap-ზე რომ არ გადაფურცლოს გვერდი შემთხვევით
    document.addEventListener('click', function(e) {
        if (e.target.closest('.footnote-trigger')) {
            e.stopPropagation();
        }
    }, true);
}
// გლობალური ფუნქცია მობილური პორტალისთვის
window.showMobilePortal = function(title, text) {
    const existing = document.getElementById('mobile-footnote-portal');
    if (existing) existing.remove();
    const portal = document.createElement('div');
    portal.id = 'mobile-footnote-portal';
    portal.innerHTML = `

        <div class="portal-overlay" id="portal-bg"></div>

        <div class="portal-card">

            <div class="portal-header">

                <strong>${title}</strong>

                <button class="portal-close-btn" id="portal-close">&times;</button>

            </div>

            <div class="portal-body">${text}</div>

        </div>

    `;
    document.body.appendChild(portal);
    const closePortal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        portal.classList.add('closing');
        setTimeout(() => portal.remove(), 200);
    };
    document.getElementById('portal-close').onclick = closePortal;
    document.getElementById('portal-bg').onclick = closePortal;
};


// 🧠 SMART SYNC ENGINE
// 🧠 SMART SYNC ENGINE (PROPORTIONAL)
function enableSmartSync() {
    const btn = document.getElementById('sync-scroll-btn');
    const q1 = document.querySelector('#editor-container .ql-editor'); // English
    const q2 = document.querySelector('#editor-container-dual .ql-editor'); // Georgian

    if (!btn || !q1 || !q2) return;

    let isSyncing = false;
    let syncActive = false;

    btn.onclick = () => {
        syncActive = !syncActive;
        btn.classList.toggle('active', syncActive);
        btn.style.color = syncActive ? '#a855f7' : '';
        if(syncActive) alignView(q1, q2);
    };

    const alignView = (source, target) => {
        if (!syncActive || isSyncing) return;
        isSyncing = true;

        const srcHeaders = Array.from(source.querySelectorAll('h1, h2, h3'));
        const trgHeaders = Array.from(target.querySelectorAll('h1, h2, h3'));

        // Fallback: თუ სათაურები არ არის, ჩვეულებრივი პროცენტით
        if (srcHeaders.length === 0 || trgHeaders.length === 0) {
            const p = source.scrollTop / (source.scrollHeight - source.clientHeight);
            target.scrollTop = p * (target.scrollHeight - target.clientHeight);
            setTimeout(() => isSyncing = false, 20);
            return;
        }

        // 1. ვპოულობთ რომელ სეგმენტში ვართ (რომელი სათაურის ქვემოთ)
        let idx = -1;
        for (let i = 0; i < srcHeaders.length; i++) {
            if (source.scrollTop >= srcHeaders[i].offsetTop - 20) { // 20px ტოლერანტობა
                idx = i;
            } else {
                break;
            }
        }

        // 2. განვსაზღვრავთ საწყის და საბოლოო წერტილებს (Source)
        // თუ idx -1-ია, ესეიგი პირველ სათაურამდე ვართ (შესავალი)
        const srcStart = (idx === -1) ? 0 : srcHeaders[idx].offsetTop;

        // თუ ბოლო სათაურია, დასასრული არის მთლიანი სქროლის ბოლო
        // დაკლება (clientHeight) საჭიროა, რომ ბოლომდე ჩავიდეს
        const srcBottomLimit = source.scrollHeight - source.clientHeight;
        const srcEnd = (idx + 1 < srcHeaders.length) ? srcHeaders[idx + 1].offsetTop : srcBottomLimit;

        // 3. ვითვლით პროგრესს ამ სეგმენტში (0.0 - 1.0)
        const segmentHeight = srcEnd - srcStart;
        const progressInPixels = source.scrollTop - srcStart;

        let ratio = 0;
        if (segmentHeight > 0) {
            ratio = progressInPixels / segmentHeight;
        }
        // Ratio არ უნდა იყოს 1-ზე მეტი ან 0-ზე ნაკლები
        ratio = Math.max(0, Math.min(1, ratio));

        // 4. ვიღებთ შესაბამის კოორდინატებს Target-ში (Georgian)
        const trgStart = (idx === -1 || !trgHeaders[idx]) ? 0 : trgHeaders[idx].offsetTop;

        const trgBottomLimit = target.scrollHeight - target.clientHeight;
        const trgEnd = (idx + 1 < srcHeaders.length && trgHeaders[idx + 1]) ? trgHeaders[idx + 1].offsetTop : trgBottomLimit;

        // 5. ვითვლით Target-ის ახალ პოზიციას
        const targetSegmentHeight = trgEnd - trgStart;
        const newScrollTop = trgStart + (targetSegmentHeight * ratio);

        // 6. ვასრულებთ სქროლს (auto - რომ არ დაიგვიანოს, smooth - ლაგი ექნება)
        target.scrollTo({
            top: newScrollTop,
            behavior: 'auto'
        });

        // ძალიან სწრაფი გაშვება, რომ "Live" ეფექტი ჰქონდეს
        setTimeout(() => isSyncing = false, 20);
    };

    q1.onscroll = () => alignView(q1, q2);
    q2.onscroll = () => alignView(q2, q1);

    q1.onmouseenter = () => { if(syncActive) { q1.onscroll = () => alignView(q1, q2); q2.onscroll = null; } };
    q2.onmouseenter = () => { if(syncActive) { q2.onscroll = () => alignView(q2, q1); q1.onscroll = null; } };
}


/* ============================================================
   🚀 FLOATING SHARE BUTTON (DEEP LINKING)
   ============================================================ */
function initShareButton() {


    // 2. HTML ელემენტების შექმნა
    const container = document.createElement('div');
    container.id = 'floating-share-container';

    // 🛑 TranslatePress-ის და Google Translate-ის სრული ბლოკირება ამ ღილაკზე!
    container.setAttribute('translate', 'no');
    container.setAttribute('data-no-translation', '');
    container.setAttribute('data-no-dynamic-translation', '');
    container.classList.add('skiptranslate', 'notranslate');

    const bubble = document.createElement('div');
    bubble.id = 'share-bubble';
    // SVG იკონები (Facebook, X, LinkedIn, Copy)
    bubble.innerHTML = `
        <button class="share-icon-btn fb" title="Share on Facebook">
            <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </button>
        <button class="share-icon-btn x" title="Share on X">
            <svg viewBox="0 0 24 24"><path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/></svg>
        </button>
        <button class="share-icon-btn li" title="Share on LinkedIn">
            <svg viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        </button>
        <div class="share-divider"></div>
        <button class="share-icon-btn copy" title="Copy Link" style="position: relative;">
            <span class="material-icons-outlined">link</span>
            <div id="copy-tooltip">Copied!</div>
        </button>
    `;

    // 🚀 ტექსტის განსაზღვრა ენის მიხედვით
    const shareBtnText = currentLanguage === 'ka' ? 'გაზიარება' : 'Share';

    const mainBtn = document.createElement('button');
    mainBtn.id = 'main-share-btn';
    mainBtn.title = shareBtnText;

    // ✅ ვამატებთ აიკონს + ტექსტს სპეციალურ სპანში
    mainBtn.innerHTML = `
        <span class="material-icons-outlined">ios_share</span>
        <span class="share-text-label">${shareBtnText}</span>
    `;

    container.appendChild(bubble);
    container.appendChild(mainBtn);
    document.body.appendChild(container);

    // 3. ლოგიკა და ივენთები
    let isOpen = false;

    mainBtn.onclick = (e) => {
        e.stopPropagation();
        isOpen = !isOpen;
        bubble.classList.toggle('active', isOpen);
        mainBtn.classList.toggle('active', isOpen); // ვიყენებთ კლასს
    };

    // ეკრანზე დაჭერისას ბუშტის დახურვა
    document.addEventListener('click', (e) => {
        if (isOpen && !container.contains(e.target)) {
            isOpen = false;
            bubble.classList.remove('active');
            mainBtn.classList.remove('active'); // ვხსნით კლასს
        }
    });

    // გაზიარების ფუნქციები
    const getShareData = () => {
        // ყოველთვის იღებს იმ მომენტში არსებულ აქტიურ ლინკს (Hash-ის ჩათვლით)
        const url = window.location.href;
        const title = document.title;
        return { url, title };
    };

    bubble.querySelector('.fb').onclick = () => {
        const { url } = getShareData();
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank', 'width=600,height=400');
    };

    bubble.querySelector('.x').onclick = () => {
        const { url, title } = getShareData();
        window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent("Reading: " + title)}`, '_blank', 'width=600,height=400');
    };

    bubble.querySelector('.li').onclick = () => {
        const { url } = getShareData();
        window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`, '_blank', 'width=600,height=400');
    };

    // Copy to Clipboard ფუნქცია
    bubble.querySelector('.copy').onclick = () => {
        const { url } = getShareData();
        const tooltip = document.getElementById('copy-tooltip');

        navigator.clipboard.writeText(url).then(() => {
            tooltip.classList.add('show');
            setTimeout(() => tooltip.classList.remove('show'), 2000);
        }).catch(err => {
            /* copy error silenced */
            // Fallback ძველი ბრაუზერებისთვის
            const textArea = document.createElement("textarea");
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("copy");
            document.body.removeChild(textArea);
            tooltip.classList.add('show');
            setTimeout(() => tooltip.classList.remove('show'), 2000);
        });
    };
}


/* ============================================================
   🎧 AUDIO SYNC ENGINE (PROTOTYPE - MULTI-CHAPTER & MULTILINGUAL)
   ============================================================ */
let syncAudioPlayer = null;
let currentSyncId = -1;
let currentLoadedChapter = -1;
let currentTimings = [];

const GLOBAL_AUDIO_LIBRARY = {
    "beta": {
        en: [
            {
                chapter: 1,
                url: "https://zurabkostava.com/wp-content/uploads/2026/02/First-part.mp3",
                timings: [
                    { id: 0, start: "00:00.00", end: "00:06.06" },
                    { id: 1, start: "00:06.10", end: "00:15.29" },
                    { id: 2, start: "00:15.30", end: "00:41.00" },
                    { id: 3, start: "00:41.05", end: "00:41.08" },
                    { id: 4, start: "00:41.10", end: "01:11.12" },
                    { id: 5, start: "01:14.04", end: "02:00.05" },
                    { id: 6, start: "02:01.05", end: "02:02.00" },
                    { id: 7, start: "02:02.26", end: "03:46.00" },
                    { id: 8, start: "03:47.23", end: "05:41.00" },
                    { id: 9, start: "05:42.05", end: "07:10.13" },
                    { id: 10, start: "07:11.19", end: "08:42.05" },
                    { id: 11, start: "08:43.08", end: "09:48.03" },
                    { id: 12, start: "09:49.08", end: "11:12.21" }
                ]
            }
        ],
        ka: [
            {
                chapter: 1,
                url: "https://zurabkostava.com/wp-content/uploads/2026/02/Black-Sea.mp3",
                timings: [
                    { id: 0, start: "00:00.00", end: "00:03.50" },
                    { id: 1, start: "00:03.60", end: "00:07.20" }
                ]
            }
        ]
    }
};

function initAudioPrototype() {
    // ✅ თუ ღილაკი უკვე შექმნილია, მეორედ აღარ გაეშვას:
    if (document.getElementById('audio-proto-toggle')) return;

    const bookData = GLOBAL_AUDIO_LIBRARY[CURRENT_BOOK_SLUG];
    if (!bookData) return;

    const pageLang = document.documentElement.lang.toLowerCase();
    const currentLang = pageLang.includes('en') ? 'en' : 'ka';
    const currentLangChapters = bookData[currentLang];

    if (!currentLangChapters || currentLangChapters.length === 0) return;

    function parseTimeToSeconds(timeVal) {
        if (typeof timeVal === 'number') return timeVal;
        const parts = String(timeVal).split(':');
        if (parts.length === 2) return (parseFloat(parts[0]) * 60) + parseFloat(parts[1]);
        return parseFloat(timeVal) || 0;
    }

    currentLangChapters.forEach(ch => {
        ch.parsedTimings = ch.timings.map(t => ({
            id: t.id, start: parseTimeToSeconds(t.start), end: parseTimeToSeconds(t.end)
        }));
    });

    let isFocusMode = false;
    syncAudioPlayer = new Audio();



    if (!window.audioLockAttached) {
        const preventBookInteraction = (e) => {
            if (typeof syncAudioPlayer !== 'undefined' && syncAudioPlayer && !syncAudioPlayer.paused) {
                if (e.target.closest('.paper') || e.target.closest('.flipbook') || e.target.closest('[class*="turn"]') || e.target.closest('[class*="nav"]')) {
                    if (!e.target.closest('#audio-proto-container') && !e.target.closest('#audio-mini-player')) {
                        e.stopPropagation(); e.preventDefault();
                    }
                }
            }
        };
        window.addEventListener('mousedown', preventBookInteraction, true);
        window.addEventListener('touchstart', preventBookInteraction, true);
        window.addEventListener('click', preventBookInteraction, true);
        window.addEventListener('pointerdown', preventBookInteraction, true);
        window.addEventListener('keydown', (e) => {
            if (typeof syncAudioPlayer !== 'undefined' && syncAudioPlayer && !syncAudioPlayer.paused) {
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.stopPropagation(); e.preventDefault(); }
            }
        }, true);
        window.audioLockAttached = true;
    }

    const backdrop = document.createElement('div'); backdrop.id = 'audio-backdrop'; document.body.appendChild(backdrop);
    const teleprompter = document.createElement('div'); teleprompter.id = 'audio-teleprompter'; document.body.appendChild(teleprompter);
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'audio-proto-toggle';

    // 🚀 ✅ ვბლოკავთ თარგმნას პირდაპირ შექმნისას!
    toggleBtn.setAttribute('translate', 'no');
    toggleBtn.setAttribute('data-no-translation', '');
    toggleBtn.className = 'notranslate skiptranslate';

    toggleBtn.innerHTML = `<span class="material-icons-outlined">headphones</span> ${currentLang === 'ka' ? 'აუდიო ვერსია' : 'Audiobook'}`;

    // ✅ TranslatePress-ის მყისიერი ბლოკირება
    toggleBtn.setAttribute('translate', 'no');
    toggleBtn.setAttribute('data-no-translation', '');
    toggleBtn.setAttribute('data-no-dynamic-translation', '');
    toggleBtn.className = 'skiptranslate notranslate';

    document.body.appendChild(toggleBtn);
    requestAnimationFrame(() => {
        setTimeout(() => {
            toggleBtn.classList.add('animate-in');
        }, 100); // 100მს დაყოვნება უფრო რბილს ხდის შემოსვლას
    });

    const miniPlayer = document.createElement('div'); miniPlayer.id = 'audio-mini-player';
    miniPlayer.innerHTML = `<button class="audio-btn btn-prev"><span class="material-icons-outlined">skip_previous</span></button><button class="audio-btn play-btn btn-play"><span class="material-icons-outlined">play_arrow</span></button><button class="audio-btn stop-btn btn-stop"><span class="material-icons-outlined">stop</span></button><button class="audio-btn btn-next"><span class="material-icons-outlined">skip_next</span></button><div style="width:1px !important; height:20px !important; background:#444 !important; margin: 0 4px !important;"></div><button id="mini-expand" class="audio-btn" title="Focus Mode"><span class="material-icons-outlined">fullscreen</span></button>`;
    document.body.appendChild(miniPlayer);

    const fullPlayer = document.createElement('div'); fullPlayer.id = 'audio-proto-container';
    fullPlayer.innerHTML = `<button class="audio-btn btn-prev"><span class="material-icons-outlined">skip_previous</span></button><button class="audio-btn play-btn btn-play"><span class="material-icons-outlined">play_arrow</span></button><button class="audio-btn stop-btn btn-stop"><span class="material-icons-outlined">stop</span></button><button class="audio-btn btn-next"><span class="material-icons-outlined">skip_next</span></button><button id="audio-proto-speed">1x</button><div id="audio-proto-time">0.0s</div><div style="width:1px !important; height:25px !important; background:#444 !important; margin: 0 5px !important;"></div><button id="full-collapse" class="audio-btn" title="Minimize"><span class="material-icons-outlined">fullscreen_exit</span></button>`;
    document.body.appendChild(fullPlayer);

    const timeDisplay = document.getElementById('audio-proto-time');

    // 🎧 თავების მართვის მთავარი ფუნქცია (ჩატვირთვის დაცვით)
    function loadChapterAudio(chapterNum, timeToStart = 0, autoPlay = false) {
        if (currentLoadedChapter === chapterNum && syncAudioPlayer.src) {
            if (timeToStart > 0) syncAudioPlayer.currentTime = timeToStart;
            if (autoPlay) syncAudioPlayer.play();
            return;
        }

        const chData = currentLangChapters.find(c => c.chapter === chapterNum);
        if (!chData) return;

        currentLoadedChapter = chapterNum;
        currentTimings = chData.parsedTimings;
        syncAudioPlayer.src = chData.url;

        // 🚀 მაგია: ველოდებით, რომ ბრაუზერმა გაიაზროს ფაილი, სანამ დროს გადავახვევთ!
        const onReady = () => {
            syncAudioPlayer.currentTime = timeToStart;
            if (autoPlay) syncAudioPlayer.play();
            syncAudioPlayer.removeEventListener('loadedmetadata', onReady);
        };

        syncAudioPlayer.addEventListener('loadedmetadata', onReady);

        // ყოველი შემთხვევისთვის, თუ ფაილი უკვე ქეშშია
        if (syncAudioPlayer.readyState >= 1) {
            syncAudioPlayer.currentTime = timeToStart;
            if (autoPlay) syncAudioPlayer.play();
        }

    }

    // 🚧 ლამაზი შეტყობინება, როცა აუდიო არ არის მზად
    // 🚧 ლამაზი შეტყობინება, როცა აუდიო არ არის მზად
    function showAudioWIPMessage() {
        let toast = document.getElementById('audio-wip-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'audio-wip-toast';

            // 🚀 ✅ TranslatePress-ის მყისიერი ბლოკირება
            toast.setAttribute('translate', 'no');
            toast.setAttribute('data-no-translation', '');
            toast.setAttribute('data-no-dynamic-translation', '');
            toast.className = 'skiptranslate notranslate';

            toast.style.cssText = `
                position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
                background: rgba(168, 85, 247, 0.95); color: #fff; padding: 12px 24px;
                border-radius: 30px; font-size: 14px; font-weight: bold; font-family: inherit;
                box-shadow: 0 10px 25px rgba(0,0,0,0.6); z-index: 10001;
                pointer-events: none; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                display: flex; align-items: center; gap: 8px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
            `;

            // 🛑 საწყისი დამალვა ძალისმიერად (!important)
            toast.style.setProperty('opacity', '0', 'important');

            document.body.appendChild(toast);
        }

        const wipText = currentLang === 'en' ? 'Audio version in progress...' : 'აუდიო ვერსია მზადდება...';
        toast.innerHTML = `<span class="material-icons-outlined" style="font-size: 18px;">construction</span> ${wipText}`;

        // ანიმაციით გამოჩენა
        requestAnimationFrame(() => {
            toast.style.setProperty('opacity', '1', 'important'); // 🚀 გამოჩენა CSS-ის ჯიბრზე
            toast.style.transform = 'translateX(-50%) translateY(-20px)';
        });

        // 3 წამში გაქრობა
        if (window.wipToastTimer) clearTimeout(window.wipToastTimer);
        window.wipToastTimer = setTimeout(() => {
            toast.style.setProperty('opacity', '0', 'important'); // 🚀 გაქრობა CSS-ის ჯიბრზე
            toast.style.transform = 'translateX(-50%) translateY(0)';
        }, 3000);
    }
    // 🔍 ჭკვიანი მაძიებელი 3.1: მკაცრი გამიჯვნა მობილურსა და კომპიუტერზე
    function getChapterAndTimeForCurrentPage(chapters) {
        const flippedPapers = Array.from(document.querySelectorAll('.paper.flipped'));
        const unflippedPapers = Array.from(document.querySelectorAll('.paper:not(.flipped)'));
        const isMobile = window.innerWidth <= 768;

        const visibleFaces = [];

        if (isMobile) {
            // მობილურზე მხოლოდ 1 გვერდი ჩანს (მარჯვენა/front)
            if (unflippedPapers.length > 0) {
                const frontFace = unflippedPapers[0].querySelector('.front');
                if (frontFace) visibleFaces.push(frontFace);
            }
        } else {
            // დესკტოპზე ჩანს 2 გვერდი: ჯერ მარცხენა (back), მერე მარჯვენა (front)
            if (flippedPapers.length > 0) {
                const backFace = flippedPapers[flippedPapers.length - 1].querySelector('.back');
                if (backFace) visibleFaces.push(backFace);
            }
            if (unflippedPapers.length > 0) {
                const frontFace = unflippedPapers[0].querySelector('.front');
                if (frontFace) visibleFaces.push(frontFace);
            }
        }

        // 1. ვეძებთ სუფთა დასაწყისს
        for (let face of visibleFaces) {
            const syncElements = Array.from(face.querySelectorAll('[class*="sync-id-"]'));

            for (let el of syncElements) {
                const idClass = Array.from(el.classList).find(c => c.startsWith('sync-id-'));
                const chClass = Array.from(el.classList).find(c => c.startsWith('sync-ch-'));

                if (idClass && chClass) {
                    const allInstances = document.querySelectorAll(`.${chClass}.${idClass}`);

                    // თუ ამ აბზაცის პირველი ნაწილი ზუსტად ამ გვერდზეა, ე.ი სუფთაა!
                    if (allInstances.length > 0 && face.contains(allInstances[0])) {
                        const syncId = parseInt(idClass.replace('sync-id-', ''));
                        const chNum = parseInt(chClass.replace('sync-ch-', ''));

                        const chapterObj = chapters.find(c => c.chapter === chNum);
                        if (chapterObj) {
                            const timing = chapterObj.parsedTimings.find(t => t.id === syncId);
                            if (timing) return { chapter: chNum, time: timing.start };
                        }
                    }
                }
            }
        }

        // 2. (იშვიათი) თუ სუფთა აბზაცი არ არის, ვიღებთ უბრალოდ პირველივეს რასაც ვხედავთ
        for (let face of visibleFaces) {
            const firstSyncEl = face.querySelector('[class*="sync-id-"]');
            if (firstSyncEl) {
                const idClass = Array.from(firstSyncEl.classList).find(c => c.startsWith('sync-id-'));
                const chClass = Array.from(firstSyncEl.classList).find(c => c.startsWith('sync-ch-'));
                if (idClass && chClass) {
                    const syncId = parseInt(idClass.replace('sync-id-', ''));
                    const chNum = parseInt(chClass.replace('sync-ch-', ''));
                    const chapterObj = chapters.find(c => c.chapter === chNum);
                    if (chapterObj) {
                        const timing = chapterObj.parsedTimings.find(t => t.id === syncId);
                        if (timing) return { chapter: chNum, time: timing.start };
                    }
                }
            }
        }
        return null;
    }

    syncAudioPlayer.onplay = () => document.querySelectorAll('.btn-play').forEach(b => b.innerHTML = '<span class="material-icons-outlined">pause</span>');
    syncAudioPlayer.onpause = () => document.querySelectorAll('.btn-play').forEach(b => b.innerHTML = '<span class="material-icons-outlined">play_arrow</span>');

    syncAudioPlayer.onended = () => {
        const nextChapterNum = currentLoadedChapter + 1;
        const nextChData = currentLangChapters.find(c => c.chapter === nextChapterNum);
        if (nextChData) loadChapterAudio(nextChapterNum, 0, true);
        else document.querySelectorAll('.btn-stop')[0].click();
    };

    // 🎧 საწყისი ღილაკის ლოგიკა (გასწორებული: პლეერი არ ირთვება აუდიოს გარეშე)
    toggleBtn.onclick = async () => {
        // 1. ვამოწმებთ სესიას
        const session = await getCachedSession();

        if (!session) {
            showAudioAuthModal();
            return;
        }

        // 2. ჯერ ვადგენთ, გვაქვს თუ არა აუდიო მონაცემი ამ გვერდისთვის
        const loc = getChapterAndTimeForCurrentPage(currentLangChapters);

        if (loc) {
            // ✅ მხოლოდ თუ აუდიო ნაპოვნია:
            // ვმალავთ მთავარ ღილაკს და ვაჩენთ პლეერს
            toggleBtn.classList.add('hidden');
            miniPlayer.classList.add('active');

            // ვტვირთავთ და ვრთავთ
            loadChapterAudio(loc.chapter, loc.time, true);
            currentSyncId = -1;
        } else {
            // ❌ თუ აუდიო არ არის:
            // ვაჩვენებთ მხოლოდ შეტყობინებას, პლეერი რჩება დამალული
            showAudioWIPMessage();
        }
    };

    // ▶️ Play / Pause პლეერის შიგნიდან
    document.querySelectorAll('.btn-play').forEach(btn => btn.onclick = () => {
        if (syncAudioPlayer.paused) {
            if (syncAudioPlayer.currentTime === 0 || !syncAudioPlayer.src) {
                const loc = getChapterAndTimeForCurrentPage(currentLangChapters);
                if (loc) {
                    loadChapterAudio(loc.chapter, loc.time, true);
                    currentSyncId = -1;
                } else {
                    // ❌ მოხდა ისე, რომ პლეერი ღიაა, მაგრამ ცარიელ გვერდზე დააჭირა ფლეის
                    showAudioWIPMessage();
                }
            } else {
                // ✅ დაპაუზებული იყო და აგრძელებს. აქ Book-nav ავტომატურად დააბრუნებს სწორ გვერდზე!
                syncAudioPlayer.play();
                currentSyncId = -1;
            }
        } else {
            syncAudioPlayer.pause();
        }
    });

    document.querySelectorAll('.btn-next').forEach(btn => btn.onclick = () => {
        if (currentTimings.length === 0) return;
        const nextBlock = currentTimings.find(t => t.start > syncAudioPlayer.currentTime + 0.1);
        if (nextBlock) syncAudioPlayer.currentTime = nextBlock.start;
        else {
            const nextChData = currentLangChapters.find(c => c.chapter === currentLoadedChapter + 1);
            if (nextChData) loadChapterAudio(nextChData.chapter, 0, !syncAudioPlayer.paused);
        }
    });

    document.querySelectorAll('.btn-prev').forEach(btn => btn.onclick = () => {
        if (currentTimings.length === 0) return;
        const time = syncAudioPlayer.currentTime;
        const idx = currentTimings.findIndex(t => time >= t.start && time <= t.end);
        if (idx > 0) syncAudioPlayer.currentTime = (time - currentTimings[idx].start > 2.0) ? currentTimings[idx].start : currentTimings[idx - 1].start;
        else if (idx === 0 && time > 2.0) syncAudioPlayer.currentTime = currentTimings[0].start;
        else {
            const prevChData = currentLangChapters.find(c => c.chapter === currentLoadedChapter - 1);
            if (prevChData) {
                const lastTiming = prevChData.parsedTimings[prevChData.parsedTimings.length - 1];
                loadChapterAudio(prevChData.chapter, lastTiming ? lastTiming.start : 0, !syncAudioPlayer.paused);
            } else syncAudioPlayer.currentTime = 0;
        }
    });

    document.querySelectorAll('.btn-stop').forEach(btn => btn.onclick = () => {
        syncAudioPlayer.pause(); syncAudioPlayer.currentTime = 0; currentSyncId = -1;
        document.querySelectorAll('.sync-highlight').forEach(el => el.classList.remove('sync-highlight'));
        teleprompter.classList.remove('active'); backdrop.classList.remove('active');
        fullPlayer.classList.remove('active'); miniPlayer.classList.remove('active'); toggleBtn.classList.remove('hidden');
        isFocusMode = false; timeDisplay.innerText = "0.0s";
    });

    document.getElementById('mini-expand').onclick = () => {
        miniPlayer.classList.remove('active'); backdrop.classList.add('active'); fullPlayer.classList.add('active');
        isFocusMode = true; updateTeleprompter(syncAudioPlayer.currentTime);
    };
    const collapsePlayer = () => {
        backdrop.classList.remove('active'); fullPlayer.classList.remove('active'); teleprompter.classList.remove('active');
        miniPlayer.classList.add('active'); isFocusMode = false;
    };
    document.getElementById('full-collapse').onclick = collapsePlayer;
    backdrop.onclick = collapsePlayer;

    const speedBtn = document.getElementById('audio-proto-speed');
    const speeds = [1, 1.25, 1.5, 2, 0.75]; let speedIdx = 0;
    speedBtn.onclick = () => { speedIdx = (speedIdx + 1) % speeds.length; syncAudioPlayer.playbackRate = speeds[speedIdx]; speedBtn.innerText = speeds[speedIdx] + 'x'; };

    function updateTeleprompter(time) {
        if (!isFocusMode || currentTimings.length === 0) return;
        const activeIndex = currentTimings.findIndex(t => time >= t.start && time <= t.end);
        if (activeIndex !== -1) {
            let pText = "", cText = "", nText = "";
            const chapterClass = `sync-ch-${currentLoadedChapter}`;
            if (activeIndex > 0) {
                const pTargets = document.querySelectorAll(`.${chapterClass}.sync-id-${currentTimings[activeIndex - 1].id}`);
                if (pTargets.length) pText = Array.from(pTargets).map(el => el.innerText).join(' ');
            }
            const cTargets = document.querySelectorAll(`.${chapterClass}.sync-id-${currentTimings[activeIndex].id}`);
            if (cTargets.length) cText = Array.from(cTargets).map(el => el.innerText).join(' ');
            if (activeIndex + 1 < currentTimings.length) {
                const nTargets = document.querySelectorAll(`.${chapterClass}.sync-id-${currentTimings[activeIndex + 1].id}`);
                if (nTargets.length) nText = Array.from(nTargets).map(el => el.innerText).join(' ');
            }
            teleprompter.innerHTML = `${pText ? `<div class="tp-prev">${pText}</div>` : ''}<div class="tp-current">${cText}</div>${nText ? `<div class="tp-next">${nText}</div>` : ''}`;
            teleprompter.classList.add('active');
        }
    }

    let _lastHighlighted = [];
    syncAudioPlayer.ontimeupdate = () => {
        const time = syncAudioPlayer.currentTime;
        timeDisplay.textContent = time.toFixed(1) + 's';
        if (currentTimings.length === 0) return;
        const block = currentTimings.find(t => time >= t.start && time <= t.end);

        if (block && block.id !== currentSyncId) {
            currentSyncId = block.id;
            _lastHighlighted.forEach(el => el.classList.remove('sync-highlight'));
            const chapterClass = `sync-ch-${currentLoadedChapter}`;
            const targets = document.querySelectorAll(`.${chapterClass}.sync-id-${currentSyncId}`);
            _lastHighlighted = Array.from(targets);
            if (targets.length > 0) targets.forEach(el => el.classList.add('sync-highlight'));
            updateTeleprompter(time);
            if (!syncAudioPlayer.paused && typeof autoFlipToElement === 'function' && targets.length > 0) {
                autoFlipToElement(targets[0]);
            }
        } else if (!block && currentSyncId !== -1) {
            _lastHighlighted.forEach(el => el.classList.remove('sync-highlight'));
            _lastHighlighted = [];
            currentSyncId = -1;
            teleprompter.classList.remove('active');
        }
    };

    function smoothScrollLoop() {
        if (isFocusMode && !syncAudioPlayer.paused && currentTimings.length > 0) {
            const time = syncAudioPlayer.currentTime;
            const block = currentTimings.find(t => time >= t.start && time <= t.end);
            if (block) {
                const currentEl = teleprompter.querySelector('.tp-current');
                if (currentEl) {
                    const duration = block.end - block.start;
                    let startDelay = 2.0; let endDelay = 2.0;
                    if (duration <= (startDelay + endDelay)) { startDelay = duration * 0.15; endDelay = duration * 0.15; }
                    const scrollDuration = duration - (startDelay + endDelay);
                    let progress = 0;
                    if (scrollDuration > 0) {
                        progress = (time - (block.start + startDelay)) / scrollDuration;
                        progress = Math.max(0, Math.min(1, progress));
                    }
                    if (currentEl.offsetHeight > teleprompter.clientHeight - 100) {
                        const startY = currentEl.offsetTop - 50;
                        const endY = currentEl.offsetTop + currentEl.offsetHeight - teleprompter.clientHeight + 50;
                        teleprompter.scrollTo({ top: startY + (progress * (endY - startY)), behavior: 'auto' });
                    } else {
                        teleprompter.scrollTo({ top: currentEl.offsetTop - (teleprompter.clientHeight / 2) + (currentEl.clientHeight / 2), behavior: 'auto' });
                    }
                }
            }
        }
        requestAnimationFrame(smoothScrollLoop);
    }
    requestAnimationFrame(smoothScrollLoop);
}


/* ============================================================
   🚀 ფუნქცია: აუდიო ავტორიზაციის მოდალი (Multilingual)
   ============================================================ */
function showAudioAuthModal() {
    let modal = document.getElementById('audio-auth-alert');

    // თუ მოდალი ჯერ არ არსებობს, ვქმნით
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'audio-auth-alert';
        // ვბლოკავთ TranslatePress-ს, რომ ჩვენი ხელით დაწერილი თარგმანი არ აურიოს
        modal.className = 'notranslate skiptranslate';
        document.body.appendChild(modal);
    }

    // 🇬🇪/🇺🇸 ენის მიხედვით ტექსტების განსაზღვრა
    const isKa = currentLanguage === 'ka';

    const content = {
        title: isKa ? "ექსკლუზიური ფუნქცია" : "Exclusive Feature",
        desc: isKa
            ? "აუდიო ვერსიის მოსასმენად და თქვენი პროგრესის სინქრონიზაციისთვის საჭიროა ავტორიზაცია."
            : "Please sign in to listen to the audio version and sync your reading progress across devices.",
        btnLabel: isKa ? "შესვლა / რეგისტრაცია" : "Sign In / Register",
        closeLabel: isKa ? "მოგვიანებით" : "Maybe later"
    };

    modal.innerHTML = `
        <div class="audio-alert-card">
            <div class="audio-alert-icon">
                <span class="material-icons-outlined">lock</span>
            </div>
            <h3>${content.title}</h3>
            <p>${content.desc}</p>
            <button class="audio-alert-btn" id="audio-alert-login-trigger">${content.btnLabel}</button>
            <button class="audio-alert-close" id="audio-alert-cancel">${content.closeLabel}</button>
        </div>
    `;

    // აქტივაცია (ანიმაციისთვის)
    requestAnimationFrame(() => modal.classList.add('active'));

    // --- ღილაკების ივენთები ---

    // 1. "შესვლა" ღილაკზე დაჭერა
    document.getElementById('audio-alert-login-trigger').onclick = () => {
        modal.classList.remove('active');
        // ველოდებით მოდალის დახურვის ანიმაციას და მერე ვხსნით Auth ფანჯარას
        setTimeout(() => {
            const authBtn = document.getElementById('user-auth-btn');
            if (authBtn) authBtn.click();
        }, 400);
    };

    // 2. დახურვა (Maybe later)
    document.getElementById('audio-alert-cancel').onclick = () => {
        modal.classList.remove('active');
    };

    // 3. ფონზე დაჭერით დახურვა
    modal.onclick = (e) => {
        if (e.target === modal) modal.classList.remove('active');
    };
}

let flipTimeout = null;
function autoFlipToElement(el) {
    if (flipTimeout) clearTimeout(flipTimeout);
    flipTimeout = setTimeout(() => {
        const idClass = Array.from(el.classList).find(c => c.startsWith('sync-id-'));
        const chClass = Array.from(el.classList).find(c => c.startsWith('sync-ch-'));
        if (!idClass || !chClass) return;

        const allInstances = document.querySelectorAll(`.${chClass}.${idClass}`);
        if (allInstances.length === 0) return;

        const firstInstance = allInstances[0];
        const targetPaper = firstInstance.closest('.paper');
        if (!targetPaper) return;

        const pIndex = parseInt(targetPaper.getAttribute('data-index'));
        const isBack = firstInstance.closest('.back') !== null;
        const side = isBack ? 'back' : 'front';

        const flippedPapers = Array.from(document.querySelectorAll('.paper.flipped'));
        const unflippedPapers = Array.from(document.querySelectorAll('.paper:not(.flipped)'));

        const currentTopFront = unflippedPapers[0];
        const currentTopBack = flippedPapers[flippedPapers.length - 1];

        let needsFlip = false;
        if (isBack) {
            if (targetPaper !== currentTopBack) needsFlip = true;
        } else {
            if (targetPaper !== currentTopFront) needsFlip = true;
        }

        if (needsFlip) {
            const event = new CustomEvent('book-nav', {
                detail: { pageIndex: pIndex, total: document.querySelectorAll('.paper').length, side: side }
            });
            document.dispatchEvent(event);
        }
    }, 100);
}


/* ============================================================
   🌌 AMBIENT BACKGROUND ENGINE (PERFORMANCE OPTIMIZED)
   ============================================================ */
window.lastAmbientUrl = null;
window.ambientTimeout = null; // ვამატებთ ტაიმერს კონტროლისთვის

function updateAmbientBackground() {
    // 🛑 1. ვიცავთ ანიმაციას: ვწყვეტთ წინა ტაიმერს, თუ მომხმარებელი სწრაფად ფურცლავს
    if (window.ambientTimeout) {
        clearTimeout(window.ambientTimeout);
    }

    // 🛑 2. ვაძლევთ 800 მილიწამიან დაყოვნებას (ზუსტად იმდენს, რამდენიც ფურცვლის ანიმაციას სჭირდება)
    window.ambientTimeout = setTimeout(() => {
        let bgEl = document.getElementById('ambient-bg');

        if (!bgEl) {
            bgEl = document.createElement('div');
            bgEl.id = 'ambient-bg';
            const root = document.getElementById('digital-library-root');
            if (root) root.insertBefore(bgEl, root.firstChild);
        }

        let activeItem = document.querySelector('#chapter-list-ui li.active');
        if (!activeItem) return;

        while (activeItem && !activeItem.classList.contains('toc-h1') && !activeItem.classList.contains('toc-cover')) {
            activeItem = activeItem.previousElementSibling;
        }
        if (!activeItem) return;

        let imgSrc = null;

        if (activeItem.classList.contains('toc-cover')) {
            imgSrc = bookMeta.coverImage || null;
        } else {
            const activeTitleText = activeItem.querySelector('span:not(.toc-arrow)').innerText.trim();
            const targetChapter = chaptersData.find(ch => getChapterTitle(ch, currentLanguage) === activeTitleText);

            if (targetChapter) {
                const content = (currentLanguage === 'en') ? (targetChapter.content_en || targetChapter.content) : (targetChapter.content);
                if (content) {
                    const temp = document.createElement('div');
                    temp.innerHTML = content;
                    const img = temp.querySelector('img');
                    if (img) imgSrc = img.getAttribute('src');
                }
            }
        }

        if (imgSrc === window.lastAmbientUrl) return;
        window.lastAmbientUrl = imgSrc;

        if (imgSrc) {
            const imgObj = new Image();
            imgObj.onload = () => {
                bgEl.style.backgroundImage = `url('${imgSrc}')`;
                bgEl.classList.add('active');
            };
            imgObj.src = imgSrc;
        } else {
            bgEl.classList.remove('active');
            setTimeout(() => {
                if (!bgEl.classList.contains('active')) {
                    bgEl.style.backgroundImage = 'none';
                }
            }, 1500);
        }
    }, 800); // 👈 800 მილიწამი აცდის ფურცელს გადაშლას!
}
