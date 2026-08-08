/* ---------------------------------------------------------
   INSTADISCOVERY SIDEKICK V6.0 (FINAL) 🚀
   --------------------------------------------------------- */

const SUPABASE_URL = 'https://ctvdtfwnwfusdmlbbnmr.supabase.co';
const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImN0dmR0Zndud2Z1c2RtbGJibm1yIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQxODE2MTgsImV4cCI6MjA3OTc1NzYxOH0.lIkrqpEk038DGnQw3jmrMCI5PmTee4vl2wkDNOVrLJA';

// Custom storage adapter using chrome.storage.local for persistent sessions
const chromeStorageAdapter = {
    getItem: (key) => new Promise((resolve) => {
        chrome.storage.local.get([key], (result) => {
            resolve(result[key] ?? null);
        });
    }),
    setItem: (key, value) => new Promise((resolve) => {
        chrome.storage.local.set({ [key]: value }, () => resolve());
    }),
    removeItem: (key) => new Promise((resolve) => {
        chrome.storage.local.remove(key, () => resolve());
    }),
};

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_KEY, {
    auth: {
        storage: chromeStorageAdapter,
        autoRefreshToken: true,
        persistSession: true,
        detectSessionInUrl: false,
    }
});
let currentMode = '';
let currentId = null;
window.choicesInstances = {};
function getMultiSelectValues(id) {
    const el = document.getElementById(id);
    if (!el) return [];
    if (window.choicesInstances[id]) {
        const val = window.choicesInstances[id].getValue(true);
        return Array.isArray(val) ? val : [val];
    }
    return Array.from(el.selectedOptions).map(o => o.value);
}
let currentSettingsTab = 'tags';
let APP_CONFIG = { tags: {}, locations: {}, people: {} };
let editingCategory = null;
let lastSelectedId = null; // დაიმახსოვრებს ბოლო დაჭერილ აითემს
let isDataLoading = false; // აიცილებს თავიდან ზედმეტ რეფრეშებს
let locSortBy = 'default'; // 'default' | 'name' | 'posts'
let locSortAsc = false;
let peopleSortBy = 'default'; // 'default' | 'name' | 'followers' | 'following'
let peopleSortAsc = false;
let savedBotFollowersRange = '8k-1M'; // 🤖 people followers range filter
let savedBotSwitchTime = '5-13'; // 🤖 seconds between post switches
let savedBotDateLimit = 'any'; // 🤖 Post age limit for skipping inactive people
let cachedPlaylists = { tags: [], locations: [], people: [] }; // ⭐ playlist cache
let pendingPlaylistItemId = null; // item id waiting for playlist assignment
let globalFavoritedItems = new Set(); // ⭐ tracks item IDs that are in ANY playlist
let _botStorageListenerRegistered = false; // prevent duplicate storage listeners
const _scrollHandlers = {}; // track scroll handlers per container to prevent leaks
let savedStoryFocusTime = '3-7';    // story bot: seconds per story
let savedStoryPerUser = '3-8';      // story bot: stories to view per user
let savedStoryLikeRatio = '6-10';   // story bot: likes per 10 stories
let savedStoryCount = '10-20';           // story bot: number of profiles
let savedStoryInnerSearch = false;
let _storyBotStorageListenerRegistered = false;
const DEFAULT_CONFIG = {
    tags: { "cyber": "🤖 Cyber", "urban": "🏛️ Urban", "cinema": "🎬 Cinema", "nature": "🌿 Nature", "aesthetic": "✨ Vibe" },
    locations: { "ge": "🇬🇪 Georgia", "us": "🇺🇸 USA", "it": "🇮🇹 Italy", "fr": "🇫🇷 France", "jp": "🇯🇵 Japan" },
    people: { "art": "🎨 Art", "tech": "💻 Tech", "other": "🎲 Other" }
};

function parsePostCount(val) {
    if (!val) return 0;
    const str = String(val).toUpperCase().replace(/,/g, '');
    const match = str.match(/^([0-9.]+)\s*([KMB]?)$/);
    if (!match) return 0;
    const num = parseFloat(match[1]);
    const suffix = match[2];
    if (suffix === 'K') return num * 1000;
    if (suffix === 'M') return num * 1000000;
    if (suffix === 'B') return num * 1000000000;
    return num;
}

// 🟢 INITIALIZATION
document.addEventListener('DOMContentLoaded', initApp);
let currentPlatform = 'instagram'; // გლობალური ცვლადი (default)
async function initApp() {
    // 🎯 სტატუსების ამოღება (Sniper და Comments)
    const res = await chrome.storage.local.get(null);
    
    if (res.auto_bot_only_keywords !== undefined) savedBotOnlyKeywords = res.auto_bot_only_keywords;
    if (res.auto_bot_carousel_range !== undefined) savedBotCarouselRange = Array.isArray(res.auto_bot_carousel_range) ? res.auto_bot_carousel_range.join('-') : res.auto_bot_carousel_range;
    if (res.auto_bot_like_comments_active !== undefined) savedBotLikeComments = res.auto_bot_like_comments_active;
    if (res.auto_bot_like_comments_ratio !== undefined) savedBotLikeCommentsRatio = Array.isArray(res.auto_bot_like_comments_ratio) ? res.auto_bot_like_comments_ratio.join('-') : res.auto_bot_like_comments_ratio;
    if (res.auto_bot_like_comments_amount !== undefined) savedBotLikeCommentsAmount = Array.isArray(res.auto_bot_like_comments_amount) ? res.auto_bot_like_comments_amount.join('-') : res.auto_bot_like_comments_amount;
    if (res.auto_bot_inner_search !== undefined) savedBotInnerSearch = res.auto_bot_inner_search;
    
    if (res.auto_bot_comment_active !== undefined) savedBotCommentActive = res.auto_bot_comment_active;
    if (res.auto_bot_comment_ratio !== undefined) savedBotCommentRatio = res.auto_bot_comment_ratio;
    if (res.auto_bot_comment_only_liked !== undefined) savedBotCommentOnlyLiked = res.auto_bot_comment_only_liked;
    if (res.auto_bot_comment_list !== undefined) savedBotCommentList = res.auto_bot_comment_list.replace(/\\n/g, '\n');
    if (res.auto_bot_date_limit !== undefined) savedBotDateLimit = res.auto_bot_date_limit;
    if (res.auto_story_bot_inner_search !== undefined) savedStoryInnerSearch = res.auto_story_bot_inner_search;

    // 🛑 [BUG FIX] Restoring dynamic inputs from UI auto-save (so users don't lose data on reload)
    if (res.ui_persist_botProfileCount !== undefined) savedBotCount = res.ui_persist_botProfileCount;
    if (res.ui_persist_botRestMinutes !== undefined) savedBotRest = res.ui_persist_botRestMinutes;
    if (res.ui_persist_botViewsRange !== undefined) savedBotViews = res.ui_persist_botViewsRange;
    if (res.ui_persist_botLikesRange !== undefined) savedBotLikes = res.ui_persist_botLikesRange;
    if (res.ui_persist_botSwitchTime !== undefined) savedBotSwitchTime = res.ui_persist_botSwitchTime;
    if (res.ui_persist_botPostsRange !== undefined) savedBotPostsRange = res.ui_persist_botPostsRange;
    if (res.ui_persist_botFollowersRange !== undefined) savedBotFollowersRange = res.ui_persist_botFollowersRange;
    if (res.ui_persist_botRecentCheck !== undefined) savedBotRecent = res.ui_persist_botRecentCheck;
    if (res.ui_persist_botTagKeywordCheck !== undefined) savedBotTagKeyword = res.ui_persist_botTagKeywordCheck;
    if (res.ui_persist_botDeepDiveEnabled !== undefined) savedBotDeepDiveEnabled = res.ui_persist_botDeepDiveEnabled;
    if (res.ui_persist_botDeepDiveRange !== undefined) savedBotDeepDiveRange = res.ui_persist_botDeepDiveRange;
    if (res.ui_persist_botKeywords !== undefined) savedBotKeywords = res.ui_persist_botKeywords;
    
    if (res.ui_persist_storyFocusTime !== undefined) savedStoryFocusTime = res.ui_persist_storyFocusTime;
    if (res.ui_persist_storyPerUser !== undefined) savedStoryPerUser = res.ui_persist_storyPerUser;
    if (res.ui_persist_storyLikeRatio !== undefined) savedStoryLikeRatio = res.ui_persist_storyLikeRatio;
    if (res.ui_persist_storyBotCount !== undefined) savedStoryCount = res.ui_persist_storyBotCount;
    console.log("🚀 Sidekick V6.0 Loaded");
    await initAuthWidget();
    setupGlobalListeners();
    await loadAppConfig();

    // 🛑 დაემატა: რაოდენობების განახლება სტარტზევე
    await updateGlobalCounts();

    injectResultModalListeners();

    // 🛑 PLATFORM BUTTON LISTENERS (ახალი)
    document.querySelectorAll('.plat-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            currentPlatform = btn.dataset.platform; // ვცვლით პლატფორმას
            updatePlatformUI(true); // true = ხელით გადართული (Force Update)
        });
    });

    const autoNavCheck = document.getElementById('autoNavCheck');
    if(autoNavCheck) {
        const saved = localStorage.getItem('insta_auto_nav');
        if (saved === 'true') autoNavCheck.checked = true;
        autoNavCheck.addEventListener('change', () => localStorage.setItem('insta_auto_nav', autoNavCheck.checked));
    }
    const autoAddCheck = document.getElementById('autoAddCheck');
    if(autoAddCheck) {
        chrome.storage.local.get(['auto_add_other', 'auto_add_blacklist', 'auto_add_range_enabled', 'auto_add_range_min', 'auto_add_range_max'], (result) => {
            if (result.auto_add_other) autoAddCheck.checked = true;
            const bl = document.getElementById('autoAddBlacklist');
            if (bl && result.auto_add_blacklist) bl.value = result.auto_add_blacklist.join('\n');
            const rangeCheck = document.getElementById('autoAddRangeCheck');
            if (rangeCheck) rangeCheck.checked = !!result.auto_add_range_enabled;
            const rangeMin = document.getElementById('autoAddRangeMin');
            if (rangeMin && result.auto_add_range_min) rangeMin.value = result.auto_add_range_min;
            const rangeMax = document.getElementById('autoAddRangeMax');
            if (rangeMax && result.auto_add_range_max) rangeMax.value = result.auto_add_range_max;
            const apiScraperCheck = document.getElementById('apiScraperCheck');
            if (apiScraperCheck && result.api_scraper_enabled) apiScraperCheck.checked = true;
        });


        autoAddCheck.addEventListener('change', () => {
            chrome.storage.local.set({ auto_add_other: autoAddCheck.checked });
        });
    }
    const btnSaveAutoAdd = document.getElementById('btnSaveAutoAddSettings');

    if(btnSaveAutoAdd) {

        btnSaveAutoAdd.addEventListener('click', () => {
            const bl = document.getElementById('autoAddBlacklist');
            const lines = bl ? bl.value.split('\n').map(l => l.trim().toLowerCase()).filter(l => l.length > 0) : [];
            const rangeEnabled = !!document.getElementById('autoAddRangeCheck')?.checked;
            const rangeMinVal = (document.getElementById('autoAddRangeMin')?.value || '').trim();
            const rangeMaxVal = (document.getElementById('autoAddRangeMax')?.value || '').trim();
            const apiScraperEnabled = !!document.getElementById('apiScraperCheck')?.checked; // 🛑 ახალი ცვლადი

            chrome.storage.local.set({
                auto_add_blacklist: lines,
                auto_add_range_enabled: rangeEnabled,
                auto_add_range_min: rangeMinVal,
                auto_add_range_max: rangeMaxVal,
                api_scraper_enabled: apiScraperEnabled // 🛑 ვინახავთ ლოკალურად
            }, () => {
                showToast('Auto Add settings saved!', 'success');
            });
        });
    }
    updatePlatformUI();
    chrome.tabs.onActivated.addListener(() => updatePlatformUI());
    chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
        if (changeInfo.status === 'complete') updatePlatformUI();
    });
}
// 🟢 CONFIG & SYNC
async function loadAppConfig() {
    const { data: { session } } = await supabaseClient.auth.getSession();
    if (!session) {
        APP_CONFIG = JSON.parse(JSON.stringify(DEFAULT_CONFIG));
    } else {
        try {
            const { data } = await supabaseClient.from('app_categories').select('*').eq('user_id', session.user.id);
            if (!data || data.length === 0) {
                await seedCategories(session.user.id);
                return;
            }
            APP_CONFIG = { tags: {}, locations: {}, people: {} };
            data.forEach(item => {
                if(APP_CONFIG[item.section] !== undefined) APP_CONFIG[item.section][item.key_val] = item.label;
            });
        } catch (err) { console.error(err); }
    }
    refreshAllDropdowns();
    chrome.storage.local.set({ app_config: APP_CONFIG, supabase_session: session });
}

function refreshAllDropdowns() {
    populateSelect('tagCategory', APP_CONFIG.tags, '🎲 Mix All');
    populateSelect('locCategory', APP_CONFIG.locations, '🌍 Global');
    populateSelect('personCategory', APP_CONFIG.people, '👥 All');
}

async function seedCategories(userId) {
    let rows = [];
    for (let section in DEFAULT_CONFIG) {
        for (let key in DEFAULT_CONFIG[section]) {
            rows.push({ user_id: userId, section: section, key_val: key, label: DEFAULT_CONFIG[section][key] });
        }
    }
    await supabaseClient.from('app_categories').insert(rows);
    await loadAppConfig();
}

