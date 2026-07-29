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

    const GENRES = ['chill', 'electronic', 'ambient', 'synth-pop', 'indie', 'pop', 'dance', 'house'];

    async function init() {
        await fetchToken();
        if (accessToken) {
            await loadNextTrack();
        } else {
            console.error("Failed to load Spotify token");
        }
    }

    async function fetchToken() {
        try {
            const res = await fetch('/wp-json/zk/v1/spotify-token');
            const data = await res.json();
            if (data.access_token) {
                accessToken = data.access_token;
            }
        } catch (e) {
            console.error("Token fetch error:", e);
        }
    }

    async function fetchRandomTrack(retryCount = 0) {
        if (retryCount > 5) {
            console.error("Too many retries, stopping search.");
            return null;
        }

        const randomGenre = GENRES[Math.floor(Math.random() * GENRES.length)];
        const randomOffset = Math.floor(Math.random() * 100);
        
        try {
            // Encode the query properly
            const query = encodeURIComponent(`genre:${randomGenre}`);
            const res = await fetch(`https://api.spotify.com/v1/search?q=${query}&type=track&limit=20&offset=${randomOffset}`, {
                headers: {
                    'Authorization': `Bearer ${accessToken}`
                }
            });

            if (res.status === 401) {
                // Token expired, re-fetch
                await fetchToken();
                return fetchRandomTrack(retryCount + 1);
            }

            if (!res.ok) {
                console.error("Spotify API Error:", res.status, await res.text());
                return fetchRandomTrack(retryCount + 1);
            }

            const data = await res.json();
            if (data.tracks && data.tracks.items && data.tracks.items.length > 0) {
                // Shuffle items to get a random one with a preview
                const items = data.tracks.items.sort(() => 0.5 - Math.random());
                const track = items.find(t => t.preview_url);
                if (track) return track;
            }
            
            // If no track with preview found, try again
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
        renderTrack(track);
        
        if (track.preview_url) {
            audio.src = track.preview_url;
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
