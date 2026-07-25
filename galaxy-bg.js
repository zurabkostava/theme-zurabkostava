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
    let galaxyGroup;
    let animationFrameId;
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

        // Camera - huge far plane so large tilted objects don't clip
        camera = new THREE.PerspectiveCamera(60, rect.width / rect.height, 1, 25000);
        camera.position.z = 1000;

        renderer = new THREE.WebGLRenderer({ canvas: container, alpha: true, antialias: true });
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(rect.width, rect.height);
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

        // 🌌 NEW: Volumetric Parallax Galaxy (Card Stack Technique)
        const galaxyTexture = new THREE.TextureLoader().load('https://zurabkostava.com/wp-content/uploads/2026/07/Galaxy.webp');
        const galaxyGeometry = new THREE.PlaneGeometry(15000, 15000);
        
        galaxyGroup = new THREE.Group();
        
        // Create 5 layers to simulate 3D volume
        const layers = 5;
        for (let i = 0; i < layers; i++) {
            const mat = new THREE.MeshBasicMaterial({
                map: galaxyTexture,
                transparent: true,
                // Center layer is the brightest core, outer layers are faint dust
                opacity: (i === 2) ? 0.7 : 0.25, 
                depthWrite: false,
                blending: THREE.AdditiveBlending,
                fog: false 
            });
            const mesh = new THREE.Mesh(galaxyGeometry, mat);
            
            // Offset each layer in Z space to create depth
            mesh.position.z = (i - 2) * 800; // Spaced 800 units apart in local Z
            
            // Scale outer layers slightly down so the edges look softer and spherical
            const scale = 1 - Math.abs(i - 2) * 0.15; 
            mesh.scale.set(scale, scale, 1);
            
            galaxyGroup.add(mesh);
        }

        // Position the entire group far away in the top-right
        galaxyGroup.position.set(7000, 4500, -14000);
        
        // Tilt the entire 3D volume
        galaxyGroup.rotation.x = Math.PI / 12;
        galaxyGroup.rotation.y = Math.PI / 12;
        galaxyGroup.rotation.z = -Math.PI / 6;
        
        galaxyGroup.renderOrder = -1;
        scene.add(galaxyGroup);

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

        // Animate the distant galaxy
        if (galaxyGroup) {
            // Speed = 0.05 units per frame. 
            // At 60fps = 3 units/sec. To travel 14000 units takes ~77 minutes.
            galaxyGroup.position.z += 0.05 * timeMultiplier;
            // Extremely slow rotation to give it life
            galaxyGroup.rotation.z += 0.00005 * timeMultiplier;
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
    });
})();
