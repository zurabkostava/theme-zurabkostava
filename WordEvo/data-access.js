/* Shared, bounded Supabase access. No background polling or automatic retry loops. */
(function (root) {
    async function readAll(makeQuery, pageSize = 500) {
        const rows = [];
        // Every caller supplies a unique, stable order. Advance by the actual response
        // length so a server-side row cap smaller than pageSize cannot truncate data.
        for (let offset = 0; ; ) {
            const { data, error } = await makeQuery().range(offset, offset + pageSize - 1);
            if (error) throw error;
            if (!data?.length) return rows;
            rows.push(...data);
            offset += data.length;
        }
    }

    function latestWriter(write, onError) {
        const pending = new Map();
        let running = null;
        function enqueue(key, value) {
            let resolve;
            const result = new Promise(done => { resolve = done; });
            const previous = pending.get(key);
            pending.set(key, { value, waiters: [...(previous?.waiters || []), resolve] });
            if (!running) {
                // Combine synchronous increments (e.g. tests + correct) before sending.
                running = Promise.resolve().then(async () => {
                    while (pending.size) {
                        const [nextKey, entry] = pending.entries().next().value;
                        pending.delete(nextKey);
                        let ok = false;
                        try {
                            const response = await write(entry.value);
                            if (response?.error) throw response.error;
                            ok = true;
                        } catch (error) {
                            try { onError(error); } catch (reportError) { console.error(reportError); }
                        }
                        entry.waiters.forEach(done => done(ok));
                    }
                    running = null;
                });
            }
            return result;
        }
        return { enqueue, flush: () => running || Promise.resolve() };
    }

    const api = { readAll, latestWriter };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.WordevoData = api;
})(typeof window !== 'undefined' ? window : globalThis);
