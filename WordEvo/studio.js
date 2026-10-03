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
        soundButton.innerHTML = `<i class="fas fa-${sounds ? 'volume-low' : 'volume-mute'}" aria-hidden="true"></i>`;
    }
    // Brief, quiet synthesized tones: no downloads, no autoplay, no TTS changes.
    async function chime() {
        if (!sounds || document.hidden) return;
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
            oscillator.frequency.setValueAtTime(660, now);
            oscillator.frequency.exponentialRampToValueAtTime(880, now + .08);
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
    });
    const container = document.getElementById('cardContainer');
    let frame = 0;
    function updateCollection() {
        frame = 0;
        const cards = [...container.querySelectorAll('.card')];
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
