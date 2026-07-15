import { KokoroTTS } from 'https://cdn.jsdelivr.net/npm/kokoro-js/+esm';

let tts = null;

self.onmessage = async (e) => {
    const data = e.data;
    
    if (data.type === 'init') {
        try {
            tts = await KokoroTTS.from_pretrained("onnx-community/Kokoro-82M-v1.0-ONNX", {
                dtype: "q8",
                device: "wasm"
            });
            self.postMessage({ type: 'init_done', success: true });
        } catch (err) {
            console.error("Worker Kokoro init error:", err);
            self.postMessage({ type: 'init_done', success: false, error: err.message });
        }
    } else if (data.type === 'generate') {
        if (!tts) {
            self.postMessage({ type: 'generate_done', id: data.id, success: false, error: "Not initialized" });
            return;
        }
        try {
            const audioData = await tts.generate(data.text, { voice: data.voice });
            const blob = audioData.toBlob();
            self.postMessage({ type: 'generate_done', id: data.id, success: true, blob: blob });
        } catch (err) {
            self.postMessage({ type: 'generate_done', id: data.id, success: false, error: err.message });
        }
    }
};
