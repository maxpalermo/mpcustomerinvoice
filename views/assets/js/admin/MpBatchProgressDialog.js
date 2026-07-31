/**
 * MpBatchProgressDialog.js
 * Componente JS indipendente e riutilizzabile per la gestione di elaborazioni AJAX a blocchi
 * con barra di progresso, log in tempo reale e pulsante di STOP/Interruzione.
 *
 * @author Massimiliano Palermo
 */
class MpBatchProgressDialog {
    constructor(options = {}) {
        this.dialogId = options.dialogId || "mp-batch-progress-dialog";
        this.title = options.title || "Elaborazione in Corso";
        this.onComplete = options.onComplete || null;
        this.onStop = options.onStop || null;

        this.dialog = null;
        this.isStopped = false;
        this.isRunning = false;
        this.abortController = null;

        this.totalCount = 0;
        this.processedCount = 0;
        this.successCount = 0;
        this.errorCount = 0;

        this.initDOM();
        this.bindEvents();
        this.initGrowlObserver();
    }

    initDOM() {
        let existing = document.getElementById(this.dialogId);
        if (existing) {
            this.dialog = existing;
            return;
        }

        const dialogHtml = `
        <dialog id="${this.dialogId}" class="mp-batch-dialog" style="border:none; border-radius:12px; padding:0; width:650px; max-width:92vw; box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.4); background:#ffffff; overflow:hidden;">
            <style>
                #${this.dialogId}::backdrop {
                    background: rgba(15, 23, 42, 0.65);
                    backdrop-filter: blur(4px);
                }
                #${this.dialogId} .dialog-header {
                    background: #1e293b;
                    color: #ffffff;
                    padding: 16px 24px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                #${this.dialogId} .dialog-body {
                    padding: 24px;
                    background: #f8fafc;
                }
                #${this.dialogId} .dialog-footer {
                    padding: 16px 24px;
                    background: #ffffff;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: flex-end;
                    gap: 12px;
                }
                #${this.dialogId} .stat-box {
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 10px 14px;
                    text-align: center;
                    flex: 1;
                }
                #${this.dialogId} .stat-box .stat-label {
                    font-size: 0.75rem;
                    text-transform: uppercase;
                    color: #64748b;
                    font-weight: 600;
                    margin-bottom: 2px;
                }
                #${this.dialogId} .stat-box .stat-value {
                    font-size: 1.25rem;
                    font-weight: 700;
                    color: #0f172a;
                }
                #${this.dialogId} .batch-log-container {
                    background: #0f172a;
                    color: #f1f5f9;
                    border-radius: 8px;
                    padding: 14px;
                    max-height: 200px;
                    overflow-y: auto;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                    font-size: 0.82rem;
                    line-height: 1.6;
                    border: 1px solid #334155;
                }
                #${this.dialogId} .log-entry {
                    margin-bottom: 4px;
                    word-break: break-all;
                }
                #${this.dialogId} .log-entry.success { color: #4ade80; }
                #${this.dialogId} .log-entry.danger { color: #f87171; }
                #${this.dialogId} .log-entry.warning { color: #fbbf24; }
                #${this.dialogId} .log-entry.info { color: #38bdf8; }
            </style>
            
            <div class="dialog-header">
                <h5 class="mb-0 d-flex align-items-center font-weight-bold" style="font-size: 1.1rem; color: #ffffff;">
                    <i class="material-icons text-primary mr-2" style="font-size: 24px;">published_with_changes</i>
                    <span class="dialog-title">${this.escape(this.title)}</span>
                </h5>
                <button type="button" class="close text-light btn-dialog-close-x" style="opacity: 0.8; font-size: 1.5rem; outline: none; background: none; border: none;" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="dialog-body">
                <!-- Stat Summary Boxes -->
                <div class="d-flex gap-3 mb-3">
                    <div class="stat-box">
                        <div class="stat-label">Totali</div>
                        <div class="stat-value stat-total">0</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Elaborati</div>
                        <div class="stat-value stat-processed text-primary">0</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Successi</div>
                        <div class="stat-value stat-success text-success">0</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Errori</div>
                        <div class="stat-value stat-error text-danger">0</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small font-weight-bold text-secondary status-text">Inizializzazione...</span>
                        <span class="small font-weight-bold text-primary progress-percentage">0%</span>
                    </div>
                    <div class="progress" style="height: 20px; border-radius: 10px; background-color: #e2e8f0; overflow: hidden;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; font-weight: bold; font-size: 0.8rem; line-height: 20px;">0%</div>
                    </div>
                </div>

                <!-- Live Log Container -->
                <div class="batch-log-container">
                    <div class="log-entry info">Pronto per avviare l'elaborazione...</div>
                </div>
            </div>

            <div class="dialog-footer">
                <button type="button" class="btn btn-danger font-weight-bold btn-stop-batch">
                    <i class="material-icons align-middle mr-1" style="font-size: 18px;">stop</i>
                    STOP (Interrompi)
                </button>
                <button type="button" class="btn btn-secondary font-weight-bold btn-close-batch" style="display: none;">
                    <i class="material-icons align-middle mr-1" style="font-size: 18px;">close</i>
                    Chiudi
                </button>
            </div>
        </dialog>
        `;

        document.body.insertAdjacentHTML("beforeend", dialogHtml);
        this.dialog = document.getElementById(this.dialogId);
    }

