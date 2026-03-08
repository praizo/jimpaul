<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Real-time task search with live filtering, keyboard navigation, and highlighted results">
    <title>Task Search — Jim Paul</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/search.css">
</head>
<body>
    <div class="search-container">
        <header class="search-header">
            <h1 class="search-title">Task Search</h1>
            <p class="search-subtitle">Find tasks across all your projects instantly</p>
        </header>

        <div class="search-input-wrapper" role="combobox" aria-expanded="false" aria-haspopup="listbox">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
            </svg>
            <input
                type="text"
                id="search-input"
                class="search-input"
                placeholder="Search tasks by title or description..."
                autocomplete="off"
                aria-label="Search tasks"
                aria-controls="search-results"
                aria-autocomplete="list"
            >
            <div id="search-spinner" class="search-spinner hidden" aria-hidden="true">
                <svg class="spinner-svg" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.2"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                </svg>
            </div>
            <kbd class="search-shortcut" id="search-shortcut">Ctrl+K</kbd>
        </div>

        <div id="search-status" class="search-status" aria-live="polite"></div>

        <div id="search-results" class="search-results" role="listbox" aria-label="Search results"></div>

        <div id="search-empty" class="search-empty hidden">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="empty-icon">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
                <path d="M8 11h6"></path>
            </svg>
            <p class="empty-title">No tasks found</p>
            <p class="empty-description">Try adjusting your search query or check for typos.</p>
        </div>

        <div id="search-error" class="search-error hidden">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="error-icon">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 8v4"></path>
                <path d="M12 16h.01"></path>
            </svg>
            <p class="error-title">Something went wrong</p>
            <p class="error-description" id="error-message">Unable to load search results. Please try again.</p>
            <button class="retry-button" id="retry-button">Retry</button>
        </div>

        <footer class="search-footer" id="search-footer">
            <div class="keyboard-hints">
                <span class="hint"><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                <span class="hint"><kbd>Enter</kbd> Select</span>
                <span class="hint"><kbd>Esc</kbd> Clear</span>
            </div>
        </footer>
    </div>

    <script src="/js/search.js"></script>
</body>
</html>
