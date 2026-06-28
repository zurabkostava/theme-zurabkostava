import os

js_path = 'WordEvo/script.js'
with open(js_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update renderCardFromData signature and append logic
old_func_def = "function renderCardFromData(data) {"
new_func_def = "function renderCardFromData(data, appendAndSort = true) {"
content = content.replace(old_func_def, new_func_def)

old_append_logic = """    addLongPressHandlers(card); // ეს უკვე შეიცავს preview-ს ლოგიკას
    document.getElementById('cardContainer').appendChild(card);
    sortCards();
}"""
new_append_logic = """    addLongPressHandlers(card); // ეს უკვე შეიცავს preview-ს ლოგიკას
    
    if (appendAndSort) {
        document.getElementById('cardContainer').appendChild(card);
        sortCards();
    }
    return card;
}"""
content = content.replace(old_append_logic, new_append_logic)

# 2. Update the bulk load loop in loadDictionaryData
old_bulk_loop = """// 5. ვხატავთ ბარათებს და ვავსებთ UI-ს
    cardsWithTags.forEach(card => {
        renderCardFromData(card);
    });"""
new_bulk_loop = """// 5. ვხატავთ ბარათებს და ვავსებთ UI-ს
    const fragment = document.createDocumentFragment();
    cardsWithTags.forEach(cardData => {
        const cardEl = renderCardFromData(cardData, false);
        fragment.appendChild(cardEl);
    });
    document.getElementById('cardContainer').appendChild(fragment);
    sortCards();"""
content = content.replace(old_bulk_loop, new_bulk_loop)

# 3. Update startApp
old_start_app = """    async function startApp() {
        console.log('[Wordevo] startApp: checking session...');
        try {
            const { data: { session } } = await supabaseClient.auth.getSession();
            console.log('[Wordevo] startApp: session:', !!session);
            if (session) {"""
new_start_app = """    async function startApp() {
        console.log('[Wordevo] startApp: checking session...');
        try {
            const { data: { session } } = await supabaseClient.auth.getSession();
            console.log('[Wordevo] startApp: session:', !!session);
            
            const loadingScreen = document.getElementById('globalLoadingScreen');
            if (loadingScreen) loadingScreen.classList.add('hidden');
            
            if (session) {"""
content = content.replace(old_start_app, new_start_app)

# 4. Update showAuthScreen
old_show_auth = """    function showAuthScreen() {
        mainAppContainer.style.display = 'none';
        authContainer.style.display = 'flex';"""
new_show_auth = """    function showAuthScreen() {
        const loadingScreen = document.getElementById('globalLoadingScreen');
        if (loadingScreen) loadingScreen.classList.add('hidden');
        
        mainAppContainer.style.display = 'none';
        authContainer.style.display = 'flex';"""
content = content.replace(old_show_auth, new_show_auth)

with open(js_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("Applied optimization refactoring to script.js")
