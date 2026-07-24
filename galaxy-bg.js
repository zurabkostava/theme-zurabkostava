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
        // Thicker fog so distant stars look like tiny noise/dust
        scene.fog = new THREE.FogExp2(0x000000, 0.0008);

        // Camera
        camera = new THREE.PerspectiveCamera(60, rect.width / rect.height, 1, 6000);
        camera.position.z = 1000;

        renderer = new THREE.WebGLRenderer({ canvas: container, alpha: true, antialias: true });
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(rect.width, rect.height);
        renderer.setClearColor(0x000000, 0); 

        // Generate stars
        const starCount = 150000; // Massively increased count for "noise" and "snow" effect
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(starCount * 3);
        const colors = new Float32Array(starCount * 3);
        const sizes = new Float32Array(starCount);
        
        const colorPalette = [
            new THREE.Color(0xffffff), // white
            new THREE.Color(0xffffff), // white (bias)
            new THREE.Color(0xddf0ff), // ice blue
            new THREE.Color(0xccddee)  // pale blue
        ];

        for (let i = 0; i < starCount; i++) {
            // Tighter X/Y spread so stars are concentrated in front of the camera
            // Huge Z spread for massive render distance
            const x = THREE.MathUtils.randFloatSpread(2500);
            const y = THREE.MathUtils.randFloatSpread(2500);
            const z = THREE.MathUtils.randFloatSpread(5000); // from -2500 to +2500

            positions[i * 3] = x;
            positions[i * 3 + 1] = y;
            positions[i * 3 + 2] = z;

            const color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            colors[i * 3] = color.r;
            colors[i * 3 + 1] = color.g;
            colors[i * 3 + 2] = color.b;
            
            // Sizes between 1 and 2.5
            sizes[i] = Math.random() * 1.5 + 1;
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
            size: 2.5, // Slightly bigger base size to be visible
            map: texture,
            transparent: true,
            opacity: 1,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        starSystem = new THREE.Points(geometry, starsMaterial);
        // Push the whole system back so it aligns with the camera distance
        starSystem.position.z = -1500;
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
        
        // Faster movement for "snow" effect
        const speed = 1.5;

        for (let i = 0; i < positions.length; i += 3) {
            positions[i + 2] += speed;
            
            // Reset distance matching the Z spread (5000)
            if (positions[i + 2] > 2500) {
                positions[i + 2] -= 5000;
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