// 🟢 EVENT LISTENERS
function setupGlobalListeners() {
    const bindClick = (id, handler) => { const el = document.getElementById(id); if(el) el.addEventListener('click', handler); };

    bindClick('btnTag', () => handleSearch('tag'));
    bindClick('btnLoc', () => handleSearch('loc'));
    bindClick('btnPerson', () => handleSearch('person'));

    bindClick('adminBtn', async () => {
        const { data: { session } } = await supabaseClient.auth.getSession();
        if (session) {
            document.getElementById('adminModal').classList.remove('hidden');
            switchTab('tags');
            await loadAppConfig();
            updateGlobalCounts();

            // 🛑 👇 აი, აქ ვეძახით ჩვენს სტატისტიკას!
            

        } else {
            document.getElementById('loginModal').classList.remove('hidden');
        }
    });

    bindClick('closeAdmin', () => document.getElementById('adminModal').classList.add('hidden'));

    // Settings
    bindClick('btnOpenSettings', () => {
        document.querySelector('.admin-tabs').style.display = 'none';
        document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('panelSettings').classList.remove('hidden');
        document.getElementById('panelSettings').classList.add('active');
        loadCatEditor('tags');
    });

    bindClick('btnBackToData', () => {
        document.getElementById('panelSettings').classList.remove('active');
        document.querySelector('.admin-tabs').style.display = 'flex';
        switchTab('tags');
    });

    bindClick('btnAddCategory', addNewCategory);

    // Tab Switching
    document.querySelectorAll('.admin-tabs:not(#settingsTabs) .tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if(btn.dataset.target === 'panelTags') switchTab('tags');
            if(btn.dataset.target === 'panelLocs') switchTab('locations');
            if(btn.dataset.target === 'panelPeople') switchTab('people');
        });
    });

    document.querySelectorAll('#settingsTabs .tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#settingsTabs .tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            loadCatEditor(btn.dataset.section);
        });
    });

    // Inputs
    const bindChange = (id, handler) => document.getElementById(id)?.addEventListener('change', handler);
    const bindInput = (id, handler) => document.getElementById(id)?.addEventListener('input', handler);

    bindChange('adminTagFilter', () => loadAdminData('tags'));
    bindChange('adminLocFilter', () => loadAdminData('locations'));
    bindChange('adminPersonFilter', () => loadAdminData('people'));
    bindChange('playlistTagFilter', () => handlePlaylistChange('tags'));
    bindChange('playlistLocFilter', () => handlePlaylistChange('locations'));
    bindChange('playlistPersonFilter', () => handlePlaylistChange('people'));
    bindInput('searchTags', () => loadAdminData('tags'));
    bindInput('searchLocs', () => loadAdminData('locations'));
    bindInput('searchPeople', () => loadAdminData('people'));

    bindClick('btnSaveInput', saveItem);
    bindClick('btnCancelInput', () => document.getElementById('inputModal').classList.add('hidden'));

    // ⭐ Playlist modals
    bindClick('btnPlaylistClose', () => document.getElementById('playlistModal').classList.add('hidden'));
    bindClick('btnPlaylistManageClose', () => document.getElementById('playlistManageModal').classList.add('hidden'));
    bindClick('btnCreatePlaylist', createPlaylistFromModal);

    // ⭐ Right-click playlist dropdown to manage
    ['playlistTagFilter', 'playlistLocFilter', 'playlistPersonFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const section = id.includes('Tag') ? 'tags' : id.includes('Loc') ? 'locations' : 'people';
            openPlaylistManager(section);
        });
    });

    // Load playlists on init
    loadAllPlaylists();
    bindClick('btnBulkDeleteAction', executeBulkDelete);
    bindClick('btnBulkMoveAction', executeBulkMove);

    // CSV
    document.querySelectorAll('.btn-csv').forEach(btn => {
        if(btn.innerHTML.includes('fa-file-import')) btn.addEventListener('click', () => document.getElementById('csvFileInput').click());
        if(btn.innerHTML.includes('fa-file-excel')) btn.addEventListener('click', exportExcel);
    });
    bindChange('csvFileInput', (e) => importXLSX(e.target));

    // Sort Buttons (Locations)
    document.querySelectorAll('.btn-sort').forEach(btn => {
        btn.addEventListener('click', () => {
            const sort = btn.dataset.sort;
            if (locSortBy === sort && sort !== 'default') {
                locSortAsc = !locSortAsc;
            } else {
                locSortBy = sort;
                locSortAsc = sort === 'name';
            }
            document.querySelectorAll('.btn-sort').forEach(b => { b.classList.remove('active', 'asc'); });
            btn.classList.add('active');
            if (locSortAsc) btn.classList.add('asc');
            loadAdminData('locations');
        });
    });

    // Sort Buttons (People)
    document.querySelectorAll('.btn-sort-people').forEach(btn => {
        btn.addEventListener('click', () => {
            const sort = btn.dataset.sort;
            if (peopleSortBy === sort && sort !== 'default') {
                peopleSortAsc = !peopleSortAsc;
            } else {
                peopleSortBy = sort;
                peopleSortAsc = sort === 'name';
            }
            document.querySelectorAll('.btn-sort-people').forEach(b => { b.classList.remove('active', 'asc'); });
            btn.classList.add('active');
            if (peopleSortAsc) btn.classList.add('asc');
            loadAdminData('people');
        });
    });

    // Add Buttons
    document.querySelectorAll('.admin-btn-add').forEach(btn => {
        if(btn.id !== 'btnAddCategory') {
            btn.addEventListener('click', () => {
                currentMode = 'add';
                document.getElementById('inputTitle').innerText = 'Add New';
                document.getElementById('inputName').value = '';
                document.getElementById('inputCount').value = '';
                document.getElementById('inputID').value = '';

                const idWrap = document.getElementById('idFieldWrapper');
                const countWrap = document.getElementById('countFieldWrapper');
                const catWrap = document.getElementById('categoryFieldWrapper');

                const followWrap = document.getElementById('followersFieldWrapper');

                if (currentTable === 'locations') {
                    if(idWrap) idWrap.classList.remove('hidden');
                    if(countWrap) countWrap.classList.remove('hidden');
                    if(followWrap) followWrap.classList.add('hidden');
                } else if (currentTable === 'people') {
                    if(idWrap) idWrap.classList.remove('hidden');
                    if(countWrap) countWrap.classList.add('hidden');
                    if(followWrap) followWrap.classList.remove('hidden');
                } else {
                    if(idWrap) idWrap.classList.add('hidden');
                    if(countWrap) countWrap.classList.add('hidden');
                    if(followWrap) followWrap.classList.add('hidden');
                }
                if(catWrap) catWrap.classList.add('hidden');
                document.getElementById('inputFollowers').value = '';
                document.getElementById('inputFollowing').value = '';
                document.getElementById('inputModal').classList.remove('hidden');
            });
        }
    });

    // Master Checkbox
    document.querySelectorAll('.master-checkbox').forEach(cb => {
        cb.addEventListener('change', (e) => {
            const activePanel = document.querySelector('.admin-panel.active');
            if(activePanel) {
                activePanel.querySelectorAll('.item-checkbox').forEach(box => box.checked = e.target.checked);
                handleSelection();
            }
        });
    });
