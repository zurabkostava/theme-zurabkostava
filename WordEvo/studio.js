/* Optional presentation enhancements. Never owns vocabulary or game state. */
(() => {
    'use strict';
    const soundButton = document.getElementById('soundEffectsBtn');
    let sounds = false;
    let context;
    try { sounds = localStorage.getItem('wordevo.interfaceSounds') === 'on'; } catch (_) {}
    function updateSoundButton() {
        if (!soundButton) return;
        soundButton.setAttribute('aria-pressed', String(sounds));
        soundButton.title = sounds ? 'Mute interface sounds' : 'Enable interface sounds';
        soundButton.setAttribute('aria-label', soundButton.title);
        soundButton.innerHTML = `<i class="fas fa-${sounds ? 'volume-low' : 'volume-mute'}" aria-hidden="true"></i><span class="studio-control-label">Sounds ${sounds ? 'on' : 'off'}</span>`;
    }
    // Brief, quiet synthesized tones: no downloads, no autoplay, no TTS changes.
    let lastSound = 0;
    async function chime(kind = 'open') {
        if (!sounds || document.hidden) return;
        if (Date.now() - lastSound < 100) return;
        lastSound = Date.now();
        try {
            const Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) return;
            context ||= new Audio();
            if (context.state === 'suspended') await context.resume();
            if (!sounds || context.state !== 'running') return;
            const now = context.currentTime;
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            const notes = { open: [520, 700], success: [660, 1040], error: [260, 190], select: [760, 860] };
            const [from, to] = notes[kind] || notes.open;
            oscillator.frequency.setValueAtTime(from, now);
            oscillator.frequency.exponentialRampToValueAtTime(to, now + .08);
            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(.025, now + .012);
            gain.gain.exponentialRampToValueAtTime(.001, now + .12);
            oscillator.connect(gain); gain.connect(context.destination);
            oscillator.start(now); oscillator.stop(now + .13);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
        } catch (_) { /* Sound support must never block an action. */ }
    }
    updateSoundButton();
    soundButton?.addEventListener('click', () => {
        sounds = !sounds;
        try { localStorage.setItem('wordevo.interfaceSounds', sounds ? 'on' : 'off'); } catch (_) {}
        updateSoundButton();
        if (sounds) chime();
    });
    document.addEventListener('click', event => {
        const proxy = event.target.closest('[data-studio-action]');
        if (proxy) document.getElementById(proxy.dataset.studioAction)?.click();
        // Navigation only: avoid layering sounds over listening and speaking exercises.
        if (event.target.closest('#addCardBtn, #libraryManagerBtn, #settingsBtn, #statsBtn, #trainingBtn')) chime();
        if (event.target.closest('.studio-tabs button, .sidebar-tag-item > span, #viewToggleBtn')) chime('select');
    });
    const toasts = document.getElementById('toastContainer');
    if (toasts) new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === 1 && node.matches('.toast.success, .toast.error')) chime(node.classList.contains('success') ? 'success' : 'error');
    }))).observe(toasts, { childList: true });

    // Reuse the original controls and their listeners; do not duplicate application state.
    const byId = id => document.getElementById(id);
    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text) node.textContent = text;
        return node;
    }
    const header = document.querySelector('.header-wrapper');
    const top = document.querySelector('.top-bar');
    if (header && top) {
        header.classList.add('studio-command');
        const brand = top.querySelector('.app-logo');
        const library = top.querySelector('.library-selector-wrapper');
        const search = top.querySelector('.search-input-wrapper');
        search.style.removeProperty('width');
        const account = element('details', 'studio-account');
        const summary = element('summary', '', 'More');
        summary.setAttribute('aria-label', 'Account and tools');
        account.append(summary);
        const menu = element('div', 'studio-account-menu');
        account.append(menu);
        [byId('userEmailDisplay'), byId('settingsBtn'), soundButton, byId('statsBtn'), byId('notificationsBtn'), top.querySelector('a'), byId('logoutBtn')].forEach(node => {
            if (!node) return;
            if (node.tagName === 'BUTTON' && !node.querySelector('.studio-control-label')) {
                node.append(element('span', 'studio-control-label', node.title || node.getAttribute('aria-label')));
            }
            menu.append(node);
        });
        top.replaceChildren(brand, library, search, byId('trainingBtn'), byId('addCardBtn'), account);
        byId('searchInput').setAttribute('aria-label', 'Search your vocabulary');
        byId('addCardBtn').setAttribute('aria-label', 'Add word');
        const filters = document.querySelector('.toolbar-right');
        byId('toggleSidebarBtn').append(element('span', '', 'Tags'));
        byId('sortSelect').setAttribute('aria-label', 'Sort vocabulary');
        byId('mainProgressSelect').setAttribute('aria-label', 'Filter by progress');
        byId('mainProgressSelect').options[1].textContent = 'Still learning';
        filters.querySelector('.toolbar-dropdown')?.remove();
        document.addEventListener('click', event => {
            if (!account.contains(event.target) || event.target.closest('.studio-account-menu button')) account.open = false;
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') account.open = false; });
    }

    function tabbedModal(overlayId, definitions) {
        const overlay = byId(overlayId);
        const body = overlay?.querySelector('.modal-body');
        if (!body) return;
        overlay.classList.add('studio-compact');
        const tabs = element('div', 'studio-tabs');
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', overlayId === 'settingsModal' ? 'Settings sections' : 'Word details');
        body.before(tabs);
        let activeIndex = 0;
        let nextButton;
        const panels = definitions.map(([name, ids], index) => {
            const panel = element('section', 'studio-panel');
            panel.id = `${overlayId}-panel-${index}`;
            panel.setAttribute('role', 'tabpanel');
            const button = element('button', '', name);
            button.type = 'button';
            button.id = `${overlayId}-tab-${index}`;
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-controls', panel.id);
            panel.setAttribute('aria-labelledby', button.id);
            tabs.append(button);
            ids.forEach(id => {
                let node = byId(id);
                if (!node) return;
                while (node.parentElement !== body && node.parentElement) node = node.parentElement;
                if (node.parentElement === body) panel.append(node);
            });
            body.append(panel);
            button.addEventListener('click', () => activate(index));
            button.addEventListener('keydown', event => {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % definitions.length;
                if (event.key === 'ArrowLeft') next = (index + definitions.length - 1) % definitions.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = definitions.length - 1;
                if (next !== undefined) { event.preventDefault(); activate(next); tabs.children[next].focus(); }
            });
            return panel;
        });
        function activate(index) {
            activeIndex = index;
            panels.forEach((panel, i) => {
                panel.hidden = i !== index;
                tabs.children[i].setAttribute('aria-selected', String(i === index));
                tabs.children[i].tabIndex = i === index ? 0 : -1;
            });
            body.scrollTop = 0;
            if (nextButton) {
                nextButton.hidden = index === definitions.length - 1;
                nextButton.textContent = index === 0 ? 'Next: Memory →' : 'Next: Examples →';
            }
        }
        if (overlayId === 'modalOverlay') {
            nextButton = element('button', 'studio-next-step');
            nextButton.type = 'button';
            nextButton.onclick = () => activate(Math.min(activeIndex + 1, definitions.length - 1));
            overlay.querySelector('.modal-footer').prepend(nextButton);
            byId('saveCardBtn').addEventListener('click', event => {
                if (!byId('wordInput').value.trim()) {
                    event.stopImmediatePropagation(); activate(0); byId('wordInput').focus(); return;
                }
                // Commit typed translations just as the adjacent + controls do.
                if (byId('mainTranslationInput').value.trim()) byId('addMainTranslationBtn').click();
                if (byId('extraTranslationInput').value.trim()) byId('addExtraTranslationBtn').click();
            }, true);
        }
        activate(0);
        new MutationObserver(() => {
            if (overlay.style.display !== 'none') activate(0);
        }).observe(overlay, { attributes: true, attributeFilter: ['style'] });
    }
    tabbedModal('settingsModal', [
        ['Voices', ['voiceSelect', 'englishRateSlider', 'georgianVoiceSelect', 'georgianRateSlider', 'voiceProbe']],
        ['Playback', ['skipExtraTranslationCheckbox', 'shuffleExamplesCheckbox', 'skipMnemonicCheckbox', 'readExamplesSelect']],
        ['Data & progress', ['exportExcelBtn']]
    ]);
    tabbedModal('modalOverlay', [
        ['01 · Meaning', ['wordInput', 'mainTranslationInput', 'mainTranslationTags', 'extraTranslationInput', 'extraTranslationTags']],
        ['02 · Memory', ['tagInput', 'tagList', 'mnemonicInput']],
        ['03 · Examples', ['englishSentences', 'georgianSentences', 'jsonImportInput']]
    ]);
    const progressBody = byId('progressSettingsModal')?.querySelector('.modal-body');
    if (progressBody) {
        [...progressBody.querySelectorAll(':scope > h3')].forEach((heading, index) => {
            const section = element('details', 'studio-progress-section');
            section.open = index === 0;
            section.append(element('summary', '', heading.textContent));
            heading.before(section);
            let next = heading.nextElementSibling;
            while (next && next.tagName !== 'H3') {
                const current = next; next = next.nextElementSibling; section.append(current);
            }
            heading.remove();
        });
    }
    const editor = byId('modalOverlay');
    if (editor) {
        const progress = element('div', 'studio-composer-status');
        editor.querySelector('.studio-tabs').after(progress);
        const update = () => {
            const word = byId('wordInput').value.trim();
            const hasMeaning = byId('mainTranslationTags').children.length > 0;
            const hasMemory = byId('mnemonicInput').value.trim().length > 0;
            const hasExamples = byId('englishSentences').value.trim().length > 0;
            const completed = [!!word, hasMeaning, hasMemory, hasExamples].filter(Boolean).length;
            progress.textContent = `${word || 'Your new word'} · ${completed}/4 details added`;
            progress.style.setProperty('--completion', `${completed * 25}%`);
        };
        editor.addEventListener('input', update);
        new MutationObserver(update).observe(byId('mainTranslationTags'), { childList: true });
        new MutationObserver(update).observe(editor, { attributes: true, attributeFilter: ['style'] });
        update();
    }
    const sidebar = byId('sidebar');
    const tagList = byId('sidebarTagList');
    if (sidebar && tagList) {
        const tools = element('div', 'studio-tag-tools');
        const search = element('input', 'studio-tag-search');
        search.type = 'search'; search.placeholder = 'Find a tag…'; search.setAttribute('aria-label', 'Find a tag');
        const manage = element('button', '', 'Manage tags');
        manage.type = 'button'; manage.setAttribute('aria-pressed', 'false');
        manage.onclick = () => {
            const active = sidebar.classList.toggle('studio-manage-tags');
            manage.setAttribute('aria-pressed', String(active));
            manage.textContent = active ? 'Done managing' : 'Manage tags';
        };
        const status = element('p', 'studio-tag-status');
        status.setAttribute('role', 'status');
        tools.append(search, manage, status); tagList.before(tools);
        function filterTags() {
            const rows = [...tagList.querySelectorAll('.sidebar-tag-item')];
            let shown = 0;
            rows.forEach(row => {
                const name = row.querySelector('span')?.textContent || '';
                row.hidden = !name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase());
                if (!row.hidden) shown++;
                row.style.setProperty('--tag-color', getColorForTag(name));
                const label = row.querySelector('span');
                if (label && !label.hasAttribute('role')) {
                    label.setAttribute('role', 'button'); label.tabIndex = 0;
                    label.setAttribute('aria-pressed', String(row.classList.contains('active')));
                    label.onkeydown = event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); label.click(); } };
                }
            });
            status.textContent = `${rows.filter(row => row.classList.contains('active')).length} selected · ${shown} tags`;
        }
        search.addEventListener('input', filterTags);
        new MutationObserver(filterTags).observe(tagList, { childList: true });
        filterTags();
    }
    const container = document.getElementById('cardContainer');
    // Keep the listening dock from covering dialog actions.
    const dialogs = [...document.querySelectorAll('.modal-overlay, .training-modal')];
    const syncDialogDock = () => document.body.classList.toggle('studio-dialog-open', dialogs.some(dialog =>
        !dialog.classList.contains('hidden') && getComputedStyle(dialog).display !== 'none'));
    dialogs.forEach(dialog => new MutationObserver(syncDialogDock).observe(dialog, {
        attributes: true, attributeFilter: ['style', 'class']
    }));
    syncDialogDock();
    let frame = 0;
    function updateCollection() {
        frame = 0;
        const cards = [...container.querySelectorAll('.card')];
        cards.forEach(card => {
            const value = Math.max(0, Math.min(100, Number(card.dataset.progress) || 0));
            const stage = value >= 100 ? 'Mastered ✦' : value >= 75 ? 'Almost yours' : value >= 40 ? 'Growing' : value > 0 ? 'Taking root' : 'Fresh discovery';
            let caption = card.querySelector('.studio-progress-caption');
            if (!caption) { caption = element('span', 'studio-progress-caption'); card.append(caption); }
            if (caption.textContent !== stage) caption.textContent = stage;
            card.dataset.mastery = value >= 100 ? 'complete' : 'learning';
        });
        const visible = cards.filter(card => !card.hidden && card.style.display !== 'none');
        document.getElementById('studioWordCount').textContent = cards.length.toLocaleString();
        document.getElementById('studioMasteredCount').textContent = cards.filter(card => Number(card.dataset.progress) >= 100).length.toLocaleString();
        document.getElementById('studioVisibleCount').textContent = `${visible.length.toLocaleString()} visible`;
        document.getElementById('studioEmpty').hidden = cards.length !== 0;
    }
    if (container) {
        new MutationObserver(() => { if (!frame) frame = requestAnimationFrame(updateCollection); })
            .observe(container, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'data-progress', 'hidden'] });
        updateCollection();
    }
    // Give existing icon controls accessible names without changing their handlers.
    document.querySelectorAll('button[title]').forEach(button => {
        if (!button.hasAttribute('aria-label')) button.setAttribute('aria-label', button.title);
    });
})();
