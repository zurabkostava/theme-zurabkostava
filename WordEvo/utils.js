//utils.js

/**
 * განახლებულია: ეს ფუნქცია ახლა მუშაობს 2 რეჟიმში:
 * 1. (სინქრონულად) მომენტალურად ცვლის UI-ს (პროგრესის ბარს).
 * 2. (ასინქრონულად) იძახებს updateCardProgressInDB-ს, რომ ცვლილება ბაზაში შეინახოს.
 */
function updateCardProgress(card, delta) {
    let current = parseFloat(card.dataset.progress || '0');
    const previousProgress = current;
    current = Math.max(0, Math.min(100, current + delta));
    const newProgress = parseFloat(current.toFixed(1)); // სუფთა, საბოლოო მნიშვნელობა

    card.dataset.progress = newProgress;

    // --- UI-ის განახლება (რჩება იგივე) ---
    if (newProgress >= 100) {
        card.classList.add('mastered');
    } else {
        card.classList.remove('mastered');
    }

    const progressBar = card.querySelector('.progress-bar');
    const label = card.querySelector('.progress-label');

    if (progressBar) {
        progressBar.style.width = `${newProgress}%`;
        progressBar.style.backgroundColor = getProgressColor(newProgress);
    }

    if (label) {
        label.textContent = `${newProgress}%`;
    }
    // --- UI-ის განახლების დასასრული ---


    // --- NEW: ბაზის განახლების გამოძახება ---
    const cardId = card.dataset.id;
    if (cardId && newProgress !== previousProgress) {
        // ჩვენ ვიძახებთ async ფუნქციას, მაგრამ არ "ველოდებით" (no await)
        // ეს UI-ს მომენტალურად ანახლებს და ბაზას ფონურ რეჟიმში წერს.
        updateCardProgressInDB(cardId, newProgress);
    } else if (!cardId) {
        console.warn("Skipping progress save: card.dataset.id is missing.");
    }

    // ძველი saveToStorage() აღარ გვჭირდება.
}

/**
 * NEW: ეს ფუნქცია კონკრეტულად Supabase-ში ანახლებს პროგრესს
 */
const progressWriter = WordevoData.latestWriter(async payload => {
    if (currentUser?.id !== payload.userId) throw new Error('Account changed before progress was saved');
    return supabaseClient.from('cards')
        .update({ progress: payload.progress, updated_at: payload.updatedAt })
        .eq('id', payload.cardId).eq('user_id', payload.userId);
}, error => {
    console.error('[Wordevo] Progress save failed:', error);
    showToast('პროგრესი სერვერზე ვერ შეინახა. შეამოწმეთ კავშირი.', 'error');
});

async function updateCardProgressInDB(cardId, newProgress) {
    // ვამოწმებთ, რომ კლიენტი არსებობს (script.js-დან)
    if (!cardId || typeof supabaseClient === 'undefined') {
        console.error("Cannot update progress: Card ID or Supabase Client is missing.");
        return;
    }

    if (!currentUser || currentUser.id === 'offline-user') return;
    return progressWriter.enqueue(`${currentUser.id}:${cardId}`, {
        userId: currentUser.id, cardId, progress: newProgress, updatedAt: new Date().toISOString()
    });
}


function getProgressColor(percent) {
    if (percent <= 10) return '#eee';
    if (percent <= 25) return '#c8e6c9';
    if (percent <= 50) return '#a5d6a7';
    if (percent <= 75) return '#81c784';
    if (percent < 100) return '#66bb6a';
    return '#55d288';
}


