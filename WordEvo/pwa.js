/* Installation is optional; it never asks for notification permission. */
(() => {
    let registration;
    window.registerWordevoWorker = () => {
        if (!('serviceWorker' in navigator)) return Promise.resolve(null);
        if (!registration) {
            const url = new URL(window.WORDEVO_ASSET_PATH + '/sw.js', location.href);
            url.searchParams.set('app', window.WORDEVO_APP_URL);
            // Stable release key bypasses stale HTTP/CDN content without changing scope.
            url.searchParams.set('v', '19');
            registration = navigator.serviceWorker.register(url.href, { updateViaCache: 'none' })
                .catch(error => { registration = null; throw error; });
        }
        return registration;
    };

    let installPrompt;
    const button = document.getElementById('wordevoInstall');
    const standalone = window.matchMedia('(display-mode: standalone)');
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        installPrompt = event;
        button.hidden = standalone.matches;
    });
    button.addEventListener('click', async () => {
        if (!installPrompt) return;
        const prompt = installPrompt;
        installPrompt = null;
        button.hidden = true;
        try { await prompt.prompt(); await prompt.userChoice; }
        catch (error) { console.warn('[Wordevo] Install prompt unavailable', error); }
    });
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        button.hidden = true;
    });
    window.registerWordevoWorker().catch(error => console.warn('[Wordevo] Worker unavailable', error));
})();
