// Optional, user-triggered experiment. No server requests or background polling.
(() => {
    const button = document.getElementById('voiceProbe');
    const status = document.getElementById('voiceProbeStatus');
    if (!button || !status) return;
    button.addEventListener('click', () => {
        const synth = window.speechSynthesis;
        if (!synth || !window.SpeechSynthesisUtterance) {
            status.textContent = 'ამ ბრაუზერში გახმოვანება მიუწვდომელია.';
            return;
        }
        if (synth.speaking || synth.pending || synth.paused) {
            status.textContent = 'ჯერ შეაჩერე მიმდინარე გახმოვანება და სცადე თავიდან.';
            return;
        }
        const names = () => synth.getVoices().map(v => v.name);
        const before = new Set(names());
        const microsoft = list => list.filter(name => /microsoft/i.test(name));
        button.disabled = true;
        status.textContent = 'ხმების გააქტიურების ცდა… (8 წამი)';
        let speechError = '';
        // Keep this synchronous with the tap so browser user activation is retained.
        const utterance = new SpeechSynthesisUtterance('Hello');
        utterance.lang = 'en-US';
        utterance.volume = 0;
        utterance.onerror = event => { speechError = event.error || 'unknown'; };
        try { synth.speak(utterance); }
        catch (error) { speechError = error.message; }
        let ticks = 0;
        const timer = setInterval(() => {
            const after = names();
            if (++ticks < 16) return;
            clearInterval(timer);
            button.disabled = false;
            const added = after.filter(name => !before.has(name));
            const ms = microsoft(after);
            const addedMs = microsoft(added);
            status.textContent = `ხმები: ${before.size} → ${after.length}. Microsoft: ${ms.length}. ` +
                (addedMs.length ? `დაემატა: ${addedMs.join(', ')}.` :
                    'ახალი Microsoft ხმა არ დამატებულა. შედარებისთვის ჩართე Read Aloud და სცადე ისევ.') +
                (speechError ? ` გახმოვანების პასუხი: ${speechError}.` : '');
            if (typeof loadVoices === 'function') {
                Promise.resolve(loadVoices()).catch(() => {
                    status.textContent += ' ჩამონათვალის განახლება ვერ მოხერხდა.';
                });
            }
        }, 500);
    });
})();