    bindEvents() {
        if (!this.dialog) return;

        const btnStop = this.dialog.querySelector(".btn-stop-batch");
        const btnClose = this.dialog.querySelector(".btn-close-batch");
        const btnCloseX = this.dialog.querySelector(".btn-dialog-close-x");

        if (btnStop) {
            btnStop.addEventListener("click", () => this.stop());
        }

        if (btnClose) {
            btnClose.addEventListener("click", () => this.close());
        }

        if (btnCloseX) {
            btnCloseX.addEventListener("click", () => {
                if (this.isRunning) {
                    this.stop();
                } else {
                    this.close();
                }
            });
        }
    }

    initGrowlObserver() {
        if (window.mpBatchGrowlObserverBound) return;
        window.mpBatchGrowlObserverBound = true;

        const teleportGrowls = () => {
            const openDialog = document.querySelector(`dialog.mp-batch-dialog[open]`);
            const growls = document.getElementById("growls");
            if (openDialog && growls && growls.parentElement !== openDialog) {
                openDialog.appendChild(growls);
                growls.style.position = "fixed";
                growls.style.top = "20px";
                growls.style.right = "20px";
                growls.style.zIndex = "999999";
            }
        };

        const observer = new MutationObserver(teleportGrowls);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    setTitle(newTitle) {
        this.title = newTitle;
        const titleEl = this.dialog.querySelector(".dialog-title");
        if (titleEl) titleEl.textContent = newTitle;
    }

    showModal() {
        if (this.dialog && typeof this.dialog.showModal === "function" && !this.dialog.open) {
            this.dialog.showModal();
        }
    }

    close() {
        if (this.dialog && this.dialog.open) {
            this.dialog.close();
        }
    }

    reset() {
        this.totalCount = 0;
        this.processedCount = 0;
        this.successCount = 0;
        this.errorCount = 0;
        this.isStopped = false;
        this.isRunning = true;
        this.abortController = new AbortController();

        this.dialog.querySelector(".stat-total").textContent = "0";
        this.dialog.querySelector(".stat-processed").textContent = "0";
        this.dialog.querySelector(".stat-success").textContent = "0";
        this.dialog.querySelector(".stat-error").textContent = "0";

        const bar = this.dialog.querySelector(".progress-bar");
        bar.style.width = "0%";
        bar.textContent = "0%";
        bar.className = "progress-bar progress-bar-striped progress-bar-animated bg-primary";

        this.dialog.querySelector(".progress-percentage").textContent = "0%";
        this.dialog.querySelector(".status-text").textContent = "Avvio elaborazione...";

        const logContainer = this.dialog.querySelector(".batch-log-container");
        logContainer.innerHTML = "";

        const btnStop = this.dialog.querySelector(".btn-stop-batch");
        const btnClose = this.dialog.querySelector(".btn-close-batch");
        if (btnStop) {
            btnStop.style.display = "inline-flex";
            btnStop.disabled = false;
        }
        if (btnClose) {
            btnClose.style.display = "none";
        }
    }

    updateProgressUI() {
        const percent = this.totalCount > 0 ? Math.round((this.processedCount / this.totalCount) * 100) : 0;

        this.dialog.querySelector(".stat-total").textContent = this.totalCount;
        this.dialog.querySelector(".stat-processed").textContent = this.processedCount;
        this.dialog.querySelector(".stat-success").textContent = this.successCount;
        this.dialog.querySelector(".stat-error").textContent = this.errorCount;

        const bar = this.dialog.querySelector(".progress-bar");
        bar.style.width = `${percent}%`;
        bar.textContent = `${percent}%`;

        this.dialog.querySelector(".progress-percentage").textContent = `${percent}%`;
        this.dialog.querySelector(".status-text").textContent = `Elaborazione: ${this.processedCount} di ${this.totalCount} ordini (${percent}%)`;
    }

    addLog(message, type = "info") {
        const logContainer = this.dialog.querySelector(".batch-log-container");
        if (!logContainer) return;

        const timeStr = new Date().toLocaleTimeString();
        const entry = document.createElement("div");
        entry.className = `log-entry ${type}`;
        entry.innerHTML = `<span style="opacity: 0.6;">[${timeStr}]</span> ${this.escape(message)}`;
        logContainer.appendChild(entry);
        logContainer.scrollTop = logContainer.scrollHeight;
    }

    stop() {
        if (this.isStopped) return;

        this.isStopped = true;
        if (this.abortController) {
            this.abortController.abort();
        }

        this.addLog("⚠️ Processo interrotto dall'utente.", "warning");

        const btnStop = this.dialog.querySelector(".btn-stop-batch");
        if (btnStop) {
            btnStop.disabled = true;
            btnStop.style.display = "none";
        }

        const btnClose = this.dialog.querySelector(".btn-close-batch");
        if (btnClose) {
            btnClose.style.display = "inline-flex";
        }

        this.dialog.querySelector(".status-text").textContent = "Processo interrotto dall'utente.";

        const bar = this.dialog.querySelector(".progress-bar");
        bar.className = "progress-bar bg-warning";

        this.isRunning = false;
        if (typeof this.onStop === "function") {
            this.onStop();
        }
    }

    finish() {
        this.isRunning = false;
        this.updateProgressUI();

        const bar = this.dialog.querySelector(".progress-bar");
        bar.classList.remove("progress-bar-animated", "progress-bar-striped");

        if (this.errorCount === 0 && !this.isStopped) {
            bar.className = "progress-bar bg-success";
            this.dialog.querySelector(".status-text").textContent = "Elaborazione completata con successo!";
            this.addLog("✓ Tutti gli ordini sono stati elaborati correttamente.", "success");
        } else if (!this.isStopped) {
            bar.className = "progress-bar bg-danger";
            this.dialog.querySelector(".status-text").textContent = `Elaborazione completata con ${this.errorCount} errori.`;
            this.addLog(`⚠️ Completato con ${this.errorCount} errori su ${this.totalCount} ordini.`, "danger");
        }

        const btnStop = this.dialog.querySelector(".btn-stop-batch");
        const btnClose = this.dialog.querySelector(".btn-close-batch");
        if (btnStop) btnStop.style.display = "none";
        if (btnClose) btnClose.style.display = "inline-flex";

        if (typeof this.onComplete === "function") {
            this.onComplete({
                total: this.totalCount,
                processed: this.processedCount,
                success: this.successCount,
                errors: this.errorCount,
                isStopped: this.isStopped
            });
        }
    }

    async runBatch(items, processItemCallback) {
        this.reset();
        this.showModal();

        this.totalCount = items.length;
        this.updateProgressUI();

        this.addLog(`Inizio elaborazione a blocchi per ${this.totalCount} ordini...`, "info");

        for (let i = 0; i < items.length; i++) {
            if (this.isStopped) {
                break;
            }

            const item = items[i];
            try {
                const res = await processItemCallback(item, this.abortController.signal);
                if (this.isStopped) break;

                this.processedCount++;
                if (res && res.success) {
                    this.successCount++;
                    this.addLog(`✓ ${res.message || 'Ordine elaborato con successo'}`, "success");
                } else {
                    this.errorCount++;
                    this.addLog(`✗ ${res.message || 'Errore durante l\'elaborazione dell\'ordine'}`, "danger");
                }
            } catch (err) {
                if (err.name === "AbortError" || this.isStopped) {
                    break;
                }
                this.processedCount++;
                this.errorCount++;
                this.addLog(`✗ Errore: ${err.message || err}`, "danger");
            }

            this.updateProgressUI();
        }

        if (!this.isStopped) {
            this.finish();
        }
    }

    escape(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
}

if (typeof window !== "undefined") {
    window.MpBatchProgressDialog = MpBatchProgressDialog;
}
