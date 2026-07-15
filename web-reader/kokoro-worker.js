import { KokoroTTS } from 'https://cdn.jsdelivr.net/npm/kokoro-js/+esm';

let tts = null;
const device = "wasm"; // Force WASM to prevent integrated GPU hallucinations

self.onmessage = async (e) => {
    const data = e.data;
    console.log("[Kokoro Worker] Received message:", data.type);
    
    if (data.type === 'init') {
        try {
            console.log("[Kokoro Worker] Starting from_pretrained...");
            const device = navigator.gpu ? "webgpu" : "wasm";
            console.log("[Kokoro Worker] Using device:", device);
            tts = await KokoroTTS.from_pretrained("onnx-community/Kokoro-82M-v1.0-ONNX", {
                dtype: "q8",
                device: device,
                progress_callback: (info) => {
                    self.postMessage({ type: 'progress', info });
                }
            });
            console.log("[Kokoro Worker] from_pretrained complete!");
            self.postMessage({ type: 'init_done', success: true });
        } catch (err) {
            console.error("[Kokoro Worker] init error:", err);
            self.postMessage({ type: 'init_done', success: false, error: err.message || String(err) });
        }
    } else if (data.type === 'generate') {
        if (!tts) {
            console.error("[Kokoro Worker] generate called but tts not initialized!");
            self.postMessage({ type: 'generate_done', id: data.id, success: false, error: "Not initialized" });
            return;
        }
        try {
            console.log(`[Kokoro Worker] Generating audio for text: "${data.text.substring(0, 50)}...", voice: ${data.voice}`);
            const audioData = await tts.generate(data.text, { voice: data.voice });
            console.log("[Kokoro Worker] Audio generated successfully. Converting to Blob...");
            const blob = audioData.toBlob();
            console.log(`[Kokoro Worker] Blob created, size: ${blob.size} bytes. Sending back to main thread...`);
            self.postMessage({ type: 'generate_done', id: data.id, success: true, blob: blob });
            console.log("[Kokoro Worker] generate_done posted.");
        } catch (err) {
            console.error("[Kokoro Worker] generate error:", err);
            self.postMessage({ type: 'generate_done', id: data.id, success: false, error: err.message || String(err) });
        }
    }
};
