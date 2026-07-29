document.addEventListener('DOMContentLoaded', () => {
    console.log("VibeSeeker Initialized");

    let accessToken = null;
    let currentTrack = null;
    let audio = new Audio();
    audio.volume = 0.5;

    const els = {
        cardStack: document.getElementById('cardStack'),
        btnPlay: document.getElementById('btnPlay'),
        btnLike: document.getElementById('btnLike'),
        btnDislike: document.getElementById('btnDislike')
    };

    const GENRES = ['pop', 'dance', 'electronic', 'chill', 'house', 'indie', 'rock', 'r&b', 'latin', 'jazz'];
    let activeGenre = 'all';
    let activeYear = 'all';

    async function init() {
        const genreSelect = document.getElementById('genreFilter');
        const yearSelect = document.getElementById('yearFilter');
        
        if (genreSelect) {
            genreSelect.addEventListener('change', (e) => {
                activeGenre = e.target.value;
                loadNextTrack();
            });
        }
        
        if (yearSelect) {
            yearSelect.addEventListener('change', (e) => {
                activeYear = e.target.value;
                loadNextTrack();
            });
        }

        // No token needed for iTunes API!
        await loadNextTrack();
    }

    async function fetchRandomTrack(retryCount = 0) {
        if (retryCount > 5) {
            console.error("Too many retries, stopping search.");
            return null;
        }

        let queryParts = [];
        
        if (activeGenre === 'all') {
            queryParts.push(GENRES[Math.floor(Math.random() * GENRES.length)]);
        } else {
            queryParts.push(activeGenre);
        }

        if (activeYear !== 'all') {
            queryParts.push(activeYear);
        }

        const searchTerm = queryParts.join('+');
        
        try {
            // Fetch tracks from iTunes API (free, no token, 30s previews)
            const res = await fetch(`https://itunes.apple.com/search?term=${encodeURIComponent(searchTerm)}&entity=song&limit=50`);

            if (!res.ok) {
                console.error("iTunes API Error:", res.status);
                return fetchRandomTrack(retryCount + 1);
            }

            const data = await res.json();
            if (data.results && data.results.length > 0) {
                // Shuffle items
                const items = data.results.sort(() => 0.5 - Math.random());
                const track = items.find(item => item.previewUrl);
                if (track) return track;
            }
            
            return fetchRandomTrack(retryCount + 1);

        } catch (e) {
            console.error("Failed to fetch track:", e);
            return null;
        }
    }

    async function loadNextTrack() {
        els.cardStack.innerHTML = '<div style="color: white; text-align: center; margin-top: 50%;">Searching the cosmos...</div>';
        
        const track = await fetchRandomTrack();
        if (!track) return;
        
        currentTrack = track;
        
        // iTunes API response structure
        // Get high-res artwork by replacing 100x100bb with 600x600bb
        const trackArt = track.artworkUrl100 ? track.artworkUrl100.replace('100x100bb', '600x600bb') : '';
        const trackName = track.trackName || 'Unknown Track';
        const artistName = track.artistName || 'Unknown Artist';
        const audioUrl = track.previewUrl;

        // Update UI
        const html = `
            <div class="track-card" id="currentCard">
                <div class="track-art-wrapper">
                    <img src="${trackArt}" alt="Album Art" class="track-art" id="trackImg">
                </div>
                <div class="track-info">
                    <div class="track-title">${trackName}</div>
                    <div class="track-artist">${artistName}</div>
                </div>
            </div>
        `;
        els.cardStack.innerHTML = html;
        
        if (audioUrl) {
            audio.src = audioUrl;
            audio.play().then(() => {
                els.btnPlay.innerHTML = '<i class="fa-solid fa-pause"></i>';
            }).catch(() => {
                // Autoplay blocked
                els.btnPlay.innerHTML = '<i class="fa-solid fa-play"></i>';
            });
        }
    }

    function renderTrack(track) {
        const imgUrl = track.album.images[0] ? track.album.images[0].url : '';
        const artists = track.artists.map(a => a.name).join(', ');

        const html = `
            <div class="track-card" id="currentCard">
                <div class="track-art-wrapper">
                    <img src="${imgUrl}" alt="Album Art" class="track-art" id="trackImg">
                </div>
                <div class="track-info">
                    <div class="track-title">${track.name}</div>
                    <div class="track-artist">${artists}</div>
                </div>
            </div>
        `;
        els.cardStack.innerHTML = html;
    }

    // Play/Pause
    els.btnPlay.addEventListener('click', () => {
        if (audio.paused) {
            audio.play();
            els.btnPlay.innerHTML = '<i class="fa-solid fa-pause"></i>';
        } else {
            audio.pause();
            els.btnPlay.innerHTML = '<i class="fa-solid fa-play"></i>';
        }
    });

    // Skip / Dislike
    els.btnDislike.addEventListener('click', () => {
        animateOut(-1);
    });

    // Like
    els.btnLike.addEventListener('click', () => {
        // Open in Spotify
        if (currentTrack && currentTrack.external_urls && currentTrack.external_urls.spotify) {
            window.open(currentTrack.external_urls.spotify, '_blank');
        }
        animateOut(1);
    });

    function animateOut(direction) {
        const card = document.getElementById('currentCard');
        if (!card) return;
        
        audio.pause();
        els.btnPlay.innerHTML = '<i class="fa-solid fa-play"></i>';
        
        const xMove = direction * window.innerWidth;
        card.style.transform = `translateX(${xMove}px) rotate(${direction * 30}deg)`;
        card.style.opacity = '0';
        
        setTimeout(() => {
            loadNextTrack();
        }, 300);
    }

    init();
});
