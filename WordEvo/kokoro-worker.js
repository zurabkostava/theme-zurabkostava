import { pipeline } from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@4.2.0';

let synthesizer = null;

function float32ToWav(buffer, sampleRate) {
    const numChannels = 1;
    const bytesPerSample = 2;
    const dataLength = buffer.length * bytesPerSample;
    const headerLength = 44;
    const totalLength = headerLength + dataLength;
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

    const VOLUME_MULTIPLIER = 2.0; // Boost volume slightly
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
            self.postMessage({ kind: 'status', message: 'Downloading/Loading Kokoro (80MB)...' });
            
            synthesizer = await pipeline('text-to-audio', 'onnx-community/Kokoro-82M-v1.0-ONNX', {
                dtype: 'q8',
            });

            self.postMessage({ kind: 'ready' });
        } catch (err) {
            self.postMessage({ kind: 'error', message: err.toString() });
        }
    } 
    else if (msg.kind === 'synthesize') {
        if (!synthesizer) {
            self.postMessage({ kind: 'error', message: 'Kokoro not initialized' });
            return;
        }

        try {
            const result = await synthesizer(msg.text, {
                voice: msg.voicePath, // voice name, e.g., 'af_heart'
            });

            const wavBlob = float32ToWav(result.audio, result.sampling_rate);
            self.postMessage({ kind: 'output', wav: wavBlob });

        } catch (err) {
            self.postMessage({ kind: 'error', message: err.toString() });
        }
    }
};
