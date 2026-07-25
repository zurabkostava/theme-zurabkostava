/**
 * Three.js Galaxy Background Animation for Zurab Kostava Theme
 * Ultra-realistic, extremely dense, slow-moving 3D starfield.
 */

(function() {
    let container;
    let canvas, ctx;
    let scene, camera, renderer;
    let starSystem, starSystem2, starsMaterial;
    let heroSystem, heroSystem2, heroMaterial;
    let galaxyMesh, galaxyGlowMesh, galaxyCoreMesh;
    let clusterSystem; 
    let animationFrameId;
    let isEntering = false; // For cinematic entrance
    let isRunning = false;
    let timeMultiplier = 1; // Global speed multiplier from slider

    function initGalaxy() {
        container = document.getElementById('zk-galaxy-canvas');
        if (!container) return;
        
        // Wait for Three.js to be loaded
        if (typeof THREE === 'undefined') {
            setTimeout(initGalaxy, 100);
            return;
        }

        const rect = container.parentElement.getBoundingClientRect();
        
        scene = new THREE.Scene();
        // Lower fog density so more stars are visible in the distance before fading to black
        scene.fog = new THREE.FogExp2(0x000000, 0.0002);

        // Camera - huge far plane so large tilted objects don't clip at extreme distances
        camera = new THREE.PerspectiveCamera(60, rect.width / rect.height, 1, 35000);
        
        // Start far away and rotated for cinematic barrel roll entrance
        camera.position.z = 25000;
        camera.rotation.z = Math.PI * 0.5;

        renderer = new THREE.WebGLRenderer({ canvas: container, alpha: true, antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // optimize performance
        renderer.setSize(rect.width, rect.height);
        
        // Hide canvas initially to prevent pop-in
        container.style.opacity = 0;
        container.style.transition = 'opacity 3s ease-out';
        renderer.setClearColor(0x000000, 0); 

        // Generate stars
        const starCount = 400000; // 400k per block (800k total). Zero CPU overhead now!
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(starCount * 3);
        const colors = new Float32Array(starCount * 3);
        const sizes = new Float32Array(starCount);
        
        const colorPalette = [
            new THREE.Color(0xffffff), // White
            new THREE.Color(0xffffff), // White (bias)
            new THREE.Color(0xfff4e8), // Pale yellow
            new THREE.Color(0xffd2a1), // Orange/Red dwarf
            new THREE.Color(0xa1c4ff), // Blue giant
            new THREE.Color(0xc2d6ff)  // Pale blue
        ];

        // Create cluster centers for nebula-like structures and voids
        const numClusters = 250; 
        const clusters = [];
        for (let c = 0; c < numClusters; c++) {
            clusters.push({
                x: THREE.MathUtils.randFloatSpread(4500),
                y: THREE.MathUtils.randFloatSpread(3000),
                z: THREE.MathUtils.randFloatSpread(15000),
                radiusX: Math.random() * 600 + 200, 
                radiusY: Math.random() * 600 + 200, 
                radiusZ: Math.random() * 2000 + 500 
            });
        }

        for (let i = 0; i < starCount; i++) {
            let x, y, z;
            
            if (Math.random() < 0.75) {
                const cluster = clusters[Math.floor(Math.random() * clusters.length)];
                
                const u = Math.random() * 2 - 1;
                const v = Math.random() * 2 - 1;
                const w = Math.random() * 2 - 1;
                
                x = cluster.x + Math.sign(u) * Math.pow(Math.abs(u), 2) * cluster.radiusX;
                y = cluster.y + Math.sign(v) * Math.pow(Math.abs(v), 2) * cluster.radiusY;
                z = cluster.z + Math.sign(w) * Math.pow(Math.abs(w), 2) * cluster.radiusZ;
            } else {
                x = THREE.MathUtils.randFloatSpread(4500);
                y = THREE.MathUtils.randFloatSpread(3000);
                z = THREE.MathUtils.randFloatSpread(15000);
            }

            positions[i * 3] = x;
            positions[i * 3 + 1] = y;
            positions[i * 3 + 2] = z;

            const color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            colors[i * 3] = color.r;
            colors[i * 3 + 1] = color.g;
            colors[i * 3 + 2] = color.b;
            
            sizes[i] = Math.random() * 1.5 + 1.2;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

        const canvasSprite = document.createElement('canvas');
        canvasSprite.width = 8;
        canvasSprite.height = 8;
        const context = canvasSprite.getContext('2d');
        const gradient = context.createRadialGradient(4, 4, 0, 4, 4, 4);
        gradient.addColorStop(0, 'rgba(255,255,255,1)');
        gradient.addColorStop(0.3, 'rgba(255,255,255,0.8)');
        gradient.addColorStop(1, 'rgba(0,0,0,0)');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 8, 8);

        const texture = new THREE.CanvasTexture(canvasSprite);

        starsMaterial = new THREE.PointsMaterial({
            size: 2.5, 
            map: texture,
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        // 🔴 OPTIMIZATION: Dual System Infinite Loop
        // We use two identical geometries placed back-to-back. 
        // This eliminates the need to update 800k array positions on the CPU 
        // and upload 10MB to the GPU every single frame!
        starSystem = new THREE.Points(geometry, starsMaterial);
        starSystem.position.z = -6500;
        
        starSystem2 = new THREE.Points(geometry, starsMaterial);
        starSystem2.position.z = -21500; // Right behind the first one (-6500 - 15000)
        
        scene.add(starSystem);
        scene.add(starSystem2);

        // 🌟 NEW: Hero Stars (Lens Flare Stars)
        const heroStarCount = 5000; // ~1.25% of the total stars
        const heroGeometry = new THREE.BufferGeometry();
        const heroPositions = new Float32Array(heroStarCount * 3);
        const heroColors = new Float32Array(heroStarCount * 3);
        const heroSizes = new Float32Array(heroStarCount);

        for (let i = 0; i < heroStarCount; i++) {
            // Uniform spread for hero stars so they don't clump too much
            heroPositions[i * 3] = THREE.MathUtils.randFloatSpread(4500);
            heroPositions[i * 3 + 1] = THREE.MathUtils.randFloatSpread(3000);
            heroPositions[i * 3 + 2] = THREE.MathUtils.randFloatSpread(15000);

            const color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            // Boost color brightness for hero stars by lerping towards white
            heroColors[i * 3] = color.r * 0.5 + 0.5;
            heroColors[i * 3 + 1] = color.g * 0.5 + 0.5;
            heroColors[i * 3 + 2] = color.b * 0.5 + 0.5;
            
            // Sizes vary dramatically
            heroSizes[i] = Math.random() * 15 + 5;
        }

        heroGeometry.setAttribute('position', new THREE.BufferAttribute(heroPositions, 3));
        heroGeometry.setAttribute('color', new THREE.BufferAttribute(heroColors, 3));
        heroGeometry.setAttribute('size', new THREE.BufferAttribute(heroSizes, 1));

        // Flare Texture
        const flareCanvas = document.createElement('canvas');
        flareCanvas.width = 128;
        flareCanvas.height = 128;
        const flareCtx = flareCanvas.getContext('2d');
        const cx = 64, cy = 64;
        
        const radialGlow = flareCtx.createRadialGradient(cx, cy, 0, cx, cy, 64);
        radialGlow.addColorStop(0, 'rgba(255, 255, 255, 1)');
        radialGlow.addColorStop(0.05, 'rgba(255, 255, 255, 0.8)');
        radialGlow.addColorStop(0.2, 'rgba(255, 255, 255, 0.2)');
        radialGlow.addColorStop(1, 'rgba(0, 0, 0, 0)');
        flareCtx.fillStyle = radialGlow;
        flareCtx.fillRect(0, 0, 128, 128);
        
        // Draw cross spikes
        const drawSpike = (rx, ry) => {
            const grad = flareCtx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(rx, ry));
            grad.addColorStop(0, 'rgba(255, 255, 255, 1)');
            grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
            flareCtx.fillStyle = grad;
            flareCtx.beginPath();
            flareCtx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
            flareCtx.fill();
        };
        drawSpike(64, 2); // Horizontal spike
        drawSpike(2, 64); // Vertical spike

        const flareTexture = new THREE.CanvasTexture(flareCanvas);

        heroMaterial = new THREE.PointsMaterial({
            size: 20, // Huge base size for flares
            map: flareTexture,
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        heroSystem = new THREE.Points(heroGeometry, heroMaterial);
        heroSystem.position.z = -6500;
        
        heroSystem2 = new THREE.Points(heroGeometry, heroMaterial);
        heroSystem2.position.z = -21500;
        
        scene.add(heroSystem);
        scene.add(heroSystem2);

        // 🌌 NEW: Distant Galaxy Background
        const loadingManager = new THREE.LoadingManager();
        loadingManager.onLoad = function() {
            // Trigger cinematic entrance when galaxy texture is fully loaded
            container.style.opacity = 1;
            isEntering = true;
        };
        
        const galaxyTexture = new THREE.TextureLoader(loadingManager).load('https://zurabkostava.com/wp-content/uploads/2026/07/Galaxy.webp');
        
        // Create radial alpha map for the galaxy to fade edges and keep center bright
        const alphaCanvas = document.createElement('canvas');
        alphaCanvas.width = 512;
        alphaCanvas.height = 512;
        const actx = alphaCanvas.getContext('2d');
        
        // Fill background with black (fully transparent in alpha map)
        actx.fillStyle = 'black';
        actx.fillRect(0, 0, 512, 512);
        
        // Draw radial white gradient (white = opaque)
        const agrad = actx.createRadialGradient(256, 256, 0, 256, 256, 256);
        agrad.addColorStop(0, 'rgba(255, 255, 255, 1)');     // Center bright
        agrad.addColorStop(0.2, 'rgba(255, 255, 255, 0.9)'); 
        agrad.addColorStop(0.5, 'rgba(255, 255, 255, 0.3)'); // Fast fade
        agrad.addColorStop(1, 'rgba(0, 0, 0, 1)');           // Edges invisible
        
        actx.fillStyle = agrad;
        actx.fillRect(0, 0, 512, 512);
        
        const alphaTexture = new THREE.CanvasTexture(alphaCanvas);

        const galaxyMaterial = new THREE.MeshBasicMaterial({
            map: galaxyTexture,
            alphaMap: alphaTexture,
            transparent: true,
            opacity: 0.5, // Reduced overall opacity
            depthWrite: false,
            blending: THREE.AdditiveBlending,
            fog: false // Prevent the 3D fog from turning the distant galaxy black
        });
        
        // Make it massive since it's very far away
        const galaxyGeometry = new THREE.PlaneGeometry(26000, 26000);
        galaxyMesh = new THREE.Mesh(galaxyGeometry, galaxyMaterial);
        
        // Position it extremely far away (z=-25000)
        galaxyMesh.position.set(12000, 8000, -25000);
        // Tilt it slightly for a more natural angle
        galaxyMesh.rotation.z = -Math.PI / 6;
        galaxyMesh.rotation.y = Math.PI / 12; // Slight 3D tilt
        galaxyMesh.rotation.x = Math.PI / 12;
        
        // Ensure it renders behind all stars
        galaxyMesh.renderOrder = -1;
        scene.add(galaxyMesh);

        // 🌌 Galaxy Glow (Volumetric Light Effect)
        const glowCanvas = document.createElement('canvas');
        glowCanvas.width = 512;
        glowCanvas.height = 512;
        const gctx = glowCanvas.getContext('2d');
        const ggrad = gctx.createRadialGradient(256, 256, 0, 256, 256, 256);
        // Subtle deep cosmic purple/blue glow (#221f5c -> rgba(34, 31, 92))
        ggrad.addColorStop(0, 'rgba(34, 31, 92, 0.6)'); 
        ggrad.addColorStop(0.4, 'rgba(34, 31, 92, 0.15)');
        ggrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
        
        gctx.fillStyle = ggrad;
        gctx.fillRect(0, 0, 512, 512);
        
        const glowTexture = new THREE.CanvasTexture(glowCanvas);
        const glowMaterial = new THREE.MeshBasicMaterial({
            map: glowTexture,
            transparent: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            fog: false
        });
        
        // Make the glow just slightly larger than the galaxy itself
        const glowGeometry = new THREE.PlaneGeometry(32000, 32000);
        galaxyGlowMesh = new THREE.Mesh(glowGeometry, glowMaterial);
        
        // Match galaxy position and rotation, but place slightly behind
        galaxyGlowMesh.position.copy(galaxyMesh.position);
        galaxyGlowMesh.position.z -= 100;
        galaxyGlowMesh.rotation.copy(galaxyMesh.rotation);
        
        // Render behind the galaxy
        galaxyGlowMesh.renderOrder = -2;
        scene.add(galaxyGlowMesh);

        // 🌌 Galaxy Core (Bright Center)
        const coreCanvas = document.createElement('canvas');
        coreCanvas.width = 256;
        coreCanvas.height = 256;
        const cctx = coreCanvas.getContext('2d');
        const cgrad = cctx.createRadialGradient(128, 128, 0, 128, 128, 128);
        cgrad.addColorStop(0, 'rgba(255, 255, 255, 0.8)'); // Bright white center
        cgrad.addColorStop(0.3, 'rgba(255, 245, 220, 0.3)'); // Slight warm glow
        cgrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
        
        cctx.fillStyle = cgrad;
        cctx.fillRect(0, 0, 256, 256);
        
        const coreTexture = new THREE.CanvasTexture(coreCanvas);
        const coreMaterial = new THREE.MeshBasicMaterial({
            map: coreTexture,
            transparent: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            fog: false
        });
        
        // Make the core much smaller than the galaxy
        const coreGeometry = new THREE.PlaneGeometry(9000, 9000);
        galaxyCoreMesh = new THREE.Mesh(coreGeometry, coreMaterial);
        
        // Match galaxy position and rotation, but place slightly in front
        galaxyCoreMesh.position.copy(galaxyMesh.position);
        galaxyCoreMesh.position.z += 50;
        galaxyCoreMesh.rotation.copy(galaxyMesh.rotation);
        
        galaxyCoreMesh.renderOrder = -1;
        scene.add(galaxyCoreMesh);

        // 🎇 NEW: Massive Boundary Nebula (Bottom-Left)
        clusterSystem = new THREE.Group();

        // Create a soft radial gradient texture for the gas clouds
        const nebulaCanvas = document.createElement('canvas');
        nebulaCanvas.width = 512;
        nebulaCanvas.height = 512;
        const nctx = nebulaCanvas.getContext('2d');
        const ngrad = nctx.createRadialGradient(256, 256, 0, 256, 256, 256);
        ngrad.addColorStop(0, 'rgba(255, 255, 255, 1)'); 
        ngrad.addColorStop(0.2, 'rgba(255, 255, 255, 0.6)');
        ngrad.addColorStop(0.6, 'rgba(255, 255, 255, 0.1)');
        ngrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
        nctx.fillStyle = ngrad;
        nctx.fillRect(0, 0, 512, 512);
        const nebulaTexture = new THREE.CanvasTexture(nebulaCanvas);
        
        const nebulaMaterial = new THREE.MeshBasicMaterial({
            map: nebulaTexture,
            transparent: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            fog: false,
            opacity: 0.05 // Much fainter, more map-like
        });
        
        // Add 20 overlapping soft planes to form an irregular nebula cloud
        // Subtler, desaturated cosmic colors for a faint galactic band
        const nebColors = [0x221144, 0x112244, 0x331133, 0x1a1a3a]; 
        for (let i = 0; i < 20; i++) {
            const size = 15000 + Math.random() * 15000; // Even larger clouds
            const planeMat = nebulaMaterial.clone();
            planeMat.color.setHex(nebColors[Math.floor(Math.random() * nebColors.length)]);
            
            const plane = new THREE.Mesh(new THREE.PlaneGeometry(size, size), planeMat);
            // Gaussian-like random to avoid hard cube edges
            const rx = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const ry = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const rz = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            
            plane.position.set(
                rx * 20000,
                ry * 6000,
                rz * 4000
            );
            plane.rotation.z = Math.random() * Math.PI * 2;
            clusterSystem.add(plane);
        }
        
        // Add a few bright stars inside the nebula
        const nebStarsGeom = new THREE.BufferGeometry();
        const nebStarsPos = new Float32Array(500 * 3);
        for(let i=0; i<500; i++) {
            // Gaussian distribution for natural scattering
            const rx = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const ry = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const rz = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            
            nebStarsPos[i*3] = rx * 25000;
            nebStarsPos[i*3+1] = ry * 10000;
            nebStarsPos[i*3+2] = rz * 4000;
        }
        nebStarsGeom.setAttribute('position', new THREE.BufferAttribute(nebStarsPos, 3));
        const nebStarsMat = new THREE.PointsMaterial({
            size: 2.0, color: 0xffffff, transparent: true, opacity: 0.6, fog: false
        });
        const nebStars = new THREE.Points(nebStarsGeom, nebStarsMat);
        clusterSystem.add(nebStars);
        
        // Position it much further left and slightly lower
        clusterSystem.position.set(-16000, -8000, -30000);
        
        scene.add(clusterSystem);

        // Connect the time slider if it exists
        const slider = document.getElementById('zk-speed-slider');
        const speedValueDisplay = document.getElementById('zk-speed-value');
        if (slider) {
            slider.addEventListener('input', (e) => {
                timeMultiplier = parseFloat(e.target.value);
                if (speedValueDisplay) speedValueDisplay.innerText = timeMultiplier + 'x';
            });
        }

        window.addEventListener('resize', onWindowResize);

        if (!isRunning) {
            isRunning = true;
            animate();
        }
    }

    function onWindowResize() {
        if (!camera || !renderer || !container) return;
        const rect = container.parentElement.getBoundingClientRect();
        camera.aspect = rect.width / rect.height;
        camera.updateProjectionMatrix();
        renderer.setSize(rect.width, rect.height);
    }

    function animate() {
        if (!isRunning) return;
        
        if (!document.getElementById('zk-galaxy-canvas')) {
            isRunning = false;
            window.removeEventListener('resize', onWindowResize);
            if (renderer) renderer.dispose();
            return;
        }

        animationFrameId = requestAnimationFrame(animate);

        const speed = 0.4 * timeMultiplier;
        
        // Move all systems forward
        starSystem.position.z += speed;
        starSystem2.position.z += speed;
        heroSystem.position.z += speed;
        heroSystem2.position.z += speed;
        
        // Snap back when they pass the camera
        if (starSystem.position.z > 8500) {
            starSystem.position.z -= 30000;
        }
        if (starSystem2.position.z > 8500) {
            starSystem2.position.z -= 30000;
        }
        if (heroSystem.position.z > 8500) {
            heroSystem.position.z -= 30000;
        }
        if (heroSystem2.position.z > 8500) {
            heroSystem2.position.z -= 30000;
        }
        
        // Move the boundary cluster
        if (clusterSystem) {
            clusterSystem.position.z += speed;
        }
        
        // Cinematic Entrance Animation
        if (isEntering) {
            // Glide forward and smoothly barrel-roll into position
            camera.position.z += (1000 - camera.position.z) * 0.03;
            // Make rotation faster so it settles naturally before position finishes
            camera.rotation.z += (0 - camera.rotation.z) * 0.04;
            
            // Wait until both are virtually perfect before turning off the animation
            if (Math.abs(camera.position.z - 1000) < 1 && Math.abs(camera.rotation.z) < 0.005) {
                camera.position.z = 1000;
                camera.rotation.z = 0;
                isEntering = false;
            }
        }

        // Animate the distant galaxy
        if (galaxyMesh) {
            // Speed = 0.05 units per frame. 
            // At 60fps = 3 units/sec. To travel 14000 units takes ~77 minutes.
            const moveZ = 0.05 * timeMultiplier;
            galaxyMesh.position.z += moveZ;
            if (galaxyGlowMesh) galaxyGlowMesh.position.z += moveZ;
            if (galaxyCoreMesh) galaxyCoreMesh.position.z += moveZ;
            
            // Calculate how many stars to draw based on galaxy approach
            // Starts decreasing at z=-20000 (early in the journey), finishes decreasing at z=-15000
            let starFactor = 1.0;
            if (galaxyMesh.position.z > -20000) {
                const progress = (galaxyMesh.position.z - (-20000)) / 5000;
                const clampedProgress = Math.max(0, Math.min(1, progress));
                starFactor = 1.0 - (clampedProgress * 0.995); // Drops to 0.5% stars remaining
            }

            // Apply the factor using setDrawRange (highly performant GPU trick)
            const baseCount = starSystem.geometry.attributes.position.count;
            starSystem.geometry.setDrawRange(0, Math.floor(baseCount * starFactor));
            starSystem2.geometry.setDrawRange(0, Math.floor(baseCount * starFactor));
            
            const heroCount = heroSystem.geometry.attributes.position.count;
            heroSystem.geometry.setDrawRange(0, Math.floor(heroCount * starFactor));
            heroSystem2.geometry.setDrawRange(0, Math.floor(heroCount * starFactor));
            
            // Warp Speed Visual Effects (Camera Shake)
            if (timeMultiplier > 10) {
                const shake = Math.min(3, timeMultiplier / 50);
                camera.position.x = (Math.random() - 0.5) * shake;
                camera.position.y = (Math.random() - 0.5) * shake;
            } else {
                camera.position.x += (0 - camera.position.x) * 0.1;
                camera.position.y += (0 - camera.position.y) * 0.1;
            }
        }

        renderer.render(scene, camera);
    }
    const observer = new MutationObserver((mutations) => {
        if (!isRunning && document.getElementById('zk-galaxy-canvas')) {
            initGalaxy();
        } else if (isRunning && !document.getElementById('zk-galaxy-canvas')) {
            isRunning = false;
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            if (renderer) renderer.dispose();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        initGalaxy();
        const appNode = document.getElementById('app');
        if (appNode) {
            observer.observe(appNode, { childList: true, subtree: true });
        }
        
        // Screensaver Logic
        const screensaverBtn = document.getElementById('zk-screensaver-btn');
        if (screensaverBtn) {
            // Apply transition class to all elements we want to fade out
            // We use .header-inner instead of .site-header so the top blur gradient remains visible
            const elementsToHide = document.querySelectorAll('.header-inner, .site-footer, .hero-inner, .hero-latest-dock, .zk-welcome-music-container, #zk-cinematic-phrase-container, #zk-time-control');
            elementsToHide.forEach(el => el.classList.add('screensaver-element'));
            
            screensaverBtn.addEventListener('click', () => {
                document.body.classList.toggle('screensaver-mode');
                const isScreensaver = document.body.classList.contains('screensaver-mode');
                
                const expandIcon = screensaverBtn.querySelector('.icon-expand');
                const collapseIcon = screensaverBtn.querySelector('.icon-collapse');
                if (expandIcon) expandIcon.style.display = isScreensaver ? 'none' : 'block';
                if (collapseIcon) collapseIcon.style.display = isScreensaver ? 'block' : 'none';
                
                // Handle Music
                const audio = document.getElementById('zk-welcome-audio');
                if (audio) {
                    if (isScreensaver && !audio.paused) {
                        audio.dataset.wasPlaying = 'true';
                        audio.pause();
                        const playIcon = document.querySelector('.icon-play');
                        const stopIcon = document.querySelector('.icon-stop');
                        if (playIcon) playIcon.style.display = 'block';
                        if (stopIcon) stopIcon.style.display = 'none';
                    } else if (!isScreensaver && audio.dataset.wasPlaying === 'true') {
                        audio.play();
                        audio.dataset.wasPlaying = 'false';
                        const playIcon = document.querySelector('.icon-play');
                        const stopIcon = document.querySelector('.icon-stop');
                        if (playIcon) playIcon.style.display = 'none';
                        if (stopIcon) stopIcon.style.display = 'block';
                    }
                }
            });
        }
    });
})();
