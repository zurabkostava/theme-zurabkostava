import os

css_to_add = '''
/* =========================================
   LIST VIEW STYLES
   ========================================= */
.card-container.list-view {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 0 10px;
}

.card-container.list-view .card {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: none;
    padding: 12px 20px;
    gap: 20px;
    height: auto;
}

/* Adjust components inside the card when in list view */
.card-container.list-view .card-header {
    flex: 1.5;
    margin-bottom: 0;
    align-items: center !important;
}
.card-container.list-view .card-title-group {
    align-items: center !important;
    gap: 12px !important;
}

.card-container.list-view .card-header h2.word {
    font-size: 1.2rem;
    line-height: 1.2;
}

.card-container.list-view p.translation {
    flex: 2;
    margin: 0;
    font-size: 0.95rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-container.list-view .tags {
    flex: 1.5;
    margin: 0;
    justify-content: flex-end;
}

/* Transform the progress bar into a badge */
.card-container.list-view .progress-bar-container {
    position: relative;
    width: auto;
    height: auto;
    background: transparent;
    border-radius: 0;
    overflow: visible;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    margin-left: 10px;
}
.card-container.list-view .progress-bar {
    display: none; /* Hide the bar */
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

/* Make sure progress text stands out */
.card-container.list-view .card-actions {
    flex-shrink: 0;
    display: flex;
    gap: 10px;
}

/* Hover effect on toggle button */
.toolbar-btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Mobile responsiveness for list view */
@media (max-width: 768px) {
    .card-container.list-view .card {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    .card-container.list-view .card-header,
    .card-container.list-view p.translation,
    .card-container.list-view .tags {
        width: 100%;
        justify-content: flex-start;
        flex: none;
        -webkit-line-clamp: unset;
    }
    .card-container.list-view .tags {
        justify-content: flex-start;
    }
    .card-container.list-view .progress-bar-container {
        position: absolute;
        top: 12px;
        right: 15px;
        margin: 0;
    }
    .card-container.list-view .card-actions {
        position: absolute;
        bottom: 15px;
        right: 15px;
    }
}
'''

file_path = "WordEvo/style.css"
with open(file_path, 'a', encoding='utf-8') as f:
    f.write(css_to_add)

print(f"Successfully appended {len(css_to_add)} characters to {file_path}")
