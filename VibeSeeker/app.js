document.addEventListener('DOMContentLoaded', () => {
    console.log("VibeSeeker Initialized");

    let accessToken = null;
    let currentTrack = null;

    const els = {
        cardStack: document.getElementById('cardStack'),
        btnLike: document.getElementById('btnLike'),
        btnDislike: document.getElementById('btnDislike')
    };

    const GENRES = [
        "acoustic", "afrobeat", "alt-rock", "alternative", "ambient", "anime", "black-metal", "bluegrass", "blues", 
        "bossanova", "brazil", "breakbeat", "british", "cantopop", "chicago-house", "children", "chill", "classical", 
        "club", "comedy", "country", "dance", "dancehall", "death-metal", "deep-house", "detroit-techno", "disco", 
        "disney", "drum-and-bass", "dub", "dubstep", "edm", "electro", "electronic", "emo", "folk", "forro", "french", 
        "funk", "garage", "german", "gospel", "goth", "grindcore", "groove", "grunge", "guitar", "happy", "hard-rock", 
        "hardcore", "hardstyle", "heavy-metal", "hip-hop", "holidays", "honky-tonk", "house", "idm", "indian", "indie", 
        "indie-pop", "industrial", "iranian", "j-dance", "j-idol", "j-pop", "j-rock", "jazz", "k-pop", "kids", "latin", 
        "latino", "malay", "mandopop", "metal", "metal-misc", "metalcore", "minimal-techno", "movies", "mpb", "new-age", 
        "new-release", "opera", "pagode", "party", "philippines-opm", "piano", "pop", "pop-film", "post-dubstep", 
        "power-pop", "progressive-house", "psych-rock", "punk", "punk-rock", "r-n-b", "rainy-day", "reggae", "reggaeton", 
        "road-trip", "rock", "rock-n-roll", "rockabilly", "romance", "sad", "salsa", "samba", "sertanejo", "show-tunes", 
        "singer-songwriter", "ska", "sleep", "songwriter", "soul", "soundtracks", "spanish", "study", "summer", 
        "swedish", "synth-pop", "tango", "techno", "trance", "trip-hop", "turkish", "work-out", "world-music"
    ];
    let activeGenre = 'all';
    let activeYear = 'all';

    async function init() {
        const genreSelect = document.getElementById('genreFilter');
        const yearSelect = document.getElementById('yearFilter');
        
        if (genreSelect) {
            genreSelect.innerHTML = '<option value="all">All Genres</option>';
            GENRES.forEach(genre => {
                const option = document.createElement('option');
                option.value = genre;
                option.textContent = genre.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                genreSelect.appendChild(option);
            });
            genreSelect.addEventListener('change', (e) => {
                activeGenre = e.target.value;
                loadNextTrack();
            });
        }
        
        if (yearSelect) {
            yearSelect.innerHTML = '<option value="all">All Time</option>';
            const currentYear = new Date().getFullYear();
            for (let y = currentYear; y >= 1950; y--) {
                const option = document.createElement('option');
                option.value = y.toString();
                option.textContent = y.toString();
                yearSelect.appendChild(option);
            }
            yearSelect.addEventListener('change', (e) => {
                activeYear = e.target.value;
                loadNextTrack();
            });
        }

        await fetchToken();
        if (accessToken) {
            await loadNextTrack();
        } else {
            console.error("Failed to load Spotify token");
            els.cardStack.innerHTML = `<div style="color:white; text-align:center;">Failed to authenticate with Spotify.</div>`;
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

        let query = '';
        if (activeGenre === 'all') {
            query += `genre:${GENRES[Math.floor(Math.random() * GENRES.length)]}`;
        } else {
            query += `genre:${activeGenre}`;
        }

        if (activeYear !== 'all') {
            query += ` year:${activeYear}`;
        }

        const randomOffset = Math.floor(Math.random() * 100);
        
        try {
            const res = await fetch(`https://api.spotify.com/v1/search?q=${encodeURIComponent(query)}&type=track&limit=20&offset=${randomOffset}`, {
                headers: {
                    'Authorization': `Bearer ${accessToken}`
                }
            });

            if (res.status === 401) {
                await fetchToken();
                return fetchRandomTrack(retryCount + 1);
            }

            if (!res.ok) {
                console.error("Spotify API Error:", res.status);
                return fetchRandomTrack(retryCount + 1);
            }

            const data = await res.json();
            if (data.tracks && data.tracks.items && data.tracks.items.length > 0) {
                const items = data.tracks.items.sort(() => 0.5 - Math.random());
                const track = items[0];
                if (track) return track;
            }
            
            return fetchRandomTrack(retryCount + 1);

        } catch (e) {
            console.error("Fetch failed:", e);
            return null;
        }
    }

    function showLoader() {
        els.cardStack.innerHTML = `
            <div class="loader">
                <i class="fa-solid fa-compact-disc fa-spin"></i>
            </div>
        `;
    }

    async function loadNextTrack() {
        showLoader();

        const track = await fetchRandomTrack();
        if (!track) {
            els.cardStack.innerHTML = `<div style="color:white; text-align:center;">No tracks found for this criteria.</div>`;
            return;
        }
        
        currentTrack = track;
        
        // Spotify API response structure
        const trackArt = track.album && track.album.images && track.album.images[0] ? track.album.images[0].url : '';
        const trackId = track.id;

        // Update UI using Spotify Embed Widget
        const html = `
            <div class="track-card" id="currentCard" style="display:flex; flex-direction:column; padding:0; background:transparent;">
                <div class="track-art-wrapper" style="height: 60%;">
                    <img src="${trackArt}" alt="Album Art" class="track-art" id="trackImg" style="border-radius: 20px 20px 0 0;">
                </div>
                <div class="track-info" style="padding: 10px; display: flex; flex-direction: column; justify-content: center; height: 40%; background: #121212; border-radius: 0 0 20px 20px;">
                    <iframe src="https://open.spotify.com/embed/track/${trackId}?utm_source=generator" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                </div>
            </div>
        `;
        els.cardStack.innerHTML = html;

        setupCardInteraction();
    }

    function setupCardInteraction() {
        const card = document.getElementById('currentCard');
        if (!card) return;

        let startX = 0;
        let isDragging = false;

        card.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
            card.style.transition = 'none';
        });

        card.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            const currentX = e.touches[0].clientX;
            const diffX = currentX - startX;
            const rotate = diffX * 0.05;
            
            card.style.transform = `translateX(${diffX}px) rotate(${rotate}deg)`;
        });

        card.addEventListener('touchend', (e) => {
            if (!isDragging) return;
            isDragging = false;
            
            const endX = e.changedTouches[0].clientX;
            const diffX = endX - startX;
            
            if (Math.abs(diffX) > 100) {
                // Swipe resolved
                const direction = diffX > 0 ? 1 : -1;
                card.style.transition = 'transform 0.3s ease';
                card.style.transform = `translateX(${direction * window.innerWidth}px) rotate(${direction * 20}deg)`;
                
                setTimeout(() => {
                    if (direction > 0) {
                        saveLikedTrack(currentTrack);
                    }
                    loadNextTrack();
                }, 300);
            } else {
                // Return to center
                card.style.transition = 'transform 0.3s ease';
                card.style.transform = 'translateX(0) rotate(0)';
            }
        });
    }

    function saveLikedTrack(track) {
        if (!track) return;
        let saved = JSON.parse(localStorage.getItem('zk_liked_tracks') || '[]');
        if (!saved.some(t => t.id === track.id)) {
            saved.push({
                id: track.id,
                name: track.name,
                artist: track.artists[0].name,
                url: track.external_urls.spotify
            });
            localStorage.setItem('zk_liked_tracks', JSON.stringify(saved));
        }
    }

    els.btnLike.addEventListener('click', () => {
        saveLikedTrack(currentTrack);
        const card = document.getElementById('currentCard');
        if (card) {
            card.style.transition = 'transform 0.3s ease';
            card.style.transform = `translateX(${window.innerWidth}px) rotate(20deg)`;
            setTimeout(loadNextTrack, 300);
        }
    });

    els.btnDislike.addEventListener('click', () => {
        const card = document.getElementById('currentCard');
        if (card) {
            card.style.transition = 'transform 0.3s ease';
            card.style.transform = `translateX(-${window.innerWidth}px) rotate(-20deg)`;
            setTimeout(loadNextTrack, 300);
        }
    });

    init();
});
