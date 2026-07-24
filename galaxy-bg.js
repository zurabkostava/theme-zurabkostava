/**
 * Three.js Galaxy Background Animation for Zurab Kostava Theme
 * Ultra-realistic, extremely dense, slow-moving 3D starfield.
 */

(function() {
    let container;
    let camera, scene, renderer;
    let starsGeometry, starsMaterial, starSystem;
    let isRunning = false;
    let animationFrameId;

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

        // Camera - huge far plane
        camera = new THREE.PerspectiveCamera(60, rect.width / rect.height, 1, 15000);
        camera.position.z = 1000;

        renderer = new THREE.WebGLRenderer({ canvas: container, alpha: true, antialias: true });
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(rect.width, rect.height);
        renderer.setClearColor(0x000000, 0); 

        // Generate stars
        const starCount = 700000; // 700k points for maximum density without massive performance drop
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
        const numClusters = 300; 
        const clusters = [];
        for (let c = 0; c < numClusters; c++) {
            clusters.push({
                x: THREE.MathUtils.randFloatSpread(4500), // Balanced spread to cover edges without diluting density
                y: THREE.MathUtils.randFloatSpread(3000),
                z: THREE.MathUtils.randFloatSpread(15000),
                radiusX: Math.random() * 600 + 200, 
                radiusY: Math.random() * 600 + 200, 
                radiusZ: Math.random() * 2000 + 500 
            });
        }

        for (let i = 0; i < starCount; i++) {
            let x, y, z;
            
            // 75% of stars go into clusters, 25% are scattered for background noise
            if (Math.random() < 0.75) {
                const cluster = clusters[Math.floor(Math.random() * clusters.length)];
                
                // Using power of 2 for a soft falloff
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
            
            // Increased sizes so they don't anti-alias into nothingness
            sizes[i] = Math.random() * 1.5 + 1.2;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

        // Tighter gradient for sharp points
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
            size: 2.5, // Increased base size
            map: texture,
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        starSystem = new THREE.Points(geometry, starsMaterial);
        // Push the whole system back so it aligns with the massive camera distance
        starSystem.position.z = -6500;
        scene.add(starSystem);

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

        // Straight forward movement
        const positions = starSystem.geometry.attributes.position.array;
        
        // Slower movement
        const speed = 0.4;

        for (let i = 0; i < positions.length; i += 3) {
            positions[i + 2] += speed;
            
            // Reset distance matching the huge Z spread (15000)
            if (positions[i + 2] > 7500) {
                positions[i + 2] -= 15000;
            }
        }
        
        starSystem.geometry.attributes.position.needsUpdate = true;

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
