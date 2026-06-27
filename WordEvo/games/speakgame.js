let speakCards = [];
let speakCurrent = 0;
let speakTotalScore = 0;
let recognition = null;
let isRecording = false;
let currentTargetText = '';
let speakNextReady = false;
let speakTimeout = null;

// Helper: Levenshtein distance
function levenshteinDistance(a, b) {
    const matrix = [];
    for (let i = 0; i <= b.length; i++) {
        matrix[i] = [i];
    }
    for (let j = 0; j <= a.length; j++) {
        matrix[0][j] = j;
    }
    for (let i = 1; i <= b.length; i++) {
        for (let j = 1; j <= a.length; j++) {
            if (b.charAt(i - 1) === a.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1,
                    Math.min(matrix[i][j - 1] + 1, matrix[i - 1][j] + 1)
                );
            }
        }
    }
    return matrix[b.length][a.length];
}

function getSimilarity(s1, s2) {
    s1 = s1.toLowerCase().replace(/[^\w\s\']/g, '').trim();
    s2 = s2.toLowerCase().replace(/[^\w\s\']/g, '').trim();
    if (!s1 && !s2) return 1.0;
    if (!s1 || !s2) return 0.0;
    const distance = levenshteinDistance(s1, s2);
    const maxLen = Math.max(s1.length, s2.length);
    return 1 - (distance / maxLen);
}

function getValidSpeakCards() {
    const globalCount = parseInt(document.getElementById('globalQuestionCount')?.value || '10', 10);
    const all = getVisibleCards();
    
    // We want cards that have English text (either a word or a sentence)
    // Actually all cards have a word, so all are valid for SPEAK if language 1 is English.
    // For simplicity, we just use the word or the first English sentence.
    
    // Randomize
    const shuffled = all.sort(() => Math.random() - 0.5);
    return shuffled.slice(0, globalCount);
}

window.startSpeakGame = function() {
    speakCards = getValidSpeakCards();
    if (speakCards.length === 0) {
        document.getElementById('speakTab').innerHTML = '<p>No valid cards found for SPEAK.</p>';
        return;
    }
    
    speakCurrent = 0;
    speakTotalScore = 0;
    isRecording = false;
    speakNextReady = false;
    
    // Initialize Web Speech API
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'en-US'; // We assume English for pronunciation
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        
        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            handleSpeakResult(transcript);
        };
        
        recognition.onerror = (event) => {
            console.error("Speech recognition error", event.error);
            isRecording = false;
            updateMicUI(false);
            showSpeakFeedback(`Error: ${event.error}. Please try again.`, 'error');
        };
        
        recognition.onend = () => {
            isRecording = false;
            updateMicUI(false);
        };
    }
    
    showSpeakCard();
};

function showSpeakCard() {
    const container = document.getElementById('speakTab');
    if (speakCurrent >= speakCards.length) {
        showSpeakResult();
        return;
    }
    
    const card = speakCards[speakCurrent];
    const enArr = JSON.parse(card.dataset.english || '[]');
    const word = card.querySelector('.word').textContent.trim();
    
    // Pick the first English sentence if available, otherwise just the word
    currentTargetText = (enArr.length > 0 && enArr[0].trim() !== '') ? enArr[0].replace(/^\d+\.\s*/, '') : word;
    
    let micHtml = '';
    if (!recognition) {
        micHtml = `<div style="color: #ff4757; margin-top: 20px;">Speech Recognition is not supported in your browser. Try Google Chrome.</div>`;
    } else {
        micHtml = `
            <button id="speakMicBtn" class="speak-mic-btn" onclick="toggleSpeakRecording()">
                <i class="fas fa-microphone"></i>
            </button>
            <div id="speakStatus" style="margin-top: 10px; font-size: 14px; color: #a6adc8;">Tap to speak</div>
        `;
    }

    container.innerHTML = `
        <div class="game-question-animated">
            <h3>Card ${speakCurrent + 1} / ${speakCards.length}</h3>
            <div style="margin: 30px 0; font-size: 24px; line-height: 1.6; color: var(--accent); font-weight: bold; text-align: center;">
                "${currentTargetText}"
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                ${micHtml}
            </div>
            
            <div id="speakFeedback" style="margin-top: 20px; font-size: 20px; font-weight: bold; text-align: center;"></div>
            <div id="speakTranscript" style="margin-top: 10px; font-size: 16px; font-style: italic; color: #888; text-align: center;"></div>
            
            <div id="speakEnterHint" class="enter-hint-btn">Press ↵ Enter to continue</div>
        </div>
    `;
}

window.toggleSpeakRecording = function() {
    if (!recognition) return;
    if (isRecording) {
        recognition.stop();
    } else {
        try {
            recognition.start();
            isRecording = true;
            updateMicUI(true);
            document.getElementById('speakStatus').textContent = "Listening...";
            document.getElementById('speakFeedback').innerHTML = '';
            document.getElementById('speakTranscript').innerHTML = '';
        } catch (e) {
            console.error(e);
        }
    }
};

function updateMicUI(recording) {
    const btn = document.getElementById('speakMicBtn');
    if (!btn) return;
    if (recording) {
        btn.classList.add('recording');
        btn.innerHTML = '<i class="fas fa-stop"></i>';
    } else {
        btn.classList.remove('recording');
        btn.innerHTML = '<i class="fas fa-microphone"></i>';
        document.getElementById('speakStatus').textContent = "Tap to speak";
    }
}

function handleSpeakResult(transcript) {
    document.getElementById('speakTranscript').innerHTML = `You said: "${transcript}"`;
    
    const similarity = getSimilarity(currentTargetText, transcript);
    const card = speakCards[speakCurrent];
    let fbText = '';
    let fbColor = '';
    
    if (similarity >= 0.90) {
        fbText = 'Perfect! 🎉 +0.5%';
        fbColor = '#2ecc71'; // Green
        speakTotalScore += 0.5;
        if (card) updateCardProgress(card, 0.5);
    } else if (similarity >= 0.70) {
        fbText = 'Good! 👍 +0.2%';
        fbColor = '#f1c40f'; // Yellow
        speakTotalScore += 0.2;
        if (card) updateCardProgress(card, 0.2);
    } else {
        fbText = 'Try again! ❌ +0%';
        fbColor = '#e74c3c'; // Red
    }
    
    showSpeakFeedback(fbText, fbColor);
    
    document.getElementById('speakEnterHint').classList.add('visible');
    speakNextReady = true;
    speakTimeout = setTimeout(() => {
        if (speakNextReady) {
            speakNextReady = false;
            speakCurrent++;
            showSpeakCard();
        }
    }, 3000);
}

function showSpeakFeedback(text, color) {
    const fb = document.getElementById('speakFeedback');
    if (fb) {
        fb.innerHTML = text;
        fb.style.color = color;
    }
}

function showSpeakResult() {
    const container = document.getElementById('speakTab');
    const maxPossible = speakCards.length * 2;
    const percentage = maxPossible > 0 ? Math.round((speakTotalScore / maxPossible) * 100) : 0;
    
    container.innerHTML = `
        <div class="beautiful-results">
            <h3>Speaking Session Complete! 🎙️</h3>
            <div class="score-circle">${percentage}%</div>
            <p>Total Progress Earned: <strong>+${speakTotalScore}%</strong></p>
            <button class="play-again-btn" onclick="startSpeakGame()">Practice Again 🔄</button>
        </div>
    `;
    speakNextReady = false;
}

// Global Keyboard shortcuts for Speak
document.addEventListener('keydown', (e) => {
    if (document.getElementById('trainingModal')?.classList.contains('hidden')) return;
    const activeTab = document.querySelector('.training-tab.active')?.dataset.tab;
    if (activeTab !== 'tab8') return;

    if (e.key === 'Enter') {
        if (speakNextReady) {
            clearTimeout(speakTimeout);
            speakNextReady = false;
            speakCurrent++;
            showSpeakCard();
        } else if (document.getElementById('speakMicBtn')) {
            toggleSpeakRecording();
        }
    }
});
