import re

file_path = "WordEvo/style.css"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Find the start of the list view styles
start_idx = content.find("/* =========================================\n   LIST VIEW STYLES")
if start_idx != -1:
    content = content[:start_idx]

css_to_add = '''/* =========================================
   LIST VIEW STYLES
   ========================================= */
.card-container.list-view {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 0 10px;
    /* Prevent horizontal scroll */
    max-width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
}

.card-container.list-view .card {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
    max-width: 100%;
    padding: 12px 20px;
    gap: 20px;
    height: auto;
    box-sizing: border-box;
}

/* Flatten the .card-header so its children participate in .card flexbox */
.card-container.list-view .card-header {
    display: contents;
}

/* 1. Main Word */
.card-container.list-view .card-title-group {
    order: 1;
    display: flex;
    align-items: center !important;
    gap: 12px !important;
    flex: 0 0 200px; /* Fixed width to align nicely */
    min-width: 150px;
}
.card-container.list-view .card-title-group h2.word {
    font-size: 1.2rem;
    line-height: 1.2;
    margin: 0;
    word-break: break-word;
}

/* 2. Translation */
.card-container.list-view p.translation {
    order: 2;
    flex: 1 1 0; /* Grow and shrink */
    min-width: 0; /* VERY IMPORTANT to prevent flex blowout */
    margin: 0;
    font-size: 0.95rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 3. Tags */
.card-container.list-view .tags {
    order: 3;
    flex: 0 1 350px; /* Allow ample space for tags */
    min-width: 0;
    margin: 0;
    justify-content: flex-start; /* Align tags to left in their space */
    flex-wrap: wrap; /* allow wrap if many tags */
    gap: 5px;
}

/* 4. Progress */
.card-container.list-view .progress-bar-container {
    order: 4;
    position: relative;
    width: auto;
    height: auto;
    background: transparent;
    border-radius: 0;
    overflow: visible;
    flex-shrink: 0;
    display: flex;
    align-items: center;
}
.card-container.list-view .progress-bar {
    display: none;
}
.card-container.list-view .progress-label {
    position: relative;
    left: 0;
    top: 0;
    transform: none;
    font-size: 13px;
    font-weight: 700;
    color: #cdd6f4;
    text-shadow: none;
    background: rgba(255,255,255,0.05);
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.1);
}

/* 5. Actions */
.card-container.list-view .card-actions {
    order: 5;
    flex-shrink: 0;
    display: flex;
    gap: 10px;
}

/* Hover effect on toggle button */
.toolbar-btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Mobile responsiveness for list view */
@media (max-width: 900px) {
    .card-container.list-view .card {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .card-container.list-view .card-header {
        display: flex; /* Revert display contents */
        flex-direction: row;
        width: 100%;
        justify-content: space-between;
        align-items: center;
        order: 1; /* Whole header goes first */
    }
    .card-container.list-view .card-title-group {
        flex: 1;
        min-width: 0;
    }
    .card-container.list-view .card-actions {
        position: static; /* Flow normally inside card-header */
    }

    .card-container.list-view p.translation {
        order: 2;
        width: 100%;
        -webkit-line-clamp: unset; /* show full translation on mobile */
    }
    .card-container.list-view .tags {
        order: 3;
        width: 100%;
        flex: none;
        justify-content: flex-start;
    }
    .card-container.list-view .progress-bar-container {
        position: absolute;
        bottom: 15px;
        right: 15px;
        margin: 0;
    }
}
'''

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content + css_to_add)

print("Updated style.css successfully!")
