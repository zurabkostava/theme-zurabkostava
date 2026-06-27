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
/* Default (fallback) grid for older browsers */
.card-container.list-view {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 25px 10px; /* Added 25px padding top/bottom so it's not glued to header */
    max-width: 100%;
    overflow-x: hidden;
}

.card-container.list-view .card {
    display: grid;
    grid-template-columns: minmax(150px, max-content) minmax(150px, 1fr) auto auto auto;
    column-gap: 20px; /* Use grid column gap for clean spacing */
    align-items: center;
    width: 100%;
    max-width: 100%;
    padding: 12px 20px;
    box-sizing: border-box;
}

/* Modern browsers with subgrid support - PERFECT ALIGNMENT */
@supports (grid-template-columns: subgrid) {
    .card-container.list-view {
        display: grid;
        grid-template-columns: minmax(100px, max-content) minmax(150px, 1fr) auto auto auto;
        column-gap: 25px; /* Ensures nothing is glued to each other globally */
    }
    .card-container.list-view .card {
        grid-template-columns: subgrid;
        grid-column: 1 / -1;
        gap: 0; 
        padding: 12px 20px; /* Padding for the card row */
    }
}

/* Flatten the .card-header so its children participate in .card grid */
.card-container.list-view .card-header {
    display: contents;
}

/* 1. Main Word */
.card-container.list-view .card-title-group {
    grid-column: 1;
    grid-row: 1;
    display: flex;
    align-items: center !important;
    gap: 12px !important;
}
.card-container.list-view .card-title-group h2.word {
    font-size: 1.2rem;
    line-height: 1.2;
    margin: 0;
    word-break: break-word;
}

/* 2. Translation */
.card-container.list-view p.translation {
    grid-column: 2;
    grid-row: 1;
    margin: 0;
    font-size: 0.95rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    align-self: center;
}

/* 3. Tags */
.card-container.list-view .tags {
    grid-column: 3;
    grid-row: 1;
    display: flex;
    justify-content: flex-end; 
    flex-wrap: nowrap; /* Force 1 line */
    overflow: hidden;
    gap: 5px;
    align-self: center;
    max-width: 400px;
}

/* Grid View Visibility (shows max 3 tags) */
.card-container:not(.list-view) .tag-idx-3,
.card-container:not(.list-view) .tag-idx-4,
.card-container:not(.list-view) .tag-idx-5,
.card-container:not(.list-view) .tag-idx-6,
.card-container:not(.list-view) .tag-idx-7,
.card-container:not(.list-view) .tag-idx-8,
.card-container:not(.list-view) .tag-idx-9 {
    display: none !important;
}
.card-container:not(.list-view) .badge-list {
    display: none !important;
}

/* List View Visibility (shows max 5 tags) */
.card-container.list-view .tag-idx-5,
.card-container.list-view .tag-idx-6,
.card-container.list-view .tag-idx-7,
.card-container.list-view .tag-idx-8,
.card-container.list-view .tag-idx-9 {
    display: none !important;
}
.card-container.list-view .badge-grid {
    display: none !important;
}

/* 4. Progress */
.card-container.list-view .progress-bar-container {
    grid-column: 4;
    grid-row: 1;
    position: relative;
    width: auto;
    height: auto;
    background: transparent;
    border-radius: 0;
    display: flex;
    align-items: center;
    align-self: center;
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
    grid-column: 5;
    grid-row: 1;
    display: flex;
    gap: 10px;
    align-self: center;
}

/* Hover effect on toggle button */
.toolbar-btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Mobile responsiveness for list view */
@media (max-width: 900px) {
    .card-container.list-view {
        display: flex !important;
        flex-direction: column !important;
    }
    .card-container.list-view .card {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 15px !important;
    }
    
    .card-container.list-view .card-header {
        display: flex !important;
        flex-direction: row !important;
        width: 100% !important;
        justify-content: space-between !important;
        align-items: center !important;
    }
    .card-container.list-view .card-title-group,
    .card-container.list-view p.translation,
    .card-container.list-view .tags,
    .card-container.list-view .progress-bar-container,
    .card-container.list-view .card-actions {
        /* Reset grid row/column for mobile flex layout */
        grid-column: auto;
        grid-row: auto;
    }
    .card-container.list-view .card-title-group {
        margin: 0 !important;
        flex: 1 !important;
    }
    .card-container.list-view .card-actions {
        margin: 0 !important;
    }

    .card-container.list-view p.translation {
        width: 100% !important;
        -webkit-line-clamp: unset !important;
    }
    .card-container.list-view .tags {
        width: 100% !important;
        justify-content: flex-start !important;
        margin: 0 !important;
    }
    .card-container.list-view .progress-bar-container {
        position: absolute !important;
        bottom: 15px !important;
        right: 15px !important;
        margin: 0 !important;
    }
}
'''

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content + css_to_add)

print("Updated style.css successfully!")