// 🛑 მოსმენა Content Script-იდან
    chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
        if (request.action === "refresh_counts") {
            console.log("🔄 Syncing counts from external action...");
            updateGlobalCounts();
            loadAdminData(currentTable); // ცხრილიც განვაახლოთ თუ ღიაა
        }
    });

    // 🛑 დაემატა: Keyword Mode-ის გადართვაზე UI-ს განახლება
    document.getElementById('noHashCheck')?.addEventListener('change', updatePlatformUI);
}
async function updateGlobalCounts() {
    // 1. სესიის შემოწმება
    const { data: { session } } = await supabaseClient.auth.getSession();
    if (!session) {
        // თუ არ არის ავტორიზებული, განულდეს
        ['badgeTags', 'badgeLocs', 'badgePeople'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.innerText = '0';
        });
        return;
    }

    // 2. დამხმარე ფუნქცია დათვლისთვის
    const getCount = async (table) => {
        try {
            let query = supabaseClient
                .from(table)
                .select('*', { count: 'exact', head: true })
                .eq('user_id', session.user.id);

            // 🛑 ფილტრაცია პლატფორმის მიხედვით (ჯერ მხოლოდ People-ზე)
            if (table === 'people') query = query.eq('platform', currentPlatform);

            const { count, error } = await query;
            if (error) throw error;
            return count || 0;
        } catch (e) {
            console.error(`Error counting ${table}:`, e);
            return 0;
        }
    };

    // 3. პარალელური მოთხოვნა სისწრაფისთვის
    const [tags, locs, ppl] = await Promise.all([
        getCount('tags'),
        getCount('locations'),
        getCount('people')
    ]);

    // 4. UI-ის განახლება
    const elTags = document.getElementById('badgeTags');
    const elLocs = document.getElementById('badgeLocs');
    const elPpl = document.getElementById('badgePeople');

    if (elTags) elTags.innerText = tags;
    if (elLocs) elLocs.innerText = locs;
    if (elPpl) elPpl.innerText = ppl;
}
function switchTab(table) {
    currentTable = table;
    document.querySelectorAll('.admin-tabs:not(#settingsTabs) .tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));

    let targetPanel = table === 'tags' ? 'panelTags' : table === 'locations' ? 'panelLocs' : 'panelPeople';
    const activeBtn = Array.from(document.querySelectorAll('.admin-tabs:not(#settingsTabs) .tab-btn')).find(b => b.dataset.target === targetPanel);
    if(activeBtn) activeBtn.classList.add('active');
    document.getElementById(targetPanel).classList.add('active');

    let filterId = table === 'tags' ? 'adminTagFilter' : table === 'locations' ? 'adminLocFilter' : 'adminPersonFilter';
    populateSelect(filterId, APP_CONFIG[table], 'All');
    loadAdminData(table);
}




// 🟢 DATA LOADING & ACTIONS
function populateSelect(id, data, label) {
    const sel = document.getElementById(id);
    if(!sel) return;
    sel.innerHTML = `<option value="all">${label}</option>`;
    if(data) Object.entries(data).sort((a,b)=>a[1].localeCompare(b[1])).forEach(([k,v]) => sel.innerHTML += `<option value="${k}">${v}</option>`);
}

async function loadAdminData(table) {
    const container = document.getElementById(table === 'tags' ? 'listTags' : table === 'locations' ? 'listLocs' : 'listPeople');
    if(!container || isDataLoading) return;

    // 🛑 1. ვიმახსოვრებთ სქროლს და მონიშნულ ჩეკბოქსებს
    const savedScrollTop = container.scrollTop;
    const checkedIds = Array.from(container.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);

    isDataLoading = true;

    const { data: { session } } = await supabaseClient.auth.getSession();
    if(!session) { isDataLoading = false; return; }

    let selectId = (table === 'tags') ? 'adminTagFilter' : (table === 'locations') ? 'adminLocFilter' : 'adminPersonFilter';
    let searchId = (table === 'tags') ? 'searchTags' : (table === 'locations') ? 'searchLocs' : 'searchPeople';
    const filterVals = getMultiSelectValues(selectId);
    const searchVal = document.getElementById(searchId).value.trim();

    const playlistFilterId = table === 'tags' ? 'playlistTagFilter' : table === 'locations' ? 'playlistLocFilter' : 'playlistPersonFilter';
    const selectedPlaylist = document.getElementById(playlistFilterId)?.value;
    let allowedIds = null;
    if (selectedPlaylist) {
        const { data: plItems } = await supabaseClient.from('playlist_items').select('item_id').eq('playlist_id', selectedPlaylist);
        if (plItems && plItems.length > 0) allowedIds = plItems.map(p => p.item_id);
        else allowedIds = []; // Empty playlist
    }

    let countQuery = supabaseClient.from(table).select('id', { count: 'exact', head: true }).eq('user_id', session.user.id);
    if (table === 'people') countQuery = countQuery.eq('platform', currentPlatform);
    if (!filterVals.includes('all') && filterVals.length > 0) countQuery = countQuery.in(table === 'locations' ? 'country_code' : 'category', filterVals);
    if (searchVal) countQuery = countQuery.ilike(table === 'people' ? 'username' : 'name', `%${searchVal}%`);
    if (allowedIds !== null) countQuery = countQuery.in('id', allowedIds);

    const { count: totalCount } = await countQuery;
    const countSpan = document.getElementById(table === 'tags' ? 'countTags' : table === 'locations' ? 'countLocs' : 'countPeople');
    if (countSpan) countSpan.innerText = totalCount || 0;

    let activeSortBy = table === 'locations' ? locSortBy : table === 'people' ? peopleSortBy : 'default';

    let baseQuery = () => {
        let q = supabaseClient.from(table).select('*').eq('user_id', session.user.id);
        if (table === 'people') q = q.eq('platform', currentPlatform);
        if (!filterVals.includes('all') && filterVals.length > 0) q = q.in(table === 'locations' ? 'country_code' : 'category', filterVals);
        if (searchVal) q = q.ilike(table === 'people' ? 'username' : 'name', `%${searchVal}%`);
        if (allowedIds !== null) q = q.in('id', allowedIds);
        return q;
    };

    let data = [];
    const PAGE_SIZE = 200;
    let isFullyLoaded = false;

    if (activeSortBy !== 'default') {
        const FETCH_SIZE = 1000;
        const pages = Math.ceil(totalCount / FETCH_SIZE);
        const promises = [];
        for (let i = 0; i < pages; i++) {
            const from = i * FETCH_SIZE;
            promises.push(baseQuery().order('id', { ascending: false }).range(from, from + FETCH_SIZE - 1));
        }
        const results = await Promise.all(promises);
        results.forEach(res => {
            if (res.data) data = data.concat(res.data);
        });
        if (table === 'locations') {
            data.sort((a, b) => {
                let cmp = (locSortBy === 'name') ? (a.name || '').localeCompare(b.name || '') : parsePostCount(a.posts_count) - parsePostCount(b.posts_count);
                return locSortAsc ? cmp : -cmp;
            });
        } else if (table === 'people') {
            data.sort((a, b) => {
                let cmp = 0;
                if (peopleSortBy === 'name') cmp = (a.name || a.username || '').localeCompare(b.name || b.username || '');
                else if (peopleSortBy === 'followers') cmp = parsePostCount(a.followers) - parsePostCount(b.followers);
                else if (peopleSortBy === 'following') cmp = parsePostCount(a.following) - parsePostCount(b.following);
                return peopleSortAsc ? cmp : -cmp;
            });
        }
        isFullyLoaded = true;
    } else {
        const { data: batch } = await baseQuery().order('id', { ascending: false }).range(0, PAGE_SIZE - 1);
        if (batch) data = batch;
    }

    function renderRowHtml(item) {
        const name = item.name || item.username;
        const catKey = item.category || item.country_code;
        const catLabel = (APP_CONFIG[table] && APP_CONFIG[table][catKey]) ? APP_CONFIG[table][catKey] : catKey;
        const isActive = (lastSelectedId == item.id) ? 'selected-row' : '';
        const isChecked = checkedIds.includes(String(item.id)) ? 'checked' : '';
        const postsBadge = (table === 'locations' && item.posts_count) ? `<span class="posts-badge">${item.posts_count}</span>` : '';
        const followGroup = (table === 'people' && (item.followers || item.following)) ? `<div class="follow-group">${item.followers ? `<span class="followers-badge">${item.followers}</span>` : ''}${item.following ? `<span class="following-badge">${item.following}</span>` : ''}</div>` : '';
        return `<div class="db-row ${isActive}" data-id="${item.id}">
                    <input type="checkbox" class="item-checkbox" value="${item.id}" ${isChecked}>
                    ${postsBadge}${followGroup}
                    <div class="db-info">
                        <b>${name}</b>
                        <span>${catLabel}</span>
                    </div>
                    <div class="db-actions">
                        <button class="btn-fav-item" data-id="${item.id}" title="Add to playlist">${globalFavoritedItems.has(item.id) ? '<i class="fa-solid fa-star" style="color:#FFD700;"></i>' : '<i class="fa-regular fa-star"></i>'}</button>
                        <button class="btn-edit" data-id="${item.id}" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <button class="btn-del" data-id="${item.id}" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                    </div>
                </div>`;
    }

    let htmlContent = '';
    let renderedCount = 0;
    if (!data || data.length === 0) {
        htmlContent = '<div style="padding:20px; text-align:center; opacity:0.5;">Empty</div>';
    } else {
        const firstBatch = isFullyLoaded ? data.slice(0, PAGE_SIZE) : data;
        renderedCount = firstBatch.length;
        firstBatch.forEach(item => { htmlContent += renderRowHtml(item); });
    }
container.innerHTML = htmlContent;
    container.scrollTop = savedScrollTop;
    isDataLoading = false;

    // 💾 უნივერსალური Auto-Save სისტემა (ინახავს და აღადგენს ყველა ველს)
    chrome.storage.local.get(null, (res) => {
        container.querySelectorAll('.bot-input, .glass-checkbox, .story-bot-input, select.bot-input').forEach(inp => {
            if (!inp.id) return;

            // 1. მონაცემის აღდგენა რეფრეშისას
            const savedVal = res['ui_state_' + inp.id];
            if (savedVal !== undefined) {
                if (inp.type === 'checkbox') inp.checked = savedVal;
                else inp.value = savedVal;
            }

            // 2. მონაცემის შენახვა შეცვლისთანავე
            inp.addEventListener('input', (e) => {
                const val = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
                chrome.storage.local.set({ ['ui_state_' + e.target.id]: val });
            });
        });
    });

    // 📜 Infinite scroll — render next batch on scroll
    // Remove old scroll handler to prevent stale data from previous loads leaking in
    if (_scrollHandlers[container.id]) {
        container.removeEventListener('scroll', _scrollHandlers[container.id]);
        delete _scrollHandlers[container.id];
    }
    if (totalCount && renderedCount < totalCount) {
        let isLoadingMore = false;
        const onScroll = async function() {
            if (isLoadingMore) return;
            if (container.scrollTop + container.clientHeight >= container.scrollHeight - 100) {
                isLoadingMore = true;
                let nextBatch = [];
                if (isFullyLoaded) {
                    nextBatch = data.slice(renderedCount, renderedCount + PAGE_SIZE);
                } else {
                    const { data: batch } = await baseQuery().order('id', { ascending: false }).range(renderedCount, renderedCount + PAGE_SIZE - 1);
                    if (batch) {
                        nextBatch = batch;
                        data = data.concat(batch);
                    }
                }
                if (nextBatch && nextBatch.length > 0) {
                    const fragment = document.createRange().createContextualFragment(nextBatch.map(renderRowHtml).join(''));
                    container.appendChild(fragment);
                    renderedCount += nextBatch.length;
                }
                if (renderedCount >= totalCount || !nextBatch || nextBatch.length === 0) { 
                    container.removeEventListener('scroll', onScroll); 
                    delete _scrollHandlers[container.id]; 
                }
                isLoadingMore = false;
            }
        }
        _scrollHandlers[container.id] = onScroll;
        container.addEventListener('scroll', onScroll);
    }

    const countInput = container.querySelector('#botProfileCount');
    const restInput = container.querySelector('#botRestMinutes');
    const viewsInput = container.querySelector('#botViewsRange');
    const likesInput = container.querySelector('#botLikesRange');
    const recentCheck = container.querySelector('#botRecentCheck');
    const btnStart = container.querySelector('#btnStartBot');
    const onlyKwCheck = container.querySelector('#botOnlyKeywords');
    const btnStop = container.querySelector('#btnStopBot');

    const postsRangeInput = container.querySelector('#botPostsRange');
    const followersRangeInput = container.querySelector('#botFollowersRange');
    const switchTimeInput = container.querySelector('#botSwitchTime');
    const carouselInput = container.querySelector('#botCarouselRange');
    const innerSearchCheck = container.querySelector('#botInnerSearchCheck');
    
    if (carouselInput) {
        carouselInput.addEventListener('input', (e) => {
            savedBotCarouselRange = e.target.value;
            // 👈 ეგრევე ვინახავთ მეხსიერებაში, რომ რეფრეშმა არ წაშალოს!
            chrome.storage.local.set({ auto_bot_carousel_range: e.target.value });
        });
    }
    if (countInput) countInput.addEventListener('input', (e) => savedBotCount = e.target.value);
    if (restInput) restInput.addEventListener('input', (e) => savedBotRest = e.target.value);
    if (viewsInput) viewsInput.addEventListener('input', (e) => savedBotViews = e.target.value);
    if (likesInput) likesInput.addEventListener('input', (e) => savedBotLikes = e.target.value);
    if (recentCheck) recentCheck.addEventListener('change', (e) => savedBotRecent = e.target.checked);
    const tagKeywordCheck = container.querySelector('#botTagKeywordCheck');
    if (tagKeywordCheck) tagKeywordCheck.addEventListener('change', (e) => savedBotTagKeyword = e.target.checked);
    if (innerSearchCheck) innerSearchCheck.addEventListener('change', (e) => savedBotInnerSearch = e.target.checked);
    if (onlyKwCheck) {
        onlyKwCheck.addEventListener('change', (e) => {
            savedBotOnlyKeywords = e.target.checked;
            chrome.storage.local.set({ auto_bot_only_keywords: e.target.checked });
        });
    }
    if (postsRangeInput) postsRangeInput.addEventListener('input', (e) => savedBotPostsRange = e.target.value);
    if (followersRangeInput) followersRangeInput.addEventListener('input', (e) => savedBotFollowersRange = e.target.value);
    if (switchTimeInput) switchTimeInput.addEventListener('input', (e) => savedBotSwitchTime = e.target.value);
    
    const dateLimitSelect = container.querySelector('#botDateLimitSelect');
    if (dateLimitSelect) {
        dateLimitSelect.addEventListener('change', (e) => {
            savedBotDateLimit = e.target.value;
            chrome.storage.local.set({ auto_bot_date_limit: e.target.value });
        });
    }
    
    const botDateLimitDrop = container.querySelector('#botDateLimit');
    if (botDateLimitDrop) {
        botDateLimitDrop.addEventListener('change', (e) => {
            savedBotDateLimit = e.target.value;
            chrome.storage.local.set({ auto_bot_date_limit: e.target.value });
        });
    }

    // Story Bot input listeners
    const storyFocusInput = container.querySelector('#storyFocusTime');
    const storyPerUserInput = container.querySelector('#storyPerUser');
    const storyLikeRatioInput = container.querySelector('#storyLikeRatio');
    const storyBotCountInput = container.querySelector('#storyBotCount');
    const btnStartStory = container.querySelector('#btnStartStoryBot');
    const btnContinueStory = container.querySelector('#btnContinueStoryBot');
    const btnStopStory = container.querySelector('#btnStopStoryBot');
    const storyBotStatusEl = container.querySelector('.story-bot-status-line');

    if (storyFocusInput) storyFocusInput.addEventListener('input', (e) => savedStoryFocusTime = e.target.value);
    if (storyPerUserInput) storyPerUserInput.addEventListener('input', (e) => savedStoryPerUser = e.target.value);
    if (storyLikeRatioInput) storyLikeRatioInput.addEventListener('input', (e) => savedStoryLikeRatio = e.target.value);
    if (storyBotCountInput) storyBotCountInput.addEventListener('input', (e) => savedStoryCount = e.target.value);
    const storyInnerSearchCheck = container.querySelector('#storyBotInnerSearchCheck');
    if (storyInnerSearchCheck) {
        storyInnerSearchCheck.addEventListener('change', async (e) => {
            savedStoryInnerSearch = e.target.checked;
            await chrome.storage.local.set({ auto_story_bot_inner_search: savedStoryInnerSearch });
        });
    }

    // Bot status line element
    const botStatusEl = container.querySelector('.bot-status-line');

    // Bot panel collapse logic
    const botPanelHeader = container.querySelector('.bot-panel-header');
    const botPanelContent = container.querySelector('.bot-panel-content');
    const botPanelToggle = container.querySelector('.bot-panel-toggle');
    if (botPanelHeader && botPanelContent && botPanelToggle) {
        chrome.storage.local.get(['bot_panel_collapsed'], (res) => {
            if (res.bot_panel_collapsed) {
                botPanelContent.style.display = 'none';
                botPanelToggle.classList.remove('fa-chevron-up');
                botPanelToggle.classList.add('fa-chevron-down');
            }
        });
        botPanelHeader.onclick = (e) => {
            if (e.target.closest('input, button, textarea, .glass-checkbox')) return;
            const isCollapsed = botPanelContent.style.display === 'none';
            if (isCollapsed) {
                botPanelContent.style.display = 'flex';
                botPanelToggle.classList.remove('fa-chevron-down');
                botPanelToggle.classList.add('fa-chevron-up');
                chrome.storage.local.set({ bot_panel_collapsed: false });
            } else {
                botPanelContent.style.display = 'none';
                botPanelToggle.classList.remove('fa-chevron-up');
                botPanelToggle.classList.add('fa-chevron-down');
                chrome.storage.local.set({ bot_panel_collapsed: true });
            }
        };
    }

    const storyBotPanelHeader = container.querySelector('.story-bot-panel-header');
    const storyBotPanelContent = container.querySelector('.story-bot-panel-content');
    const storyBotPanelToggle = container.querySelector('.story-bot-panel-toggle');
    if (storyBotPanelHeader && storyBotPanelContent && storyBotPanelToggle) {
        chrome.storage.local.get(['story_bot_panel_collapsed'], (res) => {
            if (res.story_bot_panel_collapsed) {
                storyBotPanelContent.style.display = 'none';
                storyBotPanelToggle.classList.remove('fa-chevron-up');
                storyBotPanelToggle.classList.add('fa-chevron-down');
            }
        });
        storyBotPanelHeader.onclick = (e) => {
            if (e.target.closest('input, button, textarea, .glass-checkbox')) return;
            const isCollapsed = storyBotPanelContent.style.display === 'none';
            if (isCollapsed) {
                storyBotPanelContent.style.display = 'flex';
                storyBotPanelToggle.classList.remove('fa-chevron-down');
                storyBotPanelToggle.classList.add('fa-chevron-up');
                chrome.storage.local.set({ story_bot_panel_collapsed: false });
            } else {
                storyBotPanelContent.style.display = 'none';
                storyBotPanelToggle.classList.remove('fa-chevron-up');
                storyBotPanelToggle.classList.add('fa-chevron-down');
                chrome.storage.local.set({ story_bot_panel_collapsed: true });
            }
        };
    }

    // Helper: format elapsed time
    function formatElapsed(ms) {
        const s = Math.floor(ms / 1000);
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) return `${h}h ${m}m ${sec}s`;
        if (m > 0) return `${m}m ${sec}s`;
        return `${sec}s`;
    }

    // Helper: format time as HH:MM
    function formatTime(ts) {
        const d = new Date(ts);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    let botElapsedInterval = null;

    function startBotElapsedTimer(startTime) {
        if (botElapsedInterval) clearInterval(botElapsedInterval);
        const updateStatus = () => {
            if (!botStatusEl) return;
            const elapsed = Date.now() - startTime;
            botStatusEl.innerHTML = `<i class="fa-regular fa-clock"></i> Started ${formatTime(startTime)} <span class="bot-status-sep">|</span> <span class="bot-status-elapsed">${formatElapsed(elapsed)}</span>`;
            botStatusEl.style.display = 'flex';
        };
        updateStatus();
        botElapsedInterval = setInterval(updateStatus, 1000);
        window.botTimer = botElapsedInterval; // 🛑 გლობალური წვდომა სინქრონიზატორისთვის
    }

    function stopBotElapsedTimer() {
        if (botElapsedInterval) { clearInterval(botElapsedInterval); botElapsedInterval = null; }
        if (botStatusEl) botStatusEl.style.display = 'none';
    }

    // Story Bot elapsed timer
    let storyBotElapsedInterval = null;

    function startStoryBotElapsedTimer(startTime) {
        if (storyBotElapsedInterval) clearInterval(storyBotElapsedInterval);
        const updateStatus = () => {
            if (!storyBotStatusEl) return;
            const elapsed = Date.now() - startTime;
            storyBotStatusEl.innerHTML = `<i class="fa-regular fa-clock"></i> Started ${formatTime(startTime)} <span class="bot-status-sep">|</span> <span class="story-bot-status-elapsed">${formatElapsed(elapsed)}</span>`;
            storyBotStatusEl.style.display = 'flex';
        };
        updateStatus();
        storyBotElapsedInterval = setInterval(updateStatus, 1000);
        window.storyBotTimer = storyBotElapsedInterval; // 🛑 გლობალური წვდომა სინქრონიზატორისთვის
    }

    function stopStoryBotElapsedTimer() {
        if (storyBotElapsedInterval) { clearInterval(storyBotElapsedInterval); storyBotElapsedInterval = null; }
        if (storyBotStatusEl) storyBotStatusEl.style.display = 'none';
    }

    if (btnStart && btnStop) {
        chrome.storage.local.get(['auto_bot_active', 'auto_bot_start_time'], (res) => {
            if (res.auto_bot_active) {
                btnStart.style.display = 'none';
                btnStop.style.display = 'flex';
                if (res.auto_bot_start_time) startBotElapsedTimer(res.auto_bot_start_time);
            }
        });

        btnStart.onclick = async (e) => {
            e.stopPropagation();
            const isRecent = recentCheck ? recentCheck.checked : false;
            const isTagKeywordMode = document.getElementById('botTagKeywordCheck') ? document.getElementById('botTagKeywordCheck').checked : false;
            const isInnerSearch = document.getElementById('botInnerSearchCheck') ? document.getElementById('botInnerSearchCheck').checked : false;


            // 🛑 დამხმარე ფუნქცია: შიფრავს "8-12" ფორმატს
            const parseRange = (str, defMin, defMax) => {
                if (!str) return [defMin, defMax];
                // str.split('-') ყოფს სტრიქონს "5-9" -> ["5", "9"]
                const parts = str.split('-').map(s => parseInt(s.trim()));
                if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) return [parts[0], parts[1]];
                if (parts.length === 1 && !isNaN(parts[0])) return [parts[0], parts[0]];
                return [defMin, defMax];
            };
            // ვასწორებთ რომ ყოველთვის input-იდან წაიღოს, თუ არის
            const carouselRange = parseRange(document.getElementById('botCarouselRange').value, 1, 3);
            const likeCommRange = parseRange(document.getElementById('botLikeCommentsRatio') ? document.getElementById('botLikeCommentsRatio').value : '4-10', 4, 10);
            const likeCommCheck = document.getElementById('botLikeCommentsCheck') ? document.getElementById('botLikeCommentsCheck').checked : false;
            const likeCommAmt = parseRange(document.getElementById('botLikeCommentsAmount') ? document.getElementById('botLikeCommentsAmount').value : '1-2', 1, 2);
            const profilesRange = parseRange(countInput.value, 10, 20);
            const count = Math.floor(Math.random() * (profilesRange[1] - profilesRange[0] + 1)) + profilesRange[0];
            if (!count || count <= 0) return alert("Please enter a valid number of profiles!");

            const viewsRange = parseRange(viewsInput.value, 8, 12);
            const likesRange = parseRange(likesInput.value, 3, 7);
            const switchRange = parseRange(switchTimeInput ? switchTimeInput.value : '5-13', 5, 13);
            // 🛑 Rest ლოგიკის პარსინგი (მაგ: 5-10:10-20)
            const restVal = restInput.value.trim();
            let restConfig = { profMin: 10, profMax: 10, timeMin: 0, timeMax: 0 };
            if (restVal.includes(':')) {
                const parts = restVal.split(':');
                const profs = parseRange(parts[0], 10, 10);
                const times = parseRange(parts[1], 0, 0);
                restConfig = { profMin: profs[0], profMax: profs[1], timeMin: times[0], timeMax: times[1] };
            } else {
                const times = parseRange(restVal, 0, 0);
                restConfig = { profMin: 10, profMax: 10, timeMin: times[0], timeMax: times[1] };
            }
            // ვითვლით როდის უნდა შეისვენოს პირველად
            const firstRestTarget = Math.floor(Math.random() * (restConfig.profMax - restConfig.profMin + 1)) + restConfig.profMin;
// 🎯 Deep Dive პარამეტრების წაკითხვა
            const keywordInput = document.getElementById('botKeywords');
            const keywordsList = keywordInput && keywordInput.value
                ? keywordInput.value.split(',').map(k => k.trim().toLowerCase()).filter(k => k.length > 0)
                : [];

            const deepDiveInput = document.getElementById('botDeepDiveRange');
            const deepDiveRange = parseRange(deepDiveInput ? deepDiveInput.value : '5-7', 5, 7);
            const onlyKeywordsCheck = document.getElementById('botOnlyKeywords');
            const onlyKeywords = onlyKeywordsCheck ? onlyKeywordsCheck.checked : false;


            btnStart.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            btnStart.disabled = true;

            const filterId = table === 'people' ? 'adminPersonFilter' : table === 'locations' ? 'adminLocFilter' : 'adminTagFilter';
            const filterVal = document.getElementById(filterId).value;
            const { data: { session } } = await supabaseClient.auth.getSession();

            let botBaseQuery = () => {
                let q = supabaseClient.from(table).select('*').eq('user_id', session.user.id);
                if (table === 'people') q = q.eq('platform', 'instagram');
                if (filterVal !== 'all') q = q.eq(table === 'locations' ? 'country_code' : 'category', filterVal);
                return q;
            };

            let botCountQuery = supabaseClient.from(table).select('id', { count: 'exact', head: true }).eq('user_id', session.user.id);
            if (table === 'people') botCountQuery = botCountQuery.eq('platform', 'instagram');
            if (filterVal !== 'all') botCountQuery = botCountQuery.eq(table === 'locations' ? 'country_code' : 'category', filterVal);
            
            const { count: botTotalCount } = await botCountQuery;

            let allItems = [];
            let error = null;
            if (botTotalCount > 0) {
                const FETCH_SIZE = 1000;
                const pages = Math.ceil(botTotalCount / FETCH_SIZE);
                const promises = [];
                for (let i = 0; i < pages; i++) {
                    const from = i * FETCH_SIZE;
                    promises.push(botBaseQuery().order('id', { ascending: false }).range(from, from + FETCH_SIZE - 1));
                }
                const results = await Promise.all(promises);
                results.forEach(res => {
                    if (res.error) error = res.error;
                    if (res.data) allItems = allItems.concat(res.data);
                });
            }

            // 📊 Filter locations by post count range
            if (table === 'locations' && postsRangeInput && postsRangeInput.value.trim()) {
                const rangeParts = postsRangeInput.value.trim().split('-').map(s => s.trim());
                const minPosts = parsePostCount(rangeParts[0]);
                const maxPosts = rangeParts[1] ? parsePostCount(rangeParts[1]) : Infinity;
                if (minPosts || maxPosts < Infinity) {
                    allItems = allItems.filter(i => {
                        const pc = parsePostCount(i.posts_count);
                        return pc >= minPosts && pc <= maxPosts;
                    });
                }
            }

            // 👥 Filter people by followers range
            if (table === 'people' && followersRangeInput && followersRangeInput.value.trim()) {
                const rangeParts = followersRangeInput.value.trim().split('-').map(s => s.trim());
                const minFollowers = parsePostCount(rangeParts[0]);
                const maxFollowers = rangeParts[1] ? parsePostCount(rangeParts[1]) : Infinity;
                if (minFollowers || maxFollowers < Infinity) {
                    allItems = allItems.filter(i => {
                        const fc = parsePostCount(i.followers);
                        return fc >= minFollowers && fc <= maxFollowers;
                    });
                }
            }

            // ⭐ Filter by playlist if selected
            const botPlaylistId = table === 'tags' ? 'playlistTagFilter' : table === 'locations' ? 'playlistLocFilter' : 'playlistPersonFilter';
            const botPlaylistVal = document.getElementById(botPlaylistId)?.value;
            if (botPlaylistVal) {
                const { data: plItems } = await supabaseClient.from('playlist_items').select('item_id').eq('playlist_id', botPlaylistVal);
                if (plItems) {
                    const plItemIds = new Set(plItems.map(p => p.item_id));
                    allItems = allItems.filter(i => plItemIds.has(i.id));
                }
            }

            if (error || !allItems || allItems.length === 0) {
                alert("No items found!");
                btnStart.innerHTML = '<i class="fa-solid fa-play"></i> Start';
                btnStart.disabled = false;
                return;
            }

            const shuffled = allItems.sort(() => 0.5 - Math.random());
            // Loop items if count > available (e.g. 4 items but 8 requested → repeat)
            const filled = [];
            for (let i = 0; i < count; i++) {
                filled.push(shuffled[i % shuffled.length]);
            }
            const itemsToProcess = filled.map(i => {
                if (table === 'people') return { type: 'people', username: i.username };
                if (table === 'locations') return { type: 'locations', id: i.insta_id, name: i.name };
                if (table === 'tags') return { type: 'tags', name: i.name };
            });

            // 🛑 ვინახავთ პარამეტრებს ლოკალურად
            await chrome.storage.local.set({
                auto_bot_active: true,
                auto_bot_deep_dive_active: false, // 🛑 [BUG FIX] სტარტზე ყოველთვის ვანულებთ ძველ სტატუსს
                auto_bot_mode: table,
                auto_bot_items: itemsToProcess,
                auto_bot_index: 0,
                auto_bot_rest_config: restConfig,
                auto_bot_next_rest: firstRestTarget,
                auto_bot_recent: isRecent,
                auto_bot_tag_keyword_mode: isTagKeywordMode,
                auto_bot_inner_search: isInnerSearch,
                auto_bot_keywords: keywordsList,          // 🎯 სიტყვების სია
                auto_bot_deep_dive_range: deepDiveRange,   // 🎯 Deep Dive რეინჯი
                auto_bot_only_keywords: onlyKeywords, // 🎯 სნაიპერის რეჟიმი
                auto_bot_deep_dive_enabled: document.getElementById('botDeepDiveEnabled') ? document.getElementById('botDeepDiveEnabled').checked : false,
                auto_bot_views: viewsRange,   // [min, max]
                auto_bot_likes: likesRange,   // [min, max]
                auto_bot_switch: switchRange, // [min, max] seconds between posts
                auto_bot_carousel_range: carouselRange,
                auto_bot_like_comments_active: likeCommCheck,
                auto_bot_like_comments_ratio: likeCommRange,
                auto_bot_like_comments_amount: likeCommAmt,
                auto_bot_date_limit: savedBotDateLimit,
                auto_bot_pending_initial_search: false,
                auto_bot_start_time: Date.now()
            });

            btnStart.style.display = 'none';
            btnStop.style.display = 'flex';
            btnStart.innerHTML = '<i class="fa-solid fa-play"></i> Start';
            btnStart.disabled = false;
            startBotElapsedTimer(Date.now());

            let mainUrl = '';
            let pendingInitialSearch = false;
            const firstItem = itemsToProcess[0];

            if (isInnerSearch && (table === 'people' || table === 'tags')) {
                mainUrl = null; // Do not refresh, start from current page
                pendingInitialSearch = true;
            } else if (table === 'people') {
                mainUrl = `https://www.instagram.com/${firstItem.username}/`;
            } else if (table === 'locations') {
                const cleanName = firstItem.name.replace(/[@#]/g, '').trim();
                const slug = cleanName.toLowerCase().replace(/[^a-z0-9\s]/g, '').trim().replace(/\s+/g, '-');
                mainUrl = `https://www.instagram.com/explore/locations/${firstItem.id}/${slug}/`;
                if (isRecent) mainUrl += 'recent/';
            } else if (table === 'tags') {
                if (isTagKeywordMode) {
                    const searchTag = firstItem.name.replace(/[#]/g, '').trim();
                    mainUrl = `https://www.instagram.com/explore/search/keyword/?q=${encodeURIComponent(searchTag)}`;
                } else {
                    const cleanTag = firstItem.name.replace(/[#\s]/g, '').toLowerCase();
                    mainUrl = `https://www.instagram.com/explore/tags/${cleanTag}/`;
                }
            }

            await chrome.storage.local.set({ auto_bot_pending_initial_search: pendingInitialSearch });

            if (mainUrl) {
                chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
                    if (tabs && tabs[0]) chrome.tabs.update(tabs[0].id, { url: mainUrl });
                    else window.location.href = mainUrl;
                });
            } else {
                chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
                    if (tabs && tabs[0]) {
                        chrome.tabs.sendMessage(tabs[0].id, { action: 'start_inner_search' }, (response) => {
                            if (chrome.runtime.lastError) {
                                console.warn("Could not send message. Please refresh Instagram.");
                            }
                        });
                    }
                });
            }
        };

        btnStop.onclick = async (e) => {
            e.stopPropagation();
            await chrome.storage.local.set({ auto_bot_active: false });
            btnStart.style.display = 'flex';
            btnStop.style.display = 'none';
            stopBotElapsedTimer();
        };

        // Sync sidebar UI when bot is stopped from the on-page bot bar (register only once)
        if (!_botStorageListenerRegistered) {
            _botStorageListenerRegistered = true;
            chrome.storage.onChanged.addListener((changes, area) => {
                if (area === 'local' && changes.auto_bot_active) {
                    const isActive = changes.auto_bot_active.newValue;
                    if (!isActive) {
                        const startBtn = document.querySelector('#btnStartBot');
                        const stopBtn = document.querySelector('#btnStopBot');
                        if (startBtn) { startBtn.style.display = 'flex'; startBtn.innerHTML = '<i class="fa-solid fa-play"></i> Start'; startBtn.disabled = false; }
                        if (stopBtn) stopBtn.style.display = 'none';
                        stopBotElapsedTimer();
                    }
                }
            });
        }
    }

    // 📖 Story Bot start/stop/continue logic
    if (btnStartStory && btnStopStory) {
        chrome.storage.local.get(['auto_story_bot_active', 'auto_story_bot_start_time', 'auto_feed_story_bot_active', 'auto_feed_story_bot_start_time'], (res) => {
            if (res.auto_story_bot_active || res.auto_feed_story_bot_active) {
                btnStartStory.style.display = 'none';
                if (btnContinueStory) btnContinueStory.style.display = 'none';
                btnStopStory.style.display = 'flex';
                const st = res.auto_story_bot_start_time || res.auto_feed_story_bot_start_time;
                if (st) startStoryBotElapsedTimer(st);
            }
        });

        btnStartStory.onclick = async (e) => {
            e.stopPropagation();

            // 🛑 დამხმარე ფუნქცია: შიფრავს "8-12" ან უბრალოდ "5" ფორმატს
            const parseRange = (str, defMin, defMax) => {
                if (!str) return [defMin, defMax];
                const parts = str.split('-').map(s => parseInt(s.trim()));
                // თუ ორი ციფრია (მაგ: 5-9)
                if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) return [parts[0], parts[1]];
                // 🎯 ახალი: თუ მხოლოდ ერთი ციფრია (მაგ: 5), ვაბრუნებთ [5, 5]
                if (parts.length === 1 && !isNaN(parts[0])) return [parts[0], parts[0]];
                return [defMin, defMax];
            };

            const profilesRange = parseRange(storyBotCountInput.value, 10, 20);
            const storyCount = Math.floor(Math.random() * (profilesRange[1] - profilesRange[0] + 1)) + profilesRange[0];

            if (!storyCount || storyCount <= 0) return alert("Please enter a valid number of profiles!");

            const focusRange = parseRange(storyFocusInput ? storyFocusInput.value : '3-7', 3, 7);
            const perUserRange = parseRange(storyPerUserInput ? storyPerUserInput.value : '3-8', 3, 8);
            const likeRatioRange = parseRange(storyLikeRatioInput ? storyLikeRatioInput.value : '6-10', 6, 10);

            btnStartStory.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            btnStartStory.disabled = true;

            const filterVal = document.getElementById('adminPersonFilter').value;
            const { data: { session } } = await supabaseClient.auth.getSession();

            let storyBaseQuery = () => {
                let q = supabaseClient.from('people').select('*').eq('user_id', session.user.id).eq('platform', 'instagram');
                if (filterVal !== 'all') q = q.eq('category', filterVal);
                return q;
            };

            let storyCountQuery = supabaseClient.from('people').select('id', { count: 'exact', head: true }).eq('user_id', session.user.id).eq('platform', 'instagram');
            if (filterVal !== 'all') storyCountQuery = storyCountQuery.eq('category', filterVal);
            const { count: storyTotalCount } = await storyCountQuery;

            let allItems = [];
            let error = null;
            if (storyTotalCount > 0) {
                const FETCH_SIZE = 1000;
                const pages = Math.ceil(storyTotalCount / FETCH_SIZE);
                const promises = [];
                for (let i = 0; i < pages; i++) {
                    const from = i * FETCH_SIZE;
                    promises.push(storyBaseQuery().order('id', { ascending: false }).range(from, from + FETCH_SIZE - 1));
                }
                const results = await Promise.all(promises);
                results.forEach(res => {
                    if (res.error) error = res.error;
                    if (res.data) allItems = allItems.concat(res.data);
                });
            }

            // Filter by followers range if set
            if (followersRangeInput && followersRangeInput.value.trim()) {
                const rangeParts = followersRangeInput.value.trim().split('-').map(s => s.trim());
                const minFollowers = parsePostCount(rangeParts[0]);
                const maxFollowers = rangeParts[1] ? parsePostCount(rangeParts[1]) : Infinity;
                if (minFollowers || maxFollowers < Infinity) {
                    allItems = allItems.filter(i => {
                        const fc = parsePostCount(i.followers);
                        return fc >= minFollowers && fc <= maxFollowers;
                    });
                }
            }

            // Filter by playlist if selected
            const storyPlaylistVal = document.getElementById('playlistPersonFilter')?.value;
            if (storyPlaylistVal) {
                const { data: plItems } = await supabaseClient.from('playlist_items').select('item_id').eq('playlist_id', storyPlaylistVal);
                if (plItems) {
                    const plItemIds = new Set(plItems.map(p => p.item_id));
                    allItems = allItems.filter(i => plItemIds.has(i.id));
                }
            }

            if (error || !allItems || allItems.length === 0) {
                alert("No people found for Story Bot!");
                btnStartStory.innerHTML = '<i class="fa-solid fa-play"></i> Stories';
                btnStartStory.disabled = false;
                return;
            }

            const shuffled = allItems.sort(() => 0.5 - Math.random());
            const filled = [];
            for (let i = 0; i < storyCount; i++) {
                filled.push(shuffled[i % shuffled.length]);
            }
            const itemsToProcess = filled.map(i => ({ type: 'people', username: i.username }));

            await chrome.storage.local.set({
                auto_story_bot_active: true,
                auto_story_bot_items: itemsToProcess,
                auto_story_bot_index: 0,
                auto_story_bot_focus: focusRange,
                auto_story_bot_per_user: perUserRange,
                auto_story_bot_like_ratio: likeRatioRange,
                auto_story_bot_start_time: Date.now(),
                auto_story_bot_pending_initial_search: savedStoryInnerSearch ? true : false,
                auto_story_bot_inner_search: savedStoryInnerSearch
            });

            btnStartStory.style.display = 'none';
            btnStopStory.style.display = 'flex';
            btnStartStory.innerHTML = '<i class="fa-solid fa-play"></i> Stories';
            btnStartStory.disabled = false;
            startStoryBotElapsedTimer(Date.now());

            const firstUsername = itemsToProcess[0].username;
            let mainUrl = `https://www.instagram.com/${firstUsername}/`;

            if (savedStoryInnerSearch) {
                mainUrl = null;
            }

            if (mainUrl) {
                chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
                    if (tabs && tabs[0]) chrome.tabs.update(tabs[0].id, { url: mainUrl });
                    else window.location.href = mainUrl;
                });
            } else {
                chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
                    if (tabs && tabs[0]) {
                        chrome.tabs.sendMessage(tabs[0].id, { action: 'start_story_inner_search' }, (response) => {
                            if (chrome.runtime.lastError) {
                                console.warn("Could not send message. Please refresh Instagram.");
                            }
                        });
                    }
                });
            }
        };

        // Continue button — user is already watching followings' stories
        if (btnContinueStory) {
            btnContinueStory.onclick = async (e) => {
                e.stopPropagation();

                const parseRange = (str, defMin, defMax) => {
                    if (!str) return [defMin, defMax];
                    const parts = str.split('-').map(s => parseInt(s.trim()));
                    if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) return [parts[0], parts[1]];
                    if (parts.length === 1 && !isNaN(parts[0])) return [parts[0], parts[0]];
                    return [defMin, defMax];
                };

                const focusRange = parseRange(storyFocusInput ? storyFocusInput.value : '3-7', 3, 7);
                const perUserRange = parseRange(storyPerUserInput ? storyPerUserInput.value : '3-8', 3, 8);
                const likeRatioRange = parseRange(storyLikeRatioInput ? storyLikeRatioInput.value : '6-10', 6, 10);

                await chrome.storage.local.set({
                    auto_feed_story_bot_active: true,
                    auto_feed_story_bot_focus: focusRange,
                    auto_feed_story_bot_per_user: perUserRange,
                    auto_feed_story_bot_like_ratio: likeRatioRange,
                    auto_feed_story_bot_start_time: Date.now()
                });

                btnStartStory.style.display = 'none';
                btnContinueStory.style.display = 'none';
                btnStopStory.style.display = 'flex';
                startStoryBotElapsedTimer(Date.now());
            };
        }

        btnStopStory.onclick = async (e) => {
            e.stopPropagation();
            await chrome.storage.local.set({ auto_story_bot_active: false, auto_feed_story_bot_active: false });
            btnStartStory.style.display = 'flex';
            if (btnContinueStory) btnContinueStory.style.display = 'flex';
            btnStopStory.style.display = 'none';
            stopStoryBotElapsedTimer();
            alert("Story Bot Stopped!");
        };

        if (!_storyBotStorageListenerRegistered) {
            _storyBotStorageListenerRegistered = true;
            chrome.storage.onChanged.addListener((changes, area) => {
                if (area !== 'local') return;
                const storyOff = changes.auto_story_bot_active && !changes.auto_story_bot_active.newValue;
                const feedOff = changes.auto_feed_story_bot_active && !changes.auto_feed_story_bot_active.newValue;
                if (storyOff || feedOff) {
                    const startBtn = document.querySelector('#btnStartStoryBot');
                    const continueBtn = document.querySelector('#btnContinueStoryBot');
                    const stopBtn = document.querySelector('#btnStopStoryBot');
                    if (startBtn) { startBtn.style.display = 'flex'; startBtn.innerHTML = '<i class="fa-solid fa-play"></i> Stories'; startBtn.disabled = false; }
                    if (continueBtn) continueBtn.style.display = 'flex';
                    if (stopBtn) stopBtn.style.display = 'none';
                    stopStoryBotElapsedTimer();
                }
            });
        }
    }

    // 🎯 3. CLICK LOGIC (შენახული ლოგიკა რედაქტირებისთვის, წაშლისთვის და ნავიგაციისთვის)
    container.onclick = (e) => {
        // თუ შემთხვევით ბოტის ინფუთს ან ღილაკებს დააჭირა, ვაიგნორებთ
        if (e.target.closest('.bot-panel') || e.target.closest('.story-bot-panel')) {
            return;
        }

        const btnEdit = e.target.closest('.btn-edit');
        const btnDel = e.target.closest('.btn-del');
        const btnFav = e.target.closest('.btn-fav-item');
        const checkbox = e.target.closest('.item-checkbox');
        const row = e.target.closest('.db-row');

        if (btnFav) {
            e.stopPropagation();
            openPlaylistPopup(parseInt(btnFav.dataset.id), table === 'locations' ? 'locations' : table === 'people' ? 'people' : 'tags');
            return;
        }

        if (btnEdit) {
            e.stopPropagation();
            openEditFlow(data.find(i => i.id == btnEdit.dataset.id), table);
            return;
        }

        if (btnDel) {
            e.stopPropagation();
            deleteItem(table, btnDel.dataset.id);
            return;
        }

        if (checkbox) {
            handleSelection();
            return;
        }

        // ნავიგაცია და გამორჩევა (Selected Row)
        if (row) {
            lastSelectedId = row.dataset.id;

            // ვიზუალური გაფერადება
            container.querySelectorAll('.db-row').forEach(r => r.classList.remove('selected-row'));
            row.classList.add('selected-row');

            // გადამისამართება
            const item = data.find(i => i.id == lastSelectedId);
            if (item) {
                navigateToResult(table === 'locations' ? 'loc' : table === 'tags' ? 'tag' : 'person', item);
            }
        }
    };
}

