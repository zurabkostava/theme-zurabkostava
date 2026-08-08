// Chrome API Mock for Standalone Web App
window.chrome = window.chrome || {};

// 1. Storage Mock (using localStorage with proper serialization)
window.chrome.storage = {
    onChanged: {
        listeners: [],
        addListener: function(callback) {
            this.listeners.push(callback);
        }
    },
    local: {
        get: function(keys, callback) {
            return new Promise((resolve) => {
                let result = {};
                
                const fetchKey = (key) => {
                    const val = localStorage.getItem('chromemock_' + key);
                    if (val !== null) {
                        try {
                            return JSON.parse(val);
                        } catch(e) {
                            return val;
                        }
                    }
                    return undefined;
                };

                if (typeof keys === 'string') {
                    const v = fetchKey(keys);
                    if (v !== undefined) result[keys] = v;
                } else if (Array.isArray(keys)) {
                    keys.forEach(key => {
                        const v = fetchKey(key);
                        if (v !== undefined) result[key] = v;
                    });
                } else if (keys === null) {
                    for (let i = 0; i < localStorage.length; i++) {
                        const key = localStorage.key(i);
                        if (key.startsWith('chromemock_')) {
                            const realKey = key.replace('chromemock_', '');
                            result[realKey] = fetchKey(realKey);
                        }
                    }
                } else if (typeof keys === 'object') {
                    for (const key in keys) {
                        const v = fetchKey(key);
                        result[key] = v !== undefined ? v : keys[key];
                    }
                }
                
                if (callback) callback(result);
                resolve(result);
            });
        },
        set: function(items, callback) {
            return new Promise((resolve) => {
                let changes = {};
                
                for (const key in items) {
                    const storageKey = 'chromemock_' + key;
                    const oldStr = localStorage.getItem(storageKey);
                    let oldVal = undefined;
                    if (oldStr !== null) {
                        try { oldVal = JSON.parse(oldStr); } catch(e) { oldVal = oldStr; }
                    }
                    
                    const newVal = items[key];
                    localStorage.setItem(storageKey, JSON.stringify(newVal));
                    
                    changes[key] = {
                        oldValue: oldVal,
                        newValue: newVal
                    };
                }
                
                // Trigger listeners
                window.chrome.storage.onChanged.listeners.forEach(listener => {
                    try { listener(changes, 'local'); } catch(e) { console.error(e); }
                });
                
                if (callback) callback();
                resolve();
            });
        },
        remove: function(keys, callback) {
            return new Promise((resolve) => {
                let changes = {};
                const keysArray = Array.isArray(keys) ? keys : [keys];
                
                keysArray.forEach(key => {
                    const storageKey = 'chromemock_' + key;
                    const oldStr = localStorage.getItem(storageKey);
                    if (oldStr !== null) {
                        let oldVal = undefined;
                        try { oldVal = JSON.parse(oldStr); } catch(e) { oldVal = oldStr; }
                        changes[key] = { oldValue: oldVal, newValue: undefined };
                        localStorage.removeItem(storageKey);
                    }
                });
                
                window.chrome.storage.onChanged.listeners.forEach(listener => {
                    try { listener(changes, 'local'); } catch(e) { console.error(e); }
                });
                
                if (callback) callback();
                resolve();
            });
        }
    }
};

// 2. Tabs Mock
window.chrome.tabs = {
    onActivated: { addListener: function() {} },
    onUpdated: { addListener: function() {} },
    query: function(queryInfo, callback) {
        const dummyTabs = [{ id: 1, active: true, url: window.location.href }];
        if (callback) callback(dummyTabs);
        return Promise.resolve(dummyTabs);
    },
    update: function(tabId, updateProperties, callback) {
        if (updateProperties && updateProperties.url) {
            console.warn("WebApp: Intercepted tab update to: " + updateProperties.url);
            window.open(updateProperties.url, '_blank');
        }
        if (callback) callback();
        return Promise.resolve();
    },
    create: function(createProperties, callback) {
        if (createProperties && createProperties.url) {
            console.warn("WebApp: Intercepted tab create to: " + createProperties.url);
            window.open(createProperties.url, '_blank');
        }
        if (callback) callback({ id: 2 });
        return Promise.resolve({ id: 2 });
    },
    sendMessage: function(tabId, message, callback) {
        console.warn("WebApp: Blocked attempt to send message to tab", message);
        alert("Bot Actions are not supported in the Standalone Web App! Use the Chrome Extension for automation.");
        if (callback) callback({ status: "error", error: "Not supported in WebApp" });
    }
};

// 3. Runtime Mock
window.chrome.runtime = {
    lastError: undefined,
    onMessage: { addListener: function() {} }
};
