import { KokoroTTS } from 'https://cdn.jsdelivr.net/npm/kokoro-js@1.2.1/dist/kokoro.web.js';

let tts = null;

function float32ToWav(buffer, sampleRate) {
    if (!buffer) throw new Error("Buffer is undefined");
    if (buffer.data && buffer.data instanceof Float32Array) buffer = buffer.data; 
    const numChannels = 1;
    const bytesPerSample = 2;
    const dataLength = buffer.length * bytesPerSample;
    const headerLength = 44;
    const totalLength = headerLength + dataLength;
    if (isNaN(totalLength)) throw new Error("Invalid totalLength: " + totalLength);
    const wav = new ArrayBuffer(totalLength);
    const view = new DataView(wav);

    const writeStr = (offset, str) => {
        for (let i = 0; i < str.length; i++) view.setUint8(offset + i, str.charCodeAt(i));
    };
    writeStr(0, 'RIFF');
    view.setUint32(4, totalLength - 8, true);
    writeStr(8, 'WAVE');
    writeStr(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, numChannels, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * numChannels * bytesPerSample, true);
    view.setUint16(32, numChannels * bytesPerSample, true);
    view.setUint16(34, bytesPerSample * 8, true);
    writeStr(36, 'data');
    view.setUint32(40, dataLength, true);

    const VOLUME_MULTIPLIER = 2.0; 
    for (let i = 0; i < buffer.length; i++) {
        let sample = buffer[i] * VOLUME_MULTIPLIER;
        if (sample > 1.0) sample = 1.0;
        if (sample < -1.0) sample = -1.0;
        view.setInt16(44 + i * 2, sample < 0 ? sample * 0x8000 : sample * 0x7FFF, true);
    }
    return new Blob([wav], { type: 'audio/wav' });
}

self.onmessage = async (e) => {
    const msg = e.data;

    if (msg.kind === 'init') {
        try {
            self.postMessage({ kind: 'status', message: 'Downloading Kokoro Model (~80MB)...' });
            
            let device = 'wasm';
            if (navigator.gpu) {
                device = 'webgpu';
            }

            try {
                tts = await KokoroTTS.from_pretrained('onnx-community/Kokoro-82M-v1.0-ONNX', {
                    dtype: 'q8',
                    device: device,
                    progress_callback: (x) => {
                        if (x.status === 'downloading' || x.status === 'progress') {
                            self.postMessage({ kind: 'progress', data: x });
                        }
                    }
                });
            } catch (err1) {
                console.warn("WebGPU init failed, falling back to WASM:", err1);
                tts = await KokoroTTS.from_pretrained('onnx-community/Kokoro-82M-v1.0-ONNX', {
                    dtype: 'q8',
                    device: 'wasm',
                    progress_callback: (x) => {
                        if (x.status === 'downloading' || x.status === 'progress') {
                            self.postMessage({ kind: 'progress', data: x });
                        }
                    }
                });
            }

            self.postMessage({ kind: 'ready' });
        } catch (err) {
            self.postMessage({ kind: 'error', message: "Init Error: " + err.toString() });
        }
    } 
    else if (msg.kind === 'synthesize') {
        if (!tts) {
            self.postMessage({ kind: 'error', message: 'Kokoro not initialized' });
            return;
        }

        try {
            self.postMessage({ kind: 'status', message: 'Synthesizing...' });
            const result = await tts.generate(msg.text, {
                voice: msg.voicePath,
            });

            if (!result || !result.audio) {
                self.postMessage({ kind: 'error', message: "result.audio is missing. Keys: " + Object.keys(result || {}).join(',') });
                return;
            }

            const wavBlob = float32ToWav(result.audio, result.sampling_rate || 24000);
            self.postMessage({ kind: 'status', message: null });
            self.postMessage({ kind: 'output', wav: wavBlob });

        } catch (err) {
            self.postMessage({ kind: 'status', message: null });
            self.postMessage({ kind: 'error', message: "Synth Error: " + err.toString() });
        }
    }
};