function openEditFlow(item, table) {
    currentMode = 'edit';
    currentId = item.id;
    currentTable = table;
    document.getElementById('inputTitle').innerText = 'Edit Item';

    // ვსვამთ ნამდვილ სახელს
    document.getElementById('inputName').value = item.name || item.username;

    const idWrap = document.getElementById('idFieldWrapper');
    const countWrap = document.getElementById('countFieldWrapper');
    const inputId = document.getElementById('inputID');

    const catWrap = document.getElementById('categoryFieldWrapper');
    const inputCat = document.getElementById('inputCategory');
    const followWrap = document.getElementById('followersFieldWrapper');

    if (table === 'locations') {
        idWrap.classList.remove('hidden');
        countWrap.classList.remove('hidden');
        catWrap.classList.remove('hidden');
        followWrap.classList.add('hidden');
        inputId.value = item.insta_id || '';
        inputId.placeholder = 'Instagram ID';
        document.getElementById('inputCount').value = item.posts_count || '';
        populateSelect('inputCategory', APP_CONFIG.locations, 'Select Country');
        if (item.country_code) inputCat.value = item.country_code;
    }
    // 🛑 People ცხრილშიც ვაჩენთ ID ველს
    else if (table === 'people') {
        idWrap.classList.remove('hidden');
        countWrap.classList.add('hidden');
        catWrap.classList.remove('hidden');
        followWrap.classList.remove('hidden');
        inputId.value = item.username || '';
        inputId.placeholder = 'Handle / Link ID (e.g. UC...)';
        document.getElementById('inputFollowers').value = item.followers || '';
        document.getElementById('inputFollowing').value = item.following || '';
        populateSelect('inputCategory', APP_CONFIG.people, 'Select Category');
        if (item.category) inputCat.value = item.category;
    }
    else {
        idWrap.classList.add('hidden');
        countWrap.classList.add('hidden');
        catWrap.classList.remove('hidden');
        followWrap.classList.add('hidden');
        populateSelect('inputCategory', APP_CONFIG.tags, 'Select Category');
        if (item.category) inputCat.value = item.category;
    }

    document.getElementById('inputModal').classList.remove('hidden');
}

