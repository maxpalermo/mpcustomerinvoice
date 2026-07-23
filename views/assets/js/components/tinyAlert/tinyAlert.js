function tinyAlert(message, type = "info", title = null, icon = null, position = "topright", timer = "5") {
    const normalizedType = String(type || "info").toLowerCase();
    const normalizedPosition = String(position || "topright").toLowerCase();

    const typeTitleMap = {
        info: "Info",
        success: "Successo",
        warning: "Attenzione",
        danger: "Errore",
        error: "Errore",
    };

    const typeIconMap = {
        info: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 6a1.25 1.25 0 1 1 0 2.5A1.25 1.25 0 0 1 12 8Zm1.5 12h-3v-1.5h1v-5h-1V12h3v6.5h1V20Z"/></svg>',
        success: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.05 14.4-3.7-3.7 1.4-1.4 2.3 2.3 4.65-4.65 1.4 1.4-6.05 6.05Z"/></svg>',
        warning: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 2 21h20L12 3Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="18" r="1" fill="currentColor"/></svg>',
        danger: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1.4 14-1.4-1.4L10.6 16 9.2 14.6 10.6 13.2 9.2 11.8l1.4-1.4 1.4 1.4 1.4-1.4 1.4 1.4-1.4 1.4 1.4 1.4L13.4 16Z"/></svg>',
        error: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1.4 14-1.4-1.4L10.6 16 9.2 14.6 10.6 13.2 9.2 11.8l1.4-1.4 1.4 1.4 1.4-1.4 1.4 1.4-1.4 1.4 1.4 1.4L13.4 16Z"/></svg>',
    };

    const actualTitle = title === null || title === undefined || String(title).trim() === "" ? null : String(title);
    const resolvedTitle = actualTitle === null ? typeTitleMap[normalizedType] || null : actualTitle;

    const resolvedIcon = (() => {
        if (icon === null || icon === undefined || String(icon).trim() === "") {
            return typeIconMap[normalizedType] || null;
        }
        return String(icon);
    })();

    const containerId = `mp-tiny-alert-container-${normalizedPosition}`;
    let container = document.getElementById(containerId);
    if (!container) {
        container = document.createElement("div");
        container.id = containerId;
        container.className = `mp-tiny-alert-container mp-tiny-alert-container--${normalizedPosition}`;
        document.body.appendChild(container);
    }

    const dialog = document.createElement("dialog");
    dialog.className = `mp-tiny-alert mp-tiny-alert--${normalizedType}`;

    const body = document.createElement("div");
    body.className = "mp-tiny-alert__body";

    if (resolvedIcon) {
        const iconEl = document.createElement("div");
        iconEl.className = "mp-tiny-alert__icon";
        iconEl.innerHTML = resolvedIcon;
        body.appendChild(iconEl);
    } else {
        dialog.classList.add("mp-tiny-alert--no-icon");
    }

    const content = document.createElement("div");
    content.className = "mp-tiny-alert__content";

    if (resolvedTitle) {
        const titleEl = document.createElement("div");
        titleEl.className = "mp-tiny-alert__title";
        titleEl.textContent = resolvedTitle;
        content.appendChild(titleEl);
    } else {
        dialog.classList.add("mp-tiny-alert--no-title");
    }

    const messageEl = document.createElement("div");
    messageEl.className = "mp-tiny-alert__message";

    if (message instanceof Node) {
        messageEl.appendChild(message);
    } else if (message && typeof message === "object" && "html" in message) {
        messageEl.innerHTML = String(message.html ?? "");
    } else {
        messageEl.textContent = String(message ?? "");
    }
    content.appendChild(messageEl);

    body.appendChild(content);
    dialog.appendChild(body);

    container.prepend(dialog);

    const prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    dialog.classList.add("mp-tiny-alert--enter");

    try {
        dialog.show();
    } catch (e) {
        dialog.setAttribute("open", "");
    }

    requestAnimationFrame(() => {
        dialog.classList.add("mp-tiny-alert--visible");
    });

    const parsedSeconds = Number(timer);
    const timeoutMs = Number.isFinite(parsedSeconds) && parsedSeconds > 0 ? Math.round(parsedSeconds * 1000) : 0;

    let closeTimeoutId = null;
    let remainingMs = timeoutMs;
    let startedAt = null;

    const clearCloseTimeout = () => {
        if (closeTimeoutId !== null) {
            window.clearTimeout(closeTimeoutId);
            closeTimeoutId = null;
        }
    };

    const scheduleClose = () => {
        if (prefersReducedMotion || timeoutMs <= 0) {
            return;
        }

        clearCloseTimeout();
        startedAt = Date.now();
        closeTimeoutId = window.setTimeout(() => {
            closeDialog();
        }, remainingMs);
    };

    const pauseTimer = () => {
        if (timeoutMs <= 0 || startedAt === null) {
            return;
        }
        const elapsed = Date.now() - startedAt;
        remainingMs = Math.max(0, remainingMs - elapsed);
        startedAt = null;
        clearCloseTimeout();
    };

    const resetTimer = () => {
        if (timeoutMs <= 0) {
            return;
        }
        remainingMs = timeoutMs;
        scheduleClose();
    };

    const closeDialog = () => {
        clearCloseTimeout();

        const removeNow = () => {
            try {
                dialog.close();
            } catch (e) {
                dialog.removeAttribute("open");
            }
            dialog.remove();
        };

        if (prefersReducedMotion) {
            removeNow();
            return;
        }

        dialog.classList.remove("mp-tiny-alert--visible");
        dialog.classList.add("mp-tiny-alert--leave");
        window.setTimeout(removeNow, 220);
    };

    dialog.addEventListener("mouseenter", () => {
        pauseTimer();
        resetTimer();
    });
    dialog.addEventListener("mouseleave", () => {
        scheduleClose();
    });

    dialog.addEventListener("click", () => {
        closeDialog();
    });

    if (timeoutMs > 0) {
        scheduleClose();
    }

    return dialog;
}
