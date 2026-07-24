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
        // Give it a very slight fog for depth blending (makes distant stars fade into darkness)
        scene.fog = new THREE.FogExp2(0x000000, 0.0004);

        // Camera with a huge far plane
        camera = new THREE.PerspectiveCamera(60, rect.width / rect.height, 1, 4000);
        camera.position.z = 1000;

        renderer = new THREE.WebGLRenderer({ canvas: container, alpha: true, antialias: true });
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(rect.width, rect.height);
        // transparent background so CSS background/gradient shines through
        renderer.setClearColor(0x000000, 0); 

        // Generate stars
        const starCount = 30000; // huge number of stars for realistic density
        const geometry = new THREE.BufferGeometry();
        const positions = new Float32Array(starCount * 3);
        const colors = new Float32Array(starCount * 3);
        const sizes = new Float32Array(starCount); // Custom size per star
        
        const colorPalette = [
            new THREE.Color(0xffffff), // white
            new THREE.Color(0xbbccff), // pale blue
            new THREE.Color(0xffddaa), // pale yellow
            new THREE.Color(0x88aaff), // deeper blue
            new THREE.Color(0xffbbbb)  // pale red/orange
        ];

        for (let i = 0; i < starCount; i++) {
            // Random positions in a huge box, extending very far backwards
            const x = THREE.MathUtils.randFloatSpread(4000);
            const y = THREE.MathUtils.randFloatSpread(4000);
            const z = THREE.MathUtils.randFloatSpread(4000);

            positions[i * 3] = x;
            positions[i * 3 + 1] = y;
            positions[i * 3 + 2] = z;

            // Random color from palette
            const color = colorPalette[Math.floor(Math.random() * colorPalette.length)];
            colors[i * 3] = color.r;
            colors[i * 3 + 1] = color.g;
            colors[i * 3 + 2] = color.b;
            
            // Randomize size slightly for depth illusion
            sizes[i] = Math.random() * 2 + 1;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

        // Create a circular sprite for stars programmatically with less glow (sharper stars)
        const canvasSprite = document.createElement('canvas');
        canvasSprite.width = 16;
        canvasSprite.height = 16;
        const context = canvasSprite.getContext('2d');
        const gradient = context.createRadialGradient(8, 8, 0, 8, 8, 8);
        gradient.addColorStop(0, 'rgba(255,255,255,1)');
        gradient.addColorStop(0.1, 'rgba(255,255,255,0.8)');
        gradient.addColorStop(0.2, 'rgba(255,255,255,0.1)');
        gradient.addColorStop(1, 'rgba(0,0,0,0)');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 16, 16);

        const texture = new THREE.CanvasTexture(canvasSprite);

        starsMaterial = new THREE.PointsMaterial({
            size: 3, // Reduced base size for less glow
            map: texture,
            transparent: true,
            opacity: 0.9,
            vertexColors: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        starSystem = new THREE.Points(geometry, starsMaterial);
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
        const speed = 0.3;

        for (let i = 0; i < positions.length; i += 3) {
            positions[i + 2] += speed;
            
            // If a star goes past the camera, reset it far back in the distance
            if (positions[i + 2] > 1000) {
                positions[i + 2] -= 4000;
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