// 🟢 SEARCH & NAVIGATION
async function handleSearch(type) {
    const overlay = document.getElementById('resModalOverlay');
    const box = document.getElementById('resCardBox');
    overlay.classList.add('active');
    document.body.classList.add('no-scroll');
    document.documentElement.classList.add('no-scroll');
    box.classList.add('loading');

    document.getElementById('btnModalRefresh').onclick = () => handleSearch(type);

    try {
        const { data: { session } } = await supabaseClient.auth.getSession();
        if (!session) { document.getElementById('loginModal').classList.remove('hidden'); overlay.classList.remove('active'); return; }

        let dataVal = null, extra = null;

        if (type === 'tag') {
            const val = document.getElementById('tagInput').value.trim();
            if(val) dataVal = val;
            else {
                const selVals = getMultiSelectValues('tagCategory');
                let q = supabaseClient.from('tags').select('name');
                if(!selVals.includes('all') && selVals.length > 0) q = q.in('category', selVals);
                const {data} = await q;
                if(data?.length) dataVal = data[Math.floor(Math.random() * data.length)].name;
            }
        } else if (type === 'loc') {
            const val = document.getElementById('locInput').value.trim();
            if(val) dataVal = val;
            else {
                const selVals = getMultiSelectValues('locCategory');
                let q = supabaseClient.from('locations').select('*');
                if(!selVals.includes('all') && selVals.length > 0) q = q.in('country_code', selVals);
                const {data} = await q;
                if(data?.length) { 
                    const randLoc = data[Math.floor(Math.random() * data.length)];
                    dataVal = randLoc.name; extra = randLoc; 
                }
            }
        } else {
            const val = document.getElementById('personInput').value.trim();
            if(val) dataVal = val;
            else {
                // 🛑 ვიღებთ მხოლოდ იმ პლატფორმის იუზერებს, რომელზეც ახლა ვართ!
                const catVals = getMultiSelectValues('personCategory');
                let query = supabaseClient.from('people').select('username').eq('platform', currentPlatform);

                // თუ კონკრეტული კატეგორიაა არჩეული (და არა All)
                if (catVals.length > 0 && !catVals.includes('all')) { query = query.in('category', catVals); }

                const { data } = await query;
                if (data && data.length > 0) {
                    // ვირჩევთ რენდომ იუზერს ჩვენი გაფილტრული სიიდან
                    const randomUser = data[Math.floor(Math.random() * data.length)];
                    dataVal = randomUser.username;
                }
            }
        }

        if(!dataVal) throw new Error("No data found");

        setTimeout(() => {
            updateModalContent(type, dataVal, extra);
            box.classList.remove('loading');
        }, 500);

    } catch(e) {
        overlay.classList.remove('active');
        showToast("Nothing found", "error");
    }
}

