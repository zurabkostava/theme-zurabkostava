/**
 * Three.js Galaxy Background Animation for Zurab Kostava Theme
 * Ultra-realistic, extremely dense, slow-moving 3D starfield.
 */

(function() {
    let container;
    let canvas, ctx;
    let scene, camera, renderer;
    let starSystem, starSystem2, starsMaterial;
    let heroSystem, heroSystem2, heroMaterial, heroSystem3;
    let galaxyMesh, galaxyGlowMesh, galaxyCoreMesh;
    let clusterSystem, nebulaSystem; 
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
        camera = new THREE.PerspectiveCamera(60, rect.width / rect.height, 1, 50000);
        
        // Start far away and rotated for cinematic barrel roll entrance
        camera.position.z = 25000;
        camera.rotation.z = Math.PI * 0.5;

        renderer = new THREE.WebGLRenderer({ canvas: container, alpha: false, antialias: false, powerPreference: "high-performance", stencil: false, depth: false });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.25)); // 🚀 MASSIVE GPU FILL-RATE OPTIMIZATION
        renderer.setSize(rect.width, rect.height);
        
        // Hide canvas initially to prevent pop-in
        container.style.opacity = 0;
        container.style.transition = 'opacity 3s ease-out';
        
        // Opaque background matching the CSS body background to prevent alpha channel accumulation bug with AdditiveBlending
        renderer.setClearColor(0x020205, 1.0); 

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
            const angle = Math.random() * Math.PI * 2;
            // Push clusters away from the exact center (x=0, y=0) so we fly past them, not through epicenters
            const radius = 800 + Math.sqrt(Math.random()) * 1700;
            
            // 🌌 Randomly assign density profiles to clusters
            const typeRand = Math.random();
            let densityExponent = 2.0; // Default: Highly concentrated core (Globular cluster)
            if (typeRand > 0.8) densityExponent = 0.33; // 20% chance: Uniformly scattered/loose
            else if (typeRand > 0.4) densityExponent = 1.0; // 40% chance: Medium linear falloff
            
            clusters.push({
                x: Math.cos(angle) * radius,
                y: Math.sin(angle) * radius * 0.7,
                z: THREE.MathUtils.randFloatSpread(15000),
                radiusX: Math.random() * 600 + 200, 
                radiusY: Math.random() * 600 + 200, 
                radiusZ: Math.random() * 2000 + 500,
                densityExponent: densityExponent
            });
        }

        for (let i = 0; i < starCount; i++) {
            let x, y, z;
            
            if (Math.random() < 0.75) {
                const cluster = clusters[Math.floor(Math.random() * clusters.length)];
                
                // Spherical coordinates for natural round clusters without grid/cross artifacts
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                
                // Radius based on the cluster's specific density profile
                const r = Math.pow(Math.random(), cluster.densityExponent); 
                
                x = cluster.x + r * Math.sin(phi) * Math.cos(theta) * cluster.radiusX;
                y = cluster.y + r * Math.sin(phi) * Math.sin(theta) * cluster.radiusY;
                z = cluster.z + r * Math.cos(phi) * cluster.radiusZ;
            } else {
                const angle = Math.random() * Math.PI * 2;
                const radius = Math.sqrt(Math.random()) * 2500;
                x = Math.cos(angle) * radius;
                y = Math.sin(angle) * radius * 0.7;
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
            depthWrite: false,
            depthTest: false, // 🚀 Z-Buffer Read Optimization
            alphaTest: 0.01 // 🚀 GPU Fill-rate Optimization: Discard empty pixels!
        });

        // 🔴 OPTIMIZATION: Triple System Infinite Loop
        // We use three identical geometries placed back-to-back to prevent any visual gaps when snapping.
        starSystem = new THREE.Points(geometry, starsMaterial);
        starSystem.position.z = -6500;
        starSystem.frustumCulled = false; // CPU Optimization
        
        starSystem2 = new THREE.Points(geometry, starsMaterial);
        starSystem2.position.z = -21500;
        starSystem2.frustumCulled = false;
        
        starSystem3 = new THREE.Points(geometry, starsMaterial);
        starSystem3.position.z = -36500;
        starSystem3.frustumCulled = false;
        
        scene.add(starSystem);
        scene.add(starSystem2);
        scene.add(starSystem3);

        // 🌟 NEW: Giant Stars (0.2% of stars, 8-pointed flares, with companions)
        const giantBaseCount = 800; // 0.2% of 400,000
        const giantMaxCount = giantBaseCount * 3; // Buffer for companions
        const heroGeometry = new THREE.BufferGeometry();
        const heroPositions = new Float32Array(giantMaxCount * 3);
        const heroColors = new Float32Array(giantMaxCount * 3);
        const heroSizes = new Float32Array(giantMaxCount);

        let gIndex = 0;
        for (let i = 0; i < giantBaseCount; i++) {
            // Match cylindrical distribution
            const angle = Math.random() * Math.PI * 2;
            const radius = Math.sqrt(Math.random()) * 2500;
            const gx = Math.cos(angle) * radius;
            const gy = Math.sin(angle) * radius * 0.7;
            const gz = THREE.MathUtils.randFloatSpread(15000);

            // 1. Giant Star
            heroPositions[gIndex * 3] = gx;
            heroPositions[gIndex * 3 + 1] = gy;
            heroPositions[gIndex * 3 + 2] = gz;
            
            const color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            // Make giants very bright
            heroColors[gIndex * 3] = color.r * 0.8 + 0.2;
            heroColors[gIndex * 3 + 1] = color.g * 0.8 + 0.2;
            heroColors[gIndex * 3 + 2] = color.b * 0.8 + 0.2;
            
            // Varied giant sizes (30 to 80)
            heroSizes[gIndex] = Math.random() * 50 + 30;
            gIndex++;
            
            // 2. Companions (50% chance for 1, 20% chance for 2)
            const compChance = Math.random();
            let companions = 0;
            if (compChance > 0.8) companions = 2;
            else if (compChance > 0.3) companions = 1;
            
            for(let j=0; j<companions; j++) {
                const cAngle = Math.random() * Math.PI * 2;
                const cDist = Math.random() * 40 + 20; // 20 to 60 units away
                heroPositions[gIndex * 3] = gx + Math.cos(cAngle) * cDist;
                heroPositions[gIndex * 3 + 1] = gy + Math.sin(cAngle) * cDist;
                heroPositions[gIndex * 3 + 2] = gz + (Math.random() - 0.5) * cDist;
                
                const cColor = colorPalette[Math.floor(Math.random() * colorPalette.length)];
                heroColors[gIndex * 3] = cColor.r;
                heroColors[gIndex * 3 + 1] = cColor.g;
                heroColors[gIndex * 3 + 2] = cColor.b;
                
                // Companion sizes (10 to 20)
                heroSizes[gIndex] = Math.random() * 10 + 10;
                gIndex++;
            }
        }

        // Only assign the array buffer slice we actually populated
        heroGeometry.setAttribute('position', new THREE.BufferAttribute(heroPositions.slice(0, gIndex * 3), 3));
        heroGeometry.setAttribute('color', new THREE.BufferAttribute(heroColors.slice(0, gIndex * 3), 3));
        heroGeometry.setAttribute('aSize', new THREE.BufferAttribute(heroSizes.slice(0, gIndex), 1));

        // Flare Texture (8-pointed JWST style)
        const flareCanvas = document.createElement('canvas');
        flareCanvas.width = 256;
        flareCanvas.height = 256;
        const flareCtx = flareCanvas.getContext('2d');
        const cx = 128, cy = 128;
        
        // Main Glow
        const radialGlow = flareCtx.createRadialGradient(cx, cy, 0, cx, cy, 128);
        radialGlow.addColorStop(0, 'rgba(255, 255, 255, 1)');
        radialGlow.addColorStop(0.05, 'rgba(255, 255, 255, 0.9)');
        radialGlow.addColorStop(0.2, 'rgba(255, 255, 255, 0.2)');
        radialGlow.addColorStop(1, 'rgba(0, 0, 0, 0)');
        flareCtx.fillStyle = radialGlow;
        flareCtx.fillRect(0, 0, 256, 256);
        
        // Draw spikes
        const drawSpike = (rx, ry, angle = 0) => {
            flareCtx.save();
            flareCtx.translate(cx, cy);
            flareCtx.rotate(angle);
            const grad = flareCtx.createRadialGradient(0, 0, 0, 0, 0, Math.max(rx, ry));
            grad.addColorStop(0, 'rgba(255, 255, 255, 1)');
            grad.addColorStop(0.1, 'rgba(255, 255, 255, 0.8)');
            grad.addColorStop(1, 'rgba(0, 0, 0, 0)');
            flareCtx.fillStyle = grad;
            flareCtx.beginPath();
            flareCtx.ellipse(0, 0, rx, ry, 0, 0, Math.PI * 2);
            flareCtx.fill();
            flareCtx.restore();
        };
        
        // Massive cross
        drawSpike(128, 2, 0); // Horizontal
        drawSpike(2, 128, 0); // Vertical
        // Shorter diagonal X
        drawSpike(64, 2, Math.PI / 4);
        drawSpike(64, 2, -Math.PI / 4);

        const flareTexture = new THREE.CanvasTexture(flareCanvas);

        heroMaterial = new THREE.PointsMaterial({
            size: 1, // Base size overridden by aSize
            map: flareTexture,
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            depthTest: false, // 🚀 Z-Buffer Read Optimization
            alphaTest: 0.01 // 🚀 GPU Fill-rate Optimization
        });

        // 🚀 Add custom shader hook to read `aSize` attribute for different star sizes
        heroMaterial.onBeforeCompile = function (shader) {
            shader.vertexShader = shader.vertexShader.replace(
                'void main() {',
                'attribute float aSize;\nvoid main() {'
            ).replace(
                'gl_PointSize = size;',
                'gl_PointSize = aSize;'
            );
        };

        heroSystem = new THREE.Points(heroGeometry, heroMaterial);
        heroSystem.position.z = -6500;
        heroSystem.frustumCulled = false;
        
        heroSystem2 = new THREE.Points(heroGeometry, heroMaterial);
        heroSystem2.position.z = -21500;
        heroSystem2.frustumCulled = false;
        
        heroSystem3 = new THREE.Points(heroGeometry, heroMaterial);
        heroSystem3.position.z = -36500;
        heroSystem3.frustumCulled = false;
        
        scene.add(heroSystem);
        scene.add(heroSystem2);
        scene.add(heroSystem3);

        // 🌌 NEW: Giant Colorful Nebula Cluster (Appears from the very beginning, reached at 500x speed)
        const giantNebulaStarCount = 80000;
        const giantNebulaGeometry = new THREE.BufferGeometry();
        const giantNebulaPositions = new Float32Array(giantNebulaStarCount * 3);
        const giantNebulaColors = new Float32Array(giantNebulaStarCount * 3);
        const giantNebulaSizes = new Float32Array(giantNebulaStarCount);
        
        const giantNebulaColorsPalette = [
            new THREE.Color(0xff007f), // Neon Pink
            new THREE.Color(0x7f00ff), // Deep Purple
            new THREE.Color(0x00ffff), // Cyan
            new THREE.Color(0xffaa00), // Gold
            new THREE.Color(0xffffff)  // White core
        ];

        // Create 8 massive sub-clusters to form a complex nebula shape
        const giantNebulaSubClusters = [];
        for(let i=0; i<8; i++) {
            const angle = Math.random() * Math.PI * 2;
            const dist = 3500 + Math.random() * 4500; // Keep massive clouds away from direct flight path
            giantNebulaSubClusters.push({
                x: Math.cos(angle) * dist,
                y: Math.sin(angle) * dist * 0.7,
                z: (Math.random() - 0.5) * 15000,
                radiusX: Math.random() * 6000 + 3000, // Large soft clouds
                radiusY: Math.random() * 6000 + 3000,
                radiusZ: Math.random() * 10000 + 4000,
                color: giantNebulaColorsPalette[Math.floor(Math.random() * giantNebulaColorsPalette.length)]
            });
        }

        for (let i = 0; i < giantNebulaStarCount; i++) {
            const sub = giantNebulaSubClusters[Math.floor(Math.random() * giantNebulaSubClusters.length)];
            
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos(2 * Math.random() - 1);
            
            // 0.7 gives a very soft volumetric distribution
            const r = Math.pow(Math.random(), 0.7); 
            
            giantNebulaPositions[i * 3] = sub.x + r * Math.sin(phi) * Math.cos(theta) * sub.radiusX;
            giantNebulaPositions[i * 3 + 1] = sub.y + r * Math.sin(phi) * Math.sin(theta) * sub.radiusY;
            giantNebulaPositions[i * 3 + 2] = sub.z + r * Math.cos(phi) * sub.radiusZ;
            
            // Mix sub-cluster color with a random palette color for beautiful gradients
            const mixColor = giantNebulaColorsPalette[Math.floor(Math.random() * giantNebulaColorsPalette.length)];
            const finalColor = sub.color.clone().lerp(mixColor, 0.3);
            
            giantNebulaColors[i * 3] = finalColor.r;
            giantNebulaColors[i * 3 + 1] = finalColor.g;
            giantNebulaColors[i * 3 + 2] = finalColor.b;
            
            // Massive particles to overlap and create a continuous volumetric gas cloud effect rather than individual stars
            giantNebulaSizes[i] = Math.random() * 200 + 50; 
        }

        giantNebulaGeometry.setAttribute('position', new THREE.BufferAttribute(giantNebulaPositions, 3));
        giantNebulaGeometry.setAttribute('color', new THREE.BufferAttribute(giantNebulaColors, 3));
        giantNebulaGeometry.setAttribute('aSize', new THREE.BufferAttribute(giantNebulaSizes, 1));

        // Create a custom ultra-soft texture specifically for gas clouds (no sharp center)
        const cloudCanvas = document.createElement('canvas');
        cloudCanvas.width = 32;
        cloudCanvas.height = 32;
        const cloudCtx = cloudCanvas.getContext('2d');
        const cloudGrad = cloudCtx.createRadialGradient(16, 16, 0, 16, 16, 16);
        cloudGrad.addColorStop(0, 'rgba(255,255,255,0.15)'); // Very soft core
        cloudGrad.addColorStop(0.5, 'rgba(255,255,255,0.05)'); // Fast fade
        cloudGrad.addColorStop(1, 'rgba(0,0,0,0)');
        cloudCtx.fillStyle = cloudGrad;
        cloudCtx.fillRect(0, 0, 32, 32);
        const cloudTexture = new THREE.CanvasTexture(cloudCanvas);

        const giantNebulaMaterial = new THREE.PointsMaterial({
            size: 1,
            map: cloudTexture, 
            transparent: true,
            opacity: 0.8, // Additive blending with soft texture makes this accumulate gorgeously without blowing out
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            depthTest: false, // 🚀 Z-Buffer Read Optimization
            alphaTest: 0.005, // 🚀 Fragment Shader Early Discard
            fog: false // 🚀 IMPORTANT: Visible from infinite distance!
        });

        giantNebulaMaterial.onBeforeCompile = function (shader) {
            shader.vertexShader = shader.vertexShader.replace(
                'void main() {',
                'attribute float aSize;\nvoid main() {'
            ).replace(
                'gl_PointSize = size;',
                'gl_PointSize = aSize;'
            );
        };

        nebulaSystem = new THREE.Points(giantNebulaGeometry, giantNebulaMaterial);
        // Placed at -42000. At 500x speed (200 units/frame), it reaches the camera in 3.5 seconds.
        nebulaSystem.position.z = -42000;
        nebulaSystem.frustumCulled = false;
        scene.add(nebulaSystem);

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
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            depthTest: false,
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
            depthTest: false,
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
            depthTest: false,
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
            depthTest: false,
            fog: false,
            opacity: 0.05 // Much fainter, more map-like
        });
        
        // Add 20 overlapping soft planes to form an irregular nebula cloud
        // Subtler, desaturated cosmic colors for a faint galactic band
        const nebColors = [0x221144, 0x112244, 0x331133, 0x1a1a3a]; 
        for (let i = 0; i < 20; i++) {
            const size = 20000 + Math.random() * 20000; // Even larger clouds
            const planeMat = nebulaMaterial.clone();
            planeMat.color.setHex(nebColors[Math.floor(Math.random() * nebColors.length)]);
            
            const plane = new THREE.Mesh(new THREE.PlaneGeometry(size, size), planeMat);
            // Gaussian-like random to avoid hard cube edges
            const rx = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const ry = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const rz = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            
            plane.position.set(
                rx * 30000,
                ry * 10000,
                rz * 6000
            );
            plane.rotation.z = Math.random() * Math.PI * 2;
            clusterSystem.add(plane);
        }
        
        // Add beautifully colored, varied-size bright stars inside the nebula
        const nebStarsCount = 815; // 800 regular majestic stars + 15 Ultra-Massive ones
        const nebStarsGeom = new THREE.BufferGeometry();
        const nebStarsPos = new Float32Array(nebStarsCount * 3);
        const nebStarsColors = new Float32Array(nebStarsCount * 3);
        const nebStarsSizes = new Float32Array(nebStarsCount);
        
        for(let i=0; i<nebStarsCount; i++) {
            // Gaussian distribution for natural scattering
            const rx = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const ry = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            const rz = (Math.random() + Math.random() + Math.random() - 1.5) / 1.5;
            
            nebStarsPos[i*3] = rx * 35000;
            nebStarsPos[i*3+1] = ry * 15000;
            nebStarsPos[i*3+2] = rz * 6000;
            
            // Random majestic colors (using existing palette)
            const cColor = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            
            if (i >= 800) {
                // The 15 Ultra-Massive Stars (Reduced bloom to preserve true colors)
                nebStarsColors[i*3] = cColor.r * 1.3; 
                nebStarsColors[i*3+1] = cColor.g * 1.3;
                nebStarsColors[i*3+2] = cColor.b * 1.3;
                nebStarsSizes[i] = Math.random() * 300 + 100; // 100 to 400 sizes
            } else {
                // Regular majestic stars
                nebStarsColors[i*3] = cColor.r * 1.1;
                nebStarsColors[i*3+1] = cColor.g * 1.1;
                nebStarsColors[i*3+2] = cColor.b * 1.1;
                nebStarsSizes[i] = Math.random() > 0.9 ? Math.random() * 100 + 50 : Math.random() * 40 + 20;
            }
        }
        
        nebStarsGeom.setAttribute('position', new THREE.BufferAttribute(nebStarsPos, 3));
        nebStarsGeom.setAttribute('color', new THREE.BufferAttribute(nebStarsColors, 3));
        nebStarsGeom.setAttribute('aSize', new THREE.BufferAttribute(nebStarsSizes, 1));
        
        const nebStarsMat = new THREE.PointsMaterial({
            size: 1, // Overridden by aSize
            map: flareTexture, // Use the glorious 8-pointed JWST flare
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            depthTest: false,
            alphaTest: 0.01,
            fog: false
        });
        
        // Inject shader hook to read `aSize` attribute for different star sizes
        nebStarsMat.onBeforeCompile = function (shader) {
            shader.vertexShader = shader.vertexShader.replace(
                'void main() {',
                'attribute float aSize;\nvoid main() {'
            ).replace(
                'gl_PointSize = size;',
                'gl_PointSize = aSize;'
            );
        };
        
        const nebStars = new THREE.Points(nebStarsGeom, nebStarsMat);
        clusterSystem.add(nebStars);
        
        // Position it much further left and lower
        clusterSystem.position.set(-25000, -14000, -30000);
        
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
        
        // 🚀 Create HUD for Speedometer if it doesn't exist
        let speedHud = document.getElementById('zk-warp-speedometer');
        if (!speedHud) {
            speedHud = document.createElement('div');
            speedHud.id = 'zk-warp-speedometer';
            speedHud.style.position = 'fixed';
            speedHud.style.bottom = '30px';
            speedHud.style.right = '30px';
            speedHud.style.color = '#00ffff';
            speedHud.style.fontFamily = '"Courier New", Courier, monospace';
            speedHud.style.textShadow = '0 0 10px rgba(0, 255, 255, 0.8)';
            speedHud.style.pointerEvents = 'none';
            speedHud.style.zIndex = '9999';
            speedHud.style.opacity = '0'; // Hidden initially
            speedHud.style.transition = 'opacity 1s ease-in-out';
            speedHud.style.background = 'rgba(0, 5, 20, 0.6)';
            speedHud.style.backdropFilter = 'blur(10px)';
            speedHud.style.WebkitBackdropFilter = 'blur(10px)';
            speedHud.style.border = '1px solid rgba(0, 255, 255, 0.2)';
            speedHud.style.padding = '15px 25px';
            speedHud.style.borderRadius = '12px';
            speedHud.style.display = 'flex';
            speedHud.style.flexDirection = 'column';
            speedHud.style.alignItems = 'flex-end';
            speedHud.style.boxShadow = '0 0 20px rgba(0,255,255,0.1)';
            
            speedHud.innerHTML = `
                <div style="font-size: 11px; color: #88bbff; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 5px;">Orbital Velocity</div>
                <div style="font-size: 28px; font-weight: bold; line-height: 1;"><span id="zk-warp-speed-val">756,000,000</span> <span style="font-size:14px; color:#aaa;">c</span></div>
            `;
            document.body.appendChild(speedHud);
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

    let smoothMusicMultiplier = 0;

    function animate() {
        if (!isRunning) return;
        
        if (!document.getElementById('zk-galaxy-canvas')) {
            isRunning = false;
            window.removeEventListener('resize', onWindowResize);
            if (renderer) renderer.dispose();
            return;
        }

        animationFrameId = requestAnimationFrame(animate);

        // 🎵 Sync Galaxy Speed with Music Progress 🎵
        const audio = document.getElementById('zk-welcome-audio');
        let targetMusicMultiplier = 0;
        
        if (audio && !audio.paused && audio.duration > 0) {
            const progress = audio.currentTime / audio.duration;
            // Exponential acceleration: starts gentle, gets crazy fast towards the end (up to 100x)
            targetMusicMultiplier = 100 * Math.pow(progress, 3);
        }
        
        // Smoothly interpolate the music speed bonus so it doesn't snap if paused
        smoothMusicMultiplier += (targetMusicMultiplier - smoothMusicMultiplier) * 0.015;

        // Base speed (timeMultiplier from slider, default 1) + Music bonus
        const currentSpeedMultiplier = timeMultiplier + smoothMusicMultiplier;
        const speed = 0.4 * currentSpeedMultiplier;
        
        // 🚀 Update Speedometer UI
        const hud = document.getElementById('zk-warp-speedometer');
        if (hud) {
            // Show HUD only if music is accelerating
            if (smoothMusicMultiplier > 0.5 || (audio && !audio.paused)) {
                hud.style.opacity = '1';
            } else {
                hud.style.opacity = '0';
            }
            
            const speedValEl = document.getElementById('zk-warp-speed-val');
            if (speedValEl) {
                // Calculated true speed: 1x multiplier = 756 Million times the speed of light
                const currentDisplaySpeed = Math.floor(756000000 * currentSpeedMultiplier);
                speedValEl.innerText = currentDisplaySpeed.toLocaleString('en-US');
            }
        }
        
        // Move all systems forward
        starSystem.position.z += speed;
        starSystem2.position.z += speed;
        starSystem3.position.z += speed;
        heroSystem.position.z += speed;
        heroSystem2.position.z += speed;
        heroSystem3.position.z += speed;
        
        // Snap back when they pass the camera (3 systems * 15000 distance = 45000 total loop)
        if (starSystem.position.z > 8500) {
            starSystem.position.z -= 45000;
        }
        if (starSystem2.position.z > 8500) {
            starSystem2.position.z -= 45000;
        }
        if (starSystem3.position.z > 8500) {
            starSystem3.position.z -= 45000;
        }
        if (heroSystem.position.z > 8500) {
            heroSystem.position.z -= 45000;
        }
        if (heroSystem2.position.z > 8500) {
            heroSystem2.position.z -= 45000;
        }
        if (heroSystem3.position.z > 8500) {
            heroSystem3.position.z -= 45000;
        }
        
        // Move the boundary cluster and nebula
        if (clusterSystem) {
            clusterSystem.position.z += speed;
        }
        if (nebulaSystem) {
            nebulaSystem.position.z += speed;
            if (nebulaSystem.position.z > 20000) {
                nebulaSystem.position.z -= 150000;
            }
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
            const moveZ = 0.05 * currentSpeedMultiplier;
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
            starSystem3.geometry.setDrawRange(0, Math.floor(baseCount * starFactor));
            
            const heroCount = heroSystem.geometry.attributes.position.count;
            heroSystem.geometry.setDrawRange(0, Math.floor(heroCount * starFactor));
            heroSystem2.geometry.setDrawRange(0, Math.floor(heroCount * starFactor));
            heroSystem3.geometry.setDrawRange(0, Math.floor(heroCount * starFactor));
            
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
