/**
 * Galaxy Background Animation for Zurab Kostava Theme
 * Ultra-realistic, performant 3D starfield effect on a 2D Canvas.
 */

(function() {
    let canvas, ctx;
    let stars = [];
    let animationFrameId;
    let isRunning = false;
    
    // Config
    const STAR_COUNT = 800; // Number of stars for realistic dense feeling
    const STAR_SPEED = 2.5; // Base speed of travel
    const STAR_Z_MAX = 2000; // Maximum depth
    
    class Star {
        constructor(width, height) {
            this.width = width;
            this.height = height;
            this.x = (Math.random() - 0.5) * width * 2;
            this.y = (Math.random() - 0.5) * height * 2;
            this.z = Math.random() * STAR_Z_MAX;
            this.pz = this.z;
            
            // Random colors for stars (mostly white/blue/yellow)
            const colors = [
                'rgba(255, 255, 255, 1)',
                'rgba(255, 255, 255, 0.8)',
                'rgba(200, 220, 255, 1)',
                'rgba(255, 250, 200, 1)'
            ];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }
        
        update(speedModifier) {
            this.pz = this.z;
            this.z -= STAR_SPEED * speedModifier;
            
            if (this.z < 1) {
                this.z = STAR_Z_MAX;
                this.x = (Math.random() - 0.5) * this.width * 2;
                this.y = (Math.random() - 0.5) * this.height * 2;
                this.pz = this.z;
            }
        }
        
        draw(ctx, width, height) {
            let cx = width / 2;
            let cy = height / 2;
            
            // Current position
            let sx = (this.x / this.z) * cx + cx;
            let sy = (this.y / this.z) * cy + cy;
            
            // Previous position for trails
            let px = (this.x / this.pz) * cx + cx;
            let py = (this.y / this.pz) * cy + cy;
            
            let radius = Math.max(0.1, (1 - this.z / STAR_Z_MAX) * 2.5);
            let alpha = Math.max(0.1, 1 - (this.z / STAR_Z_MAX));

            // Apply calculated alpha to the color
            ctx.globalAlpha = alpha;
            ctx.strokeStyle = this.color;
            ctx.lineWidth = radius;
            
            ctx.beginPath();
            ctx.moveTo(px, py);
            ctx.lineTo(sx, sy);
            ctx.stroke();
            
            // Draw a slight glow head
            ctx.fillStyle = this.color;
            ctx.beginPath();
            ctx.arc(sx, sy, radius, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.globalAlpha = 1;
        }
    }
    
    function initGalaxy() {
        canvas = document.getElementById('zk-galaxy-canvas');
        if (!canvas) return;
        
        ctx = canvas.getContext('2d', { alpha: true });
        
        resize();
        window.addEventListener('resize', resize);
        
        // Initialize stars
        stars = [];
        for (let i = 0; i < STAR_COUNT; i++) {
            stars.push(new Star(canvas.width, canvas.height));
        }
        
        if (!isRunning) {
            isRunning = true;
            animate();
        }
    }
    
    function resize() {
        if (!canvas) return;
        // High DPI canvas support
        const dpr = window.devicePixelRatio || 1;
        // Use offsetWidth/Height instead of innerWidth so it fits the container
        const rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        canvas.style.width = `${rect.width}px`;
        canvas.style.height = `${rect.height}px`;
    }
    
    function animate() {
        if (!isRunning) return;
        
        // Ensure canvas is still in DOM (SPA routing support)
        if (!document.getElementById('zk-galaxy-canvas')) {
            isRunning = false;
            window.removeEventListener('resize', resize);
            return;
        }
        
        // Clear canvas with a slight trail effect (motion blur)
        // Clear canvas with a slight trail effect (motion blur) while keeping background transparent
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = 'rgba(0, 0, 0, 0.3)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.globalCompositeOperation = 'source-over';
        
        // Subtle interaction or speed variations
        let speedModifier = 1;
        
        // Dynamic speed based on scroll maybe?
        const scrollY = window.scrollY;
        speedModifier += scrollY * 0.005;
        // Cap speed
        if (speedModifier > 5) speedModifier = 5;
        
        const rect = canvas.parentElement.getBoundingClientRect();
        const w = rect.width;
        const h = rect.height;
        
        stars.forEach(star => {
            star.update(speedModifier);
            star.draw(ctx, w, h);
        });
        
        animationFrameId = requestAnimationFrame(animate);
    }
    
    // SPA initialization observer (useful if content is replaced dynamically)
    // The theme uses an SPA router and replaces content in #app -> #view
    const observer = new MutationObserver((mutations) => {
        if (!isRunning && document.getElementById('zk-galaxy-canvas')) {
            initGalaxy();
        } else if (isRunning && !document.getElementById('zk-galaxy-canvas')) {
            isRunning = false;
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
        }
    });
    
    // Start observer
    document.addEventListener('DOMContentLoaded', () => {
        initGalaxy();
        
        const appNode = document.getElementById('app');
        if (appNode) {
            observer.observe(appNode, { childList: true, subtree: true });
        }
    });
    
})();