function updateModalContent(type, data, extra) {
    const icon = document.getElementById('resIcon');
    const typeEl = document.getElementById('resType');
    const valEl = document.getElementById('resValue');
    const btnGo = document.getElementById('btnModalGo');
    const btnMap = document.getElementById('btnModalMap');
    const isKeyword = document.getElementById('noHashCheck').checked;

    btnMap.classList.add('hidden');
    const itemData = { name: data, username: data, insta_id: extra?.insta_id, country_code: extra?.country_code };

    if(type === 'tag') {
        icon.innerHTML = '<i class="fa-solid fa-hashtag"></i>';
        typeEl.innerText = "TAG";
        valEl.innerHTML = `<span class="gradient-text">${isKeyword ? data : '#'+data.replace(/\s/g,'')}</span>`;
        btnGo.innerHTML = `<i class="fa-brands fa-instagram"></i> Open Tag`;
    } else if (type === 'loc') {
        icon.innerHTML = '<i class="fa-solid fa-location-dot"></i>';
        let label = "LOCATION";
        if(extra?.country_code) label += ` IN ${APP_CONFIG.locations[extra.country_code] || extra.country_code}`;
        typeEl.innerText = label;
        valEl.innerHTML = `<span class="gradient-text">${data}</span>`;
        btnGo.innerHTML = `<i class="fa-solid fa-map-pin"></i> View Location`;
        btnMap.classList.remove('hidden');
        btnMap.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(data)}`;
    } else {
        icon.innerHTML = '<i class="fa-solid fa-user-astronaut"></i>';
        typeEl.innerText = "PROFILE";
        valEl.innerHTML = `<span class="gradient-text">@${data}</span>`;
        btnGo.innerHTML = `<i class="fa-solid fa-user"></i> Visit`;
    }

    btnGo.onclick = (e) => { e.preventDefault(); navigateToResult(type, itemData, e.ctrlKey || e.metaKey); };
    btnGo.onauxclick = (e) => { if(e.button===1) { e.preventDefault(); navigateToResult(type, itemData, true); }};

    if(document.getElementById('autoNavCheck').checked) setTimeout(()=> navigateToResult(type, itemData), 300);
}

function navigateToResult(type, item, openInNewTab = false) {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        const activeTab = tabs[0];

        // აქ აღარ ვამოწმებთ URL-ს, ვენდობით არჩეულ აიკონს
        const isFacebook = currentPlatform === 'facebook';
        const isYoutube = currentPlatform === 'youtube';
        // დანარჩენი ავტომატურად Instagram

        let mainUrl = '';
        const name = item.name || item.username;
        const cleanName = name.replace(/[@#]/g, '').trim();
        const encodedName = encodeURIComponent(cleanName);
        const noSpaceName = encodeURIComponent(cleanName.replace(/\s/g, ''));

        const FB_RECENT_PARAM = "&filters=eyJyZWNlbnRfcG9zdHM6MCI6IntcIm5hbWVcIjpcInJlY2VudF9wb3N0c1wiLFwiYXJnc1wiOlwiXCJ9In0%3D";

        // 1️⃣ YOUTUBE LOGIC
        if (isYoutube) {
            const isKeyword = document.getElementById('noHashCheck')?.checked;

            if (type === 'tag') {
                if (isKeyword) {
                    const timeValue = document.getElementById('ytDateSelect').value;
                    let timeParam = "";
                    switch (timeValue) {
                        case 'today': timeParam = "&sp=EgIIAg%253D%253D"; break;
                        case 'week':  timeParam = "&sp=EgQIAxAB"; break;
                        case 'month': timeParam = "&sp=EgQIBBAB"; break;
                        case 'year':  timeParam = "&sp=EgQIBRAB"; break;
                        default:      timeParam = "";
                    }
                    mainUrl = `https://www.youtube.com/results?search_query=${encodedName}${timeParam}`;
                } else {
                    mainUrl = `https://www.youtube.com/hashtag/${noSpaceName}`;
                }
            }
            else if (type === 'person') {
                // ვასუფთავებთ зайდმეტი გამოტოვებებისგან
                const originalName = (item.username || item.name || '').trim();

                // 1. თუ იწყება @-ით (ახალი არხის ლინკი)
                if (originalName.startsWith('@')) {
                    mainUrl = `https://www.youtube.com/${originalName}`;
                }
                // 2. თუ იწყება UC-თი და არის ზუსტად 24 სიმბოლო (YouTube Channel ID-ის სტანდარტი)
                else if (originalName.toUpperCase().startsWith('UC') && originalName.length === 24) {
                    mainUrl = `https://www.youtube.com/channel/${originalName}`;
                }
                // 3. თუ არცერთია (მაგალითად ძველი ბაზიდან შემორჩენილი უბრალო სახელი)
                else {
                    const cleanString = originalName.replace(/\s/g, '');
                    // ვიყენებთ YouTube-ის /c/ რეზოლვერს, რომელიც ბევრად უსაფრთხოა და არ აგდებს Home-ზე
                    mainUrl = `https://www.youtube.com/c/${cleanString}`;
                }
            }
            else if (type === 'loc') {
                const timeValueLoc = document.getElementById('ytDateSelectLoc').value;
                let locParam = "";
                switch (timeValueLoc) {
                    case 'today': locParam = "&sp=EgcIAhABuAEB"; break;
                    case 'week':  locParam = "&sp=EgcIAxABuAEB"; break;
                    case 'month': locParam = "&sp=EgcIBBABuAEB"; break;
                    case 'year':  locParam = "&sp=EgcIBRABuAEB"; break;
                    default:      locParam = "&sp=EgO4AQE%253D"; break;
                }
                mainUrl = `https://www.youtube.com/results?search_query=${encodedName}${locParam}`;
            }
        }

        // 2️⃣ FACEBOOK LOGIC
        // 2️⃣ FACEBOOK LOGIC
        else if (isFacebook) {
            if (type === 'tag') {
                const isKeyword = document.getElementById('noHashCheck')?.checked;
                const isRecent = document.getElementById('recentCheckTag')?.checked;

                if (isKeyword) {
                    mainUrl = `https://www.facebook.com/search/posts/?q=${encodedName}`;
                    if (isRecent) mainUrl += FB_RECENT_PARAM;
                } else {
                    mainUrl = `https://www.facebook.com/hashtag/${noSpaceName}`;
                }
            }
            else if (type === 'person') {
                // პირდაპირ პროფილზე გადასვლა FB-ის Username-ით
                mainUrl = `https://www.facebook.com/${cleanName}`;
            }
            else if (type === 'loc') {
                const isRecentLoc = document.getElementById('recentCheckLoc')?.checked;
                mainUrl = `https://www.facebook.com/search/posts/?q=${encodedName}`;
                if (isRecentLoc) mainUrl += FB_RECENT_PARAM;
            }
        }

        // 3️⃣ INSTAGRAM LOGIC (Default)
        else {
            if (type === 'tag') {
                const isKeyword = document.getElementById('noHashCheck')?.checked;
                if (isKeyword) {
                    mainUrl = `https://www.instagram.com/explore/search/keyword/?q=${encodedName}`;
                } else {
                    mainUrl = `https://www.instagram.com/explore/tags/${noSpaceName}/`;
                }
            }
            else if (type === 'loc') {
                const isRecentLoc = document.getElementById('recentCheckLoc')?.checked;
                if (item.insta_id) {
                    const slug = cleanName.toLowerCase().replace(/[^a-z0-9\s]/g, '').trim().replace(/\s+/g, '-');
                    mainUrl = `https://www.instagram.com/explore/locations/${item.insta_id}/${slug}/`;
                    if (isRecentLoc) mainUrl += 'recent/';
                } else {
                    mainUrl = `https://www.google.com/search?q=site:instagram.com/explore/locations/ "${name}"`;
                }
            }
            else {
                const igHandle = (item.username || item.name || '').replace(/[@#]/g, '').trim();
                mainUrl = `https://www.instagram.com/${igHandle}/`;
            }
        }

        // 🚀 NAVIGATION EXECUTION
        let shouldUseInnerSearch = false;
        let innerSearchQuery = '';
        let innerSearchExpectedHref = '';

        const currentTabUrl = activeTab ? activeTab.url : '';
        const targetDomain = isYoutube ? 'youtube.com' : (isFacebook ? 'facebook.com' : 'instagram.com');
        
        if (document.getElementById('globalInnerSearchCheck')?.checked && !isYoutube && !isFacebook && !openInNewTab && currentTabUrl.includes('instagram.com')) {
            if (type === 'tag') {
                const isKeyword = document.getElementById('noHashCheck')?.checked;
                if (!isKeyword) {
                    shouldUseInnerSearch = true;
                    innerSearchQuery = `#${noSpaceName}`;
                    innerSearchExpectedHref = `/explore/tags/${noSpaceName}/`;
                }
            } else if (type === 'person') {
                shouldUseInnerSearch = true;
                const igHandle = (item.username || item.name || '').replace(/[@#]/g, '').trim().toLowerCase();
                innerSearchQuery = igHandle;
                innerSearchExpectedHref = `/${igHandle}/`;
            }
        }

        if (mainUrl) {
            // 📍 Auto-update flag for locations navigated from manager
            if (type === 'loc' && item.insta_id) {
                chrome.storage.local.set({ pending_loc_update: { insta_id: item.insta_id, name: item.name } });
            }
            // 👥 Auto-update flag for people navigated from manager
            if (type === 'person' && item.username) {
                chrome.storage.local.set({ pending_person_update: { username: (item.username || item.name || '').replace(/[@#]/g, '').trim().toLowerCase() } });
            }

            if (shouldUseInnerSearch) {
                chrome.tabs.sendMessage(activeTab.id, { 
                    action: 'manual_inner_search', 
                    query: innerSearchQuery, 
                    expectedHref: innerSearchExpectedHref,
                    fallbackUrl: mainUrl
                }, (response) => {
                    if (chrome.runtime.lastError) {
                        console.warn("Could not send inner search msg, falling back to URL.");
                        chrome.tabs.update(activeTab.id, { url: mainUrl });
                    }
                });
                return;
            }

            // თუ სხვა დომენზე ვართ, ყოველთვის ახალ ტაბში ვხსნით (რომ მიმდინარე გვერდი არ დაიკარგოს)
            // ან თუ მომხმარებელმა მოითხოვა (ctrl+click)
            if (openInNewTab || !currentTabUrl.includes(targetDomain)) {
                chrome.tabs.create({ url: mainUrl, active: true });
            } else {
                chrome.tabs.update(activeTab.id, { url: mainUrl });
            }
        }
    });
}
window.loadCatEditor = async (section) => {
    currentSettingsTab = section;
    const list = document.getElementById('settingsList');
    list.innerHTML = '<div style="padding:10px; text-align:center;">Loading...</div>';

    const { data: { session } } = await supabaseClient.auth.getSession();
    if (!session) return;

    const { data } = await supabaseClient.from('app_categories')
        .select('*')
        .eq('user_id', session.user.id)
        .eq('section', section)
        .order('label');

    list.innerHTML = '';
    if (!data || !data.length) {
        list.innerHTML = '<div style="padding:10px; text-align:center; opacity:0.5;">No categories yet</div>';
        return;
    }

    // HTML-ის აწყობა
    let htmlContent = '';
    data.forEach(item => {
        htmlContent += `
            <div class="db-row">
                <div class="db-info">
                    <b>${item.label}</b>
                    <span>(${item.key_val})</span>
                </div>
                <div class="db-actions">
                    <button class="btn-edit-cat" data-id="${item.id}" data-label="${item.label}" data-key="${item.key_val}">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn-del-cat" data-id="${item.id}">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>`;
    });
    list.innerHTML = htmlContent;

    // 🛑 EVENT DELEGATION: კატეგორიების ლისტზე ივენთების მიბმა
    list.onclick = (e) => {
        const btnEdit = e.target.closest('.btn-edit-cat');
        const btnDel = e.target.closest('.btn-del-cat');

        if (btnEdit) {
            editingCategory = btnEdit.dataset.id;
            document.getElementById('newCatLabel').value = btnEdit.dataset.label;
            document.getElementById('newCatKey').value = btnEdit.dataset.key;
            document.getElementById('btnAddCategory').innerText = "Update";
            // სქროლი ავიდეს ზემოთ, რომ ინპუტები დაინახო
            document.querySelector('.admin-content').scrollTop = 0;
        }

        if (btnDel) {
            handleDeleteCategory(btnDel.dataset.id);
        }
    };
};

// ცალკე ფუნქცია წაშლისთვის (უფრო სუფთაა)
async function handleDeleteCategory(id) {
    if (confirm("Are you sure you want to delete this category?")) {
        const { error } = await supabaseClient.from('app_categories').delete().eq('id', id);
        if (error) showToast("Error deleting", "error");
        else {
            showToast("Category removed", "success");
            loadCatEditor(currentSettingsTab);
            loadAppConfig();
        }
    }
}

async function addNewCategory() {
    const labelInput = document.getElementById('newCatLabel');
    const keyInput = document.getElementById('newCatKey');
    const btn = document.getElementById('btnAddCategory');

    const label = labelInput.value.trim();
    const key = keyInput.value.trim().toLowerCase();

    if (!label || !key) return showToast("Fill all fields", "error");

    const { data: { session } } = await supabaseClient.auth.getSession();
    btn.innerText = "Processing...";
    btn.disabled = true;

    try {
        if (editingCategory) {
            // 🔄 UPDATE LOGIC
            await supabaseClient.from('app_categories')
                .update({ label, key_val: key })
                .eq('id', editingCategory);
            showToast("Category updated", "success");
        } else {
            // ➕ INSERT LOGIC
            await supabaseClient.from('app_categories').insert([
                { user_id: session.user.id, section: currentSettingsTab, label, key_val: key }
            ]);
            showToast("Category added", "success");
        }

        // Reset Form
        editingCategory = null;
        labelInput.value = '';
        keyInput.value = '';
        btn.innerText = "Add";
        btn.disabled = false;

        // Refresh UI
        await loadCatEditor(currentSettingsTab);
        await loadAppConfig();
    } catch (err) {
        console.error(err);
        showToast("Error saving", "error");
        btn.innerText = "Add";
        btn.disabled = false;
    }
}

window.editCat = (id, label, key) => {
    editingCategory = id;
    document.getElementById('newCatLabel').value = label;
    document.getElementById('newCatKey').value = key;
    document.getElementById('btnAddCategory').innerText = "Update";
};

window.delCat = async (id) => {
    if(confirm("Delete category?")) {
        await supabaseClient.from('app_categories').delete().eq('id', id);
        loadCatEditor(currentSettingsTab);
        loadAppConfig();
    }
};


// 🟢 HELPERS & AUTH
async function initAuthWidget() {
    const { data: { session } } = await supabaseClient.auth.getSession();
    const w = document.getElementById('authWidget');
    if(session) {
        w.innerHTML = `<div class="user-badge"><span class="user-name">${session.user.email.split('@')[0]}</span><button id="btnLogout" class="widget-logout-btn"><i class="fa-solid fa-power-off"></i></button></div>`;
        document.getElementById('btnLogout').onclick = async () => { if(confirm("Logout?")) { await supabaseClient.auth.signOut(); location.reload(); } };
    } else {
        w.innerHTML = `<button id="btnLoginOpen" class="widget-login-btn"><i class="fa-solid fa-key"></i></button>`;
        document.getElementById('btnLoginOpen').onclick = () => document.getElementById('loginModal').classList.remove('hidden');
    }
}

let isRegister = false;
document.getElementById('btnToggleAuth')?.addEventListener('click', (e) => {
    e.preventDefault(); isRegister = !isRegister;
    document.querySelector('#loginModal h3').innerText = isRegister ? "Register" : "Sign In";
    document.getElementById('btnLoginConfirm').innerText = isRegister ? "Register" : "Sign In";
    document.getElementById('btnToggleAuth').innerText = isRegister ? "Sign In" : "Sign Up";
});

document.getElementById('btnLoginConfirm').onclick = async () => {
    const email = document.getElementById('loginEmail').value;
    const pass = document.getElementById('loginPass').value;
    const { error } = isRegister ? await supabaseClient.auth.signUp({email, password: pass}) : await supabaseClient.auth.signInWithPassword({email, password: pass});
    if(error) {
        document.getElementById('loginError').innerText = error.message;
        document.getElementById('loginError').classList.remove('hidden');
    } else {
        location.reload();
    }
};
document.getElementById('btnLoginCancel').onclick = () => document.getElementById('loginModal').classList.add('hidden');

function showToast(msg, type="info") {
    const t = document.createElement('div');
    t.className = `glass-toast ${type}`;
    t.innerHTML = `<i class="fa-solid fa-${type==='success'?'check':'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 3000);
}

async function askConfirm(title, text) {
    return new Promise(resolve => {
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmText').innerText = text;
        const m = document.getElementById('customConfirmModal');
        m.classList.remove('hidden');
        document.getElementById('btnConfirmYes').onclick = () => { m.classList.add('hidden'); resolve(true); };
        document.getElementById('btnConfirmNo').onclick = () => { m.classList.add('hidden'); resolve(false); };
    });
}

function handleSelection() {
    const active = document.querySelector('.admin-panel.active');
    const checked = active.querySelectorAll('.item-checkbox:checked');
    const bar = document.getElementById('bulkDeleteBar');
    if(checked.length) {
        bar.classList.remove('hidden');
        document.getElementById('bulkCount').innerText = `${checked.length} selected`;

        // Populate Move Select
        const sel = document.getElementById('bulkMoveDest');
        sel.innerHTML = '<option value="">Move to...</option>';
        Object.entries(APP_CONFIG[currentTable]).forEach(([k,v]) => sel.innerHTML += `<option value="${k}">${v}</option>`);
    } else {
        bar.classList.add('hidden');
    }
}

async function executeBulkDelete() {
    const checked = document.querySelector('.admin-panel.active').querySelectorAll('.item-checkbox:checked');
    const ids = Array.from(checked).map(c => c.value);
    if(await askConfirm("Delete?", `Remove ${ids.length} items?`)) {
        await supabaseClient.from(currentTable).delete().in('id', ids);
        loadAdminData(currentTable);
        showToast("Deleted", "success");
        document.getElementById('bulkDeleteBar').classList.add('hidden');
    }
}

async function executeBulkMove() {
    const checked = document.querySelector('.admin-panel.active').querySelectorAll('.item-checkbox:checked');
    const ids = Array.from(checked).map(c => c.value);
    const dest = document.getElementById('bulkMoveDest').value;
    if(!dest) return showToast("Select destination", "error");

    if(await askConfirm("Move?", `Move ${ids.length} items?`)) {
        const col = currentTable === 'locations' ? 'country_code' : 'category';
        await supabaseClient.from(currentTable).update({[col]: dest}).in('id', ids);
        loadAdminData(currentTable);
        showToast("Moved", "success");
        document.getElementById('bulkDeleteBar').classList.add('hidden');
    }
}

async function saveItem() {
    const name = document.getElementById('inputName').value.trim();
    if(!name) return;
    const btn = document.getElementById('btnSaveInput');
    btn.innerText = "...";

    const { data: { session } } = await supabaseClient.auth.getSession();
    const items = name.split(';').map(s=>s.trim()).filter(s=>s);

    let cat;
    if (currentMode === 'edit') {
        cat = document.getElementById('inputCategory').value;
        if(cat === 'all') return alert("Select a category first!");
    } else {
        let filterId = currentTable === 'tags' ? 'adminTagFilter' : currentTable === 'locations' ? 'adminLocFilter' : 'adminPersonFilter';
        cat = document.getElementById(filterId).value;
        if(cat === 'all') return alert("Select a category first!");
    }

    for(let n of items) {
        let obj = {};

        if (currentTable === 'people') {
            obj.name = n;
            const providedId = document.getElementById('inputID')?.value.trim();
            obj.username = providedId ? providedId : n;
            obj.platform = currentPlatform;
            obj.category = cat;
            obj.followers = document.getElementById('inputFollowers')?.value || null;
            obj.following = document.getElementById('inputFollowing')?.value || null;
        } else if (currentTable === 'locations') {
            obj.name = n;
            obj.country_code = cat; // 🛑 ლოკაციების კატეგორია
            obj.posts_count = document.getElementById('inputCount')?.value || null;
            obj.insta_id = document.getElementById('inputID')?.value || null;
        } else {
            obj.name = n;
            obj.category = cat; // 🛑 ტეგების კატეგორია
        }

        obj.user_id = session.user.id;

        if(currentMode === 'edit') await supabaseClient.from(currentTable).update(obj).eq('id', currentId);
        else {
            // Check Duplicates
            let checkCol = currentTable === 'people' ? 'username' : 'name';
            const {data: ex} = await supabaseClient.from(currentTable).select('id').ilike(checkCol, n).eq('user_id', session.user.id);
            if(!ex.length) await supabaseClient.from(currentTable).insert([obj]);
        }
    }

    document.getElementById('inputModal').classList.add('hidden');
    btn.innerText = "Save";
    loadAdminData(currentTable);
    showToast("Saved!", "success");
}

function injectResultModalListeners() {
    document.getElementById('btnModalClose').onclick = closeResultModal;
    document.getElementById('resModalOverlay').onclick = (e) => { if(e.target.id === 'resModalOverlay') closeResultModal(); };
    document.addEventListener('keydown', (e) => { if(e.key==='Escape') closeResultModal(); });
}
function closeResultModal() {
    const overlay = document.getElementById('resModalOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        // სქროლის დაბრუნება
        document.body.classList.remove('no-scroll');
        document.documentElement.classList.remove('no-scroll');

        setTimeout(() => {
            const box = document.getElementById('resCardBox');
            if (box) box.classList.remove('loading');
        }, 300);
    }
}

// EXPORT / IMPORT
window.exportExcel = async () => {
    const { data: { session } } = await supabaseClient.auth.getSession();
    const rows = document.querySelectorAll('.db-row');
    if(!rows.length) return showToast("No data", "error");

    let data = [];
    // Fetch fresh data based on current filter
    let selectId = currentTable === 'tags' ? 'adminTagFilter' : currentTable === 'locations' ? 'adminLocFilter' : 'adminPersonFilter';
    let catVals = getMultiSelectValues(selectId);

    let query = supabaseClient.from(currentTable).select('*').eq('user_id', session.user.id);
    if (!catVals.includes('all') && catVals.length > 0) query = query.in(currentTable==='locations'?'country_code':'category', catVals);

    const { data: dbData } = await query;
    const ws = XLSX.utils.json_to_sheet(dbData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
    XLSX.writeFile(wb, `InstaDiscovery_${currentTable}.xlsx`);
};

window.importXLSX = async (input) => {
    const file = input.files[0];
    if(!file) return;
    const { data: { session } } = await supabaseClient.auth.getSession();

    const reader = new FileReader();
    reader.onload = async (e) => {
        const data = new Uint8Array(e.target.result);
        const wb = XLSX.read(data, {type:'array'});
        const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);

        let count = 0;
        for(let row of json) {
            let name = row.Name || row.name || row.Username || row.username || Object.values(row)[0];
            if(!name) continue;

            let obj = currentTable === 'people' ? {username: name} : {name: name};
            obj.user_id = session.user.id;
            let cat = row.Category || row.category || 'imported';

            if(currentTable === 'locations') {
                obj.country_code = cat.toLowerCase();
                if(row.Post_Count) obj.posts_count = String(row.Post_Count);
                if(row.Insta_ID) obj.insta_id = String(row.Insta_ID);
            } else {
                obj.category = cat.toLowerCase();
            }

            const { error } = await supabaseClient.from(currentTable).insert([obj]);
            if(!error) count++;
        }
        showToast(`Imported ${count} items`, "success");
        loadAdminData(currentTable);
        input.value = '';
    };
    reader.readAsArrayBuffer(file);
};



// 🛑 PLATFORM UI SWITCHER (Smart Swap V5 - Complete Logic)
// 🛑 PLATFORM UI SWITCHER (Smart Swap V6 - Icons & Logic)
function updatePlatformUI(manualOverride = false) {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        if (!tabs || !tabs[0]) return;

        const url = tabs[0].url;

        // 1. თუ ხელით არ გადაგვირთავს, ვამოწმებთ URL-ს ავტომატურად
        if (!manualOverride) {
            if (url.includes("instagram.com")) currentPlatform = 'instagram';
            else if (url.includes("facebook.com")) currentPlatform = 'facebook';
            else if (url.includes("youtube.com")) currentPlatform = 'youtube';
            // თუ არცერთია, რჩება ის რაც იყო ბოლოს არჩეული (ან დეფოლტი)
        }

        // 2. აიკონების ვიზუალური განახლება
        document.querySelectorAll('.plat-btn').forEach(btn => {
            if (btn.dataset.platform === currentPlatform) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // 3. UI ელემენტების ლოგიკა (ფილტრების გამოჩენა/დამალვა)
        // ვიყენებთ `currentPlatform`-ს URL-ის მაგივრად!

        const isYoutube = currentPlatform === 'youtube';
        const isInstagram = currentPlatform === 'instagram';
        const isFacebook = currentPlatform === 'facebook';

        // --- TAGS ---
        const recentWrapperTag = document.getElementById('recentWrapperTag');
        const recentBoxTag = document.getElementById('recentCheckTag');
        const ytDateWrapperTag = document.getElementById('ytDateWrapper');
        const ytDateSelectTag = document.getElementById('ytDateSelect');
        const isKeywordTag = document.getElementById('noHashCheck')?.checked;

        // --- LOCS ---
        const recentWrapperLoc = document.getElementById('recentWrapperLoc');
        const recentBoxLoc = document.getElementById('recentCheckLoc');
        const ytDateWrapperLoc = document.getElementById('ytDateWrapperLoc');

        // --- PEOPLE ---
        const ytSortWrapperPerson = document.getElementById('ytSortWrapperPerson');

        // LOGIC: TAGS
        if (recentWrapperTag && ytDateWrapperTag) {
            if (isYoutube) {
                recentWrapperTag.classList.add('hidden');
                ytDateWrapperTag.classList.remove('hidden');

                if (isKeywordTag) {
                    ytDateSelectTag.disabled = false;
                    ytDateWrapperTag.style.opacity = "1";
                    ytDateWrapperTag.style.pointerEvents = "auto";
                } else {
                    ytDateSelectTag.disabled = true;
                    ytDateWrapperTag.style.opacity = "0.4";
                    ytDateWrapperTag.style.pointerEvents = "none";
                    ytDateSelectTag.value = "";
                }
            } else {
                ytDateWrapperTag.classList.add('hidden');
                recentWrapperTag.classList.remove('hidden');

                if (isFacebook) {
                    if (isKeywordTag) {
                        recentBoxTag.disabled = false;
                        recentWrapperTag.style.opacity = "1";
                        recentWrapperTag.style.pointerEvents = "auto";
                    } else {
                        recentBoxTag.disabled = true;
                        recentBoxTag.checked = false;
                        recentWrapperTag.style.opacity = "0.3";
                        recentWrapperTag.style.pointerEvents = "none";
                    }
                } else if (isInstagram) {
                    recentBoxTag.disabled = true;
                    recentWrapperTag.style.opacity = "0.3";
                    recentWrapperTag.style.pointerEvents = "none";
                }
            }
        }

        // LOGIC: LOCATIONS
        if (recentWrapperLoc && ytDateWrapperLoc) {
            if (isYoutube) {
                recentWrapperLoc.classList.add('hidden');
                ytDateWrapperLoc.classList.remove('hidden');
            } else {
                ytDateWrapperLoc.classList.add('hidden');
                recentWrapperLoc.classList.remove('hidden');
                recentBoxLoc.disabled = false;
                recentWrapperLoc.style.opacity = "1";
                recentWrapperLoc.style.pointerEvents = "auto";
            }
        }

        // LOGIC: PEOPLE
        if (ytSortWrapperPerson) {
            if (isYoutube) {
                ytSortWrapperPerson.classList.remove('hidden');
            } else {
                ytSortWrapperPerson.classList.add('hidden');
            }
        }
    });
// 🛑 პლატფორმის შეცვლისას ეგრევე განვაახლოთ ბაზის ვიუ და რაოდენობები (თუ ღიაა)
    updateGlobalCounts();
    if (document.getElementById('adminModal') && !document.getElementById('adminModal').classList.contains('hidden')) {
        loadAdminData(currentTable);
    }
    
}
// 🛑 DELETE FUNCTION (ეს გაკლდა!)
async function deleteItem(table, id) {
    // 1. ვეკითხებით დასტურს (შენივე askConfirm ფუნქციით)
    const confirmed = await askConfirm("Delete Item?", "Are you sure you want to remove this?");

    if (confirmed) {
        // 2. ვშლით Supabase-დან
        const { error } = await supabaseClient
            .from(table)
            .delete()
            .eq('id', id);

        if (error) {
            showToast("Error deleting: " + error.message, "error");
        } else {
            showToast("Item deleted successfully", "success");

            // 3. ვანახლებთ სიას და მთვლელებს
            loadAdminData(table); // განაახლებს მიმდინარე ცხრილს
            updateGlobalCounts(); // განაახლებს წითელ ბეჯებს ტაბებზე

            // 4. ვაგზავნით სიგნალს Content Script-ში (რომ იქაც განახლდეს ფერები)
            chrome.tabs.query({active: true, currentWindow: true}, function(tabs) {
                if(tabs[0]) {
                    chrome.tabs.sendMessage(tabs[0].id, {action: "refresh_config"});
                }
            });
        }
    }
}

// ==========================================================
// ⭐ PLAYLIST SYSTEM
// ==========================================================

function handlePlaylistChange(section) {
    const filterId = section === 'tags' ? 'playlistTagFilter' : section === 'locations' ? 'playlistLocFilter' : 'playlistPersonFilter';
    const sel = document.getElementById(filterId);
    if (sel.value === '__manage__') {
        sel.value = '';
        openPlaylistManager(section);
        return;
    }
    loadAdminData(section);
}

async function loadAllPlaylists() {
    const { data: { session } } = await supabaseClient.auth.getSession();
    if (!session) return;
    const { data } = await supabaseClient.from('playlists').select('*').eq('user_id', session.user.id).order('name');
    if (!data) return;

    cachedPlaylists = { tags: [], locations: [], people: [] };
    data.forEach(p => {
        if (cachedPlaylists[p.section]) cachedPlaylists[p.section].push(p);
    });

    const { data: allItems } = await supabaseClient.from('playlist_items').select('item_id').eq('user_id', session.user.id);
    globalFavoritedItems = new Set((allItems || []).map(x => x.item_id));

    populatePlaylistDropdown('playlistTagFilter', cachedPlaylists.tags);
    populatePlaylistDropdown('playlistLocFilter', cachedPlaylists.locations);
    populatePlaylistDropdown('playlistPersonFilter', cachedPlaylists.people);
}

function populatePlaylistDropdown(id, playlists) {
    const sel = document.getElementById(id);
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">☆ All</option>';
    playlists.forEach(p => {
        sel.innerHTML += `<option value="${p.id}">★ ${p.name}</option>`;
    });
    if (playlists.length > 0) {
        sel.innerHTML += '<option value="__manage__">⚙ Manage...</option>';
    }
    if (current) sel.value = current;
}

async function openPlaylistPopup(itemId, section) {
    pendingPlaylistItemId = itemId;
    const modal = document.getElementById('playlistModal');
    const list = document.getElementById('playlistList');
    document.getElementById('newPlaylistName').value = '';

    const { data: { session } } = await supabaseClient.auth.getSession();
    if (!session) return;

    // Get playlists for this section
    const playlists = cachedPlaylists[section] || [];

    // Get which playlists this item is already in
    const { data: existing } = await supabaseClient.from('playlist_items').select('playlist_id').eq('item_id', itemId).eq('user_id', session.user.id);
    const inPlaylists = new Set((existing || []).map(e => e.playlist_id));

    if (playlists.length === 0) {
        list.innerHTML = '<div style="padding:10px; text-align:center; opacity:0.5; font-size:0.85rem;">No playlists yet. Create one below.</div>';
    } else {
        list.innerHTML = playlists.map(p => {
            const isIn = inPlaylists.has(p.id);
            return `<div class="playlist-row ${isIn ? 'in-playlist' : ''}" data-pid="${p.id}">
                        <span class="playlist-star">${isIn ? '★' : '☆'}</span>
                        <span class="playlist-name">${p.name}</span>
                    </div>`;
        }).join('');
    }

    // Toggle item in/out of playlist on click
    list.querySelectorAll('.playlist-row').forEach(row => {
        row.onclick = async () => {
            const pid = parseInt(row.dataset.pid);
            const isIn = row.classList.contains('in-playlist');
            if (isIn) {
                await supabaseClient.from('playlist_items').delete().eq('playlist_id', pid).eq('item_id', itemId);
                row.classList.remove('in-playlist');
                row.querySelector('.playlist-star').textContent = '☆';
                
                // check if it's still in other playlists
                const { data: stillIn } = await supabaseClient.from('playlist_items').select('id').eq('item_id', itemId).limit(1);
                if (!stillIn || stillIn.length === 0) {
                    globalFavoritedItems.delete(itemId);
                    const btn = document.querySelector(`.btn-fav-item[data-id="${itemId}"]`);
                    if (btn) btn.innerHTML = '<i class="fa-regular fa-star"></i>';
                }
                
                showToast('Removed from playlist', 'success');
            } else {
                await supabaseClient.from('playlist_items').insert([{ playlist_id: pid, item_id: itemId, user_id: session.user.id }]);
                row.classList.add('in-playlist');
                row.querySelector('.playlist-star').textContent = '★';
                
                globalFavoritedItems.add(itemId);
                const btn = document.querySelector(`.btn-fav-item[data-id="${itemId}"]`);
                if (btn) btn.innerHTML = '<i class="fa-solid fa-star" style="color:#FFD700;"></i>';
                
                showToast('Added to playlist!', 'success');
            }
        };
    });

    // Store section for create
    modal.dataset.section = section;
    modal.classList.remove('hidden');
}

async function createPlaylistFromModal() {
    const nameInput = document.getElementById('newPlaylistName');
    const name = nameInput.value.trim();
    if (!name) return;

    const modal = document.getElementById('playlistModal');
    const section = modal.dataset.section;

    const { data: { session } } = await supabaseClient.auth.getSession();
    if (!session) return;

    const { data, error } = await supabaseClient.from('playlists').insert([{ name, section, user_id: session.user.id }]).select().single();
    if (error) { showToast('Error creating playlist', 'error'); return; }

    nameInput.value = '';
    await loadAllPlaylists();
    showToast(`Playlist "${name}" created!`, 'success');

    // If we have a pending item, re-open popup to show new playlist
    if (pendingPlaylistItemId) {
        openPlaylistPopup(pendingPlaylistItemId, section);
    }
}

async function openPlaylistManager(section) {
    const modal = document.getElementById('playlistManageModal');
    const list = document.getElementById('playlistManageList');
    const playlists = cachedPlaylists[section] || [];

    if (playlists.length === 0) {
        list.innerHTML = '<div style="padding:10px; text-align:center; opacity:0.5; font-size:0.85rem;">No playlists for this section.</div>';
    } else {
        list.innerHTML = playlists.map(p => `
            <div class="playlist-manage-row" data-pid="${p.id}">
                <span class="playlist-name" style="flex:1;">${p.name}</span>
                <button class="btn-rename-pl" data-pid="${p.id}" title="Rename"><i class="fa-solid fa-pen" style="font-size:11px;"></i></button>
                <button class="btn-delete-pl" data-pid="${p.id}" title="Delete"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
            </div>
        `).join('');

        list.querySelectorAll('.btn-rename-pl').forEach(btn => {
            btn.onclick = async () => {
                const pid = parseInt(btn.dataset.pid);
                const pl = playlists.find(p => p.id === pid);
                const newName = prompt('Rename playlist:', pl?.name);
                if (!newName || !newName.trim()) return;
                await supabaseClient.from('playlists').update({ name: newName.trim() }).eq('id', pid);
                await loadAllPlaylists();
                openPlaylistManager(section);
                showToast('Renamed!', 'success');
            };
        });

        list.querySelectorAll('.btn-delete-pl').forEach(btn => {
            btn.onclick = async () => {
                const pid = parseInt(btn.dataset.pid);
                if (!confirm('Delete this playlist? Items will NOT be deleted.')) return;
                await supabaseClient.from('playlists').delete().eq('id', pid);
                await loadAllPlaylists();
                openPlaylistManager(section);
                loadAdminData(section);
                showToast('Playlist deleted', 'success');
            };
        });
    }

    modal.classList.remove('hidden');
}

