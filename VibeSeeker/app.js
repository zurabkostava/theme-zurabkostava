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

    const PLAYLISTS = [
        '37i9dQZF1DXcBWIGoYBM5M', // Today's Top Hits
        '37i9dQZF1DX4WYpdVIP59V', // Chill Hits
        '37i9dQZF1DX0XUsuxWHRQd', // RapCaviar
        '37i9dQZF1DX4dyzvuaRJ0n', // Mint (Electronic)
        '37i9dQZF1DWXRqgorJj26U', // Rock Classics
        '37i9dQZF1DX4SBhb3jqBDD', // Are & Be
        '37i9dQZF1DX1lVhptIYRda', // Hot Country
        '37i9dQZF1DX10zKzsJ2jva', // Viva Latino
        '37i9dQZF1DX4W3aJJY8mrv', // Pop Rising
        '37i9dQZF1DWY7IeIP1cdjF', // Lo-Fi Beats
    ];

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

        const randomPlaylist = PLAYLISTS[Math.floor(Math.random() * PLAYLISTS.length)];
        
        try {
            // Fetch tracks from a random popular playlist (max 50 tracks)
            const res = await fetch(`https://api.spotify.com/v1/playlists/${randomPlaylist}/tracks?limit=50`, {
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
            if (data.items && data.items.length > 0) {
                // Shuffle items to get a random one with a preview
                const items = data.items.sort(() => 0.5 - Math.random());
                const playlistItem = items.find(item => item.track && item.track.preview_url);
                if (playlistItem && playlistItem.track) return playlistItem.track;
            }
            
            // If no track with preview found in this playlist, try another
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
