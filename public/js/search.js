/**
 * Task Search — Real-Time Search UI
 * ===================================
 * Vanilla JS implementation with:
 * - Debounced input (300ms) to reduce API calls
 * - Highlighted matched terms in results
 * - Full keyboard navigation (↑↓ Arrow, Enter, Escape)
 * - Loading, empty, and error state management
 * - Accessible ARIA attributes
 *
 * Architecture:
 *   User input → debounce → fetch API → render results → keyboard nav
 *
 * UI Reasoning:
 *   - 300ms debounce is the sweet spot: fast enough to feel "live",
 *     slow enough to avoid hammering the API on every keystroke.
 *   - Keyboard nav uses activeIndex tracking instead of DOM focus
 *     to maintain input focus (user can keep typing while navigating).
 *   - Results are rendered via innerHTML for simplicity; in production,
 *     use a virtual DOM or template literals with sanitization.
 */

(function () {
    "use strict";

    // ── Configuration ──────────────────────────────────────────────
    const CONFIG = {
        apiUrl: "/api/v1/tasks/search",
        debounceMs: 300,
        minQueryLength: 2,
        perPage: 15,
    };

    // ── DOM Elements ───────────────────────────────────────────────
    const elements = {
        input: document.getElementById("search-input"),
        results: document.getElementById("search-results"),
        spinner: document.getElementById("search-spinner"),
        status: document.getElementById("search-status"),
        empty: document.getElementById("search-empty"),
        error: document.getElementById("search-error"),
        errorMessage: document.getElementById("error-message"),
        retryButton: document.getElementById("retry-button"),
        shortcut: document.getElementById("search-shortcut"),
        footer: document.getElementById("search-footer"),
        wrapper: document.querySelector(".search-input-wrapper"),
    };

    // ── State ──────────────────────────────────────────────────────
    let activeIndex = -1;
    let currentResults = [];
    let debounceTimer = null;
    let abortController = null;
    let lastQuery = "";

    // ── Debounce ───────────────────────────────────────────────────
    /**
     * Debounces a function call by the configured delay.
     * Clears any pending timer before setting a new one.
     * This prevents excessive API calls while the user is still typing.
     */
    function debounce(fn, delay) {
        return function (...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ── API Fetch ──────────────────────────────────────────────────
    /**
     * Fetches search results from the API.
     * Uses AbortController to cancel in-flight requests when a new query arrives.
     */
    async function fetchResults(query) {
        // Cancel any pending request
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        const url = `${CONFIG.apiUrl}?q=${encodeURIComponent(query)}&per_page=${CONFIG.perPage}`;

        try {
            showSpinner();
            hideStates();

            const response = await fetch(url, {
                signal: abortController.signal,
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}: ${response.statusText}`,
                );
            }

            const json = await response.json();

            hideSpinner();

            if (json.success && json.data.length > 0) {
                currentResults = json.data;
                renderResults(json.data, query);
                updateStatus(
                    `${json.meta.total} result${json.meta.total !== 1 ? "s" : ""} found`,
                );
            } else {
                currentResults = [];
                showEmpty();
                updateStatus("No results");
            }
        } catch (err) {
            hideSpinner();

            if (err.name === "AbortError") {
                return; // Request was cancelled — ignore
            }

            currentResults = [];
            showError(err.message);
            updateStatus("Search failed");
        }
    }

    // ── Render Results ─────────────────────────────────────────────
    /**
     * Renders search results to the DOM with highlighted matched terms.
     * Each result is an interactive listbox option for accessibility.
     */
    function renderResults(results, query) {
        activeIndex = -1;
        elements.results.innerHTML = results
            .map((task, index) => {
                const title = highlightMatch(escapeHtml(task.title), query);
                const project =
                    task.task_group?.project?.name || "Unknown Project";
                const group = task.task_group?.name || "";

                const labelsHtml = (task.labels || [])
                    .map(
                        (label) =>
                            `<span class="label-badge" style="background:${hexToRgba(label.color, 0.15)};color:${label.color}">${escapeHtml(label.name)}</span>`,
                    )
                    .join("");

                return `
                <div class="result-item"
                     role="option"
                     id="result-${index}"
                     aria-selected="false"
                     tabindex="-1"
                     data-index="${index}">
                    <span class="result-status-dot ${task.status || "pending"}" title="${task.status}"></span>
                    <div class="result-content">
                        <div class="result-title">${title}</div>
                        <div class="result-meta">
                            <span class="result-project">${escapeHtml(project)}</span>
                            ${group ? `<span class="result-separator">›</span><span>${escapeHtml(group)}</span>` : ""}
                            ${labelsHtml ? `<span class="result-separator">·</span><span class="result-labels">${labelsHtml}</span>` : ""}
                        </div>
                    </div>
                    <span class="result-priority priority-${task.priority || "medium"}">${task.priority || "medium"}</span>
                </div>
            `;
            })
            .join("");

        elements.wrapper.setAttribute("aria-expanded", "true");
        elements.footer.classList.remove("hidden");
    }

    // ── Highlight Matched Terms ────────────────────────────────────
    /**
     * Wraps matched query terms in <mark> tags for visual highlighting.
     * Splits multi-word queries so each word is highlighted independently.
     * Case-insensitive matching.
     */
    function highlightMatch(text, query) {
        if (!query || !text) return text;

        const words = query
            .trim()
            .split(/\s+/)
            .filter((w) => w.length > 0);
        const pattern = words.map((w) => escapeRegex(w)).join("|");
        const regex = new RegExp(`(${pattern})`, "gi");

        return text.replace(regex, '<mark class="highlight">$1</mark>');
    }

    // ── Keyboard Navigation ────────────────────────────────────────
    /**
     * Handles keyboard events for navigation:
     * - ArrowDown: Move to next result
     * - ArrowUp: Move to previous result
     * - Enter: Select the active result
     * - Escape: Clear input and results
     *
     * Active tracking is index-based to keep focus in the input field,
     * allowing users to refine their query while navigating.
     */
    function handleKeydown(e) {
        const resultCount = currentResults.length;

        switch (e.key) {
            case "ArrowDown":
                e.preventDefault();
                if (resultCount > 0) {
                    activeIndex = (activeIndex + 1) % resultCount;
                    updateActiveResult();
                }
                break;

            case "ArrowUp":
                e.preventDefault();
                if (resultCount > 0) {
                    activeIndex =
                        activeIndex <= 0 ? resultCount - 1 : activeIndex - 1;
                    updateActiveResult();
                }
                break;

            case "Enter":
                e.preventDefault();
                if (activeIndex >= 0 && currentResults[activeIndex]) {
                    selectResult(currentResults[activeIndex]);
                }
                break;

            case "Escape":
                e.preventDefault();
                clearSearch();
                break;
        }
    }

    /**
     * Updates visual active state on the selected result item.
     * Scrolls the active item into view for long result lists.
     */
    function updateActiveResult() {
        const items = elements.results.querySelectorAll(".result-item");

        items.forEach((item, index) => {
            const isActive = index === activeIndex;
            item.classList.toggle("active", isActive);
            item.setAttribute("aria-selected", isActive ? "true" : "false");
        });

        // Scroll active item into view
        const activeEl = items[activeIndex];
        if (activeEl) {
            activeEl.scrollIntoView({ block: "nearest", behavior: "smooth" });
            elements.input.setAttribute(
                "aria-activedescendant",
                `result-${activeIndex}`,
            );
        }
    }

    /**
     * Handles selection of a result (via Enter key or click).
     * In a real app, this would navigate to the task detail page.
     */
    function selectResult(task) {
        console.log("Selected task:", task);
        alert(
            `Selected: ${task.title}\nStatus: ${task.status}\nPriority: ${task.priority}`,
        );
    }

    // ── State Management ───────────────────────────────────────────
    function showSpinner() {
        elements.spinner.classList.remove("hidden");
        elements.shortcut.classList.add("hidden");
    }

    function hideSpinner() {
        elements.spinner.classList.add("hidden");
        elements.shortcut.classList.remove("hidden");
    }

    function showEmpty() {
        elements.results.innerHTML = "";
        elements.empty.classList.remove("hidden");
        elements.error.classList.add("hidden");
    }

    function showError(message) {
        elements.results.innerHTML = "";
        elements.empty.classList.add("hidden");
        elements.error.classList.remove("hidden");
        elements.errorMessage.textContent =
            message || "Unable to load search results. Please try again.";
    }

    function hideStates() {
        elements.empty.classList.add("hidden");
        elements.error.classList.add("hidden");
    }

    function updateStatus(text) {
        elements.status.textContent = text;
    }

    function clearSearch() {
        elements.input.value = "";
        elements.results.innerHTML = "";
        currentResults = [];
        activeIndex = -1;
        lastQuery = "";
        hideStates();
        hideSpinner();
        updateStatus("");
        elements.wrapper.setAttribute("aria-expanded", "false");
        elements.input.removeAttribute("aria-activedescendant");
        elements.input.focus();
    }

    // ── Utilities ──────────────────────────────────────────────────
    function escapeHtml(str) {
        const div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    }

    function hexToRgba(hex, alpha) {
        if (!hex) return `rgba(99, 102, 241, ${alpha})`;
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    // ── Input Handler ──────────────────────────────────────────────
    const debouncedFetch = debounce((query) => {
        if (query === lastQuery) return; // Avoid duplicate requests
        lastQuery = query;
        fetchResults(query);
    }, CONFIG.debounceMs);

    function handleInput(e) {
        const query = e.target.value.trim();

        if (query.length < CONFIG.minQueryLength) {
            clearTimeout(debounceTimer);
            if (abortController) abortController.abort();
            elements.results.innerHTML = "";
            currentResults = [];
            activeIndex = -1;
            hideStates();
            hideSpinner();
            updateStatus(
                query.length > 0
                    ? `Type ${CONFIG.minQueryLength - query.length} more character${CONFIG.minQueryLength - query.length !== 1 ? "s" : ""}...`
                    : "",
            );
            elements.wrapper.setAttribute("aria-expanded", "false");
            lastQuery = "";
            return;
        }

        updateStatus("Searching...");
        debouncedFetch(query);
    }

    // ── Click Handling on Results ───────────────────────────────────
    function handleResultClick(e) {
        const item = e.target.closest(".result-item");
        if (item) {
            const index = parseInt(item.dataset.index, 10);
            if (currentResults[index]) {
                activeIndex = index;
                updateActiveResult();
                selectResult(currentResults[index]);
            }
        }
    }

    // ── Global Keyboard Shortcut (Ctrl+K) ──────────────────────────
    function handleGlobalKeydown(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === "k") {
            e.preventDefault();
            elements.input.focus();
            elements.input.select();
        }
    }

    // ── Initialize ─────────────────────────────────────────────────
    function init() {
        elements.input.addEventListener("input", handleInput);
        elements.input.addEventListener("keydown", handleKeydown);
        elements.results.addEventListener("click", handleResultClick);
        elements.retryButton.addEventListener("click", () => {
            if (lastQuery) {
                fetchResults(lastQuery);
            }
        });
        document.addEventListener("keydown", handleGlobalKeydown);

        // Focus input on page load
        elements.input.focus();
    }

    // Start
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
