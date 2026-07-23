function ElementFromHtml(html) {
    const template = document.createElement("template");
    template.innerHTML = html.trim();
    const element = template.content.firstElementChild;
    template.remove();

    return element;
}

function t(original) {
    console.log("Cerco ", original);

    const token = langJson[original];

    console.log("Token: ", token);

    if (token) {
        return token;
    }

    return original;
}

function evaluatePasswordStrength(value, options) {
    const password = String(value ?? "");
    const minLength = Number(options?.minLength ?? 0);
    const maxLength = Number(options?.maxLength ?? 0);
    const minScore = Number(options?.minScore ?? 0);
    const pattern = options?.pattern;

    const issues = [];

    if (minLength > 0 && password.length < minLength) {
        issues.push("minLength");
    }
    if (maxLength > 0 && password.length > maxLength) {
        issues.push("maxLength");
    }

    let patternOk = true;
    if (pattern) {
        try {
            const re = new RegExp(pattern);
            patternOk = re.test(password);
        } catch (e) {
            patternOk = true;
        }
        if (!patternOk) {
            issues.push("pattern");
        }
    }

    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasDigit = /\d/.test(password);
    const hasSymbol = /[^A-Za-z0-9]/.test(password);

    let score = 0;

    if (password.length >= Math.max(1, minLength)) score += 1;
    if (password.length >= Math.max(8, minLength)) score += 1;
    if (password.length >= Math.max(12, minLength)) score += 1;

    const varietyCount = [hasLower, hasUpper, hasDigit, hasSymbol].filter(Boolean).length;
    if (varietyCount >= 2) score += 1;
    if (varietyCount >= 3) score += 1;
    if (varietyCount >= 4) score += 1;

    if (pattern) {
        if (patternOk) score += 1;
    }

    score = Math.max(0, Math.min(5, Math.round(score)));

    const ok = issues.length === 0 && (Number.isFinite(minScore) ? score >= minScore : true);
    if (!ok && Number.isFinite(minScore) && score < minScore) {
        issues.push("minScore");
    }

    return {
        ok,
        score,
        issues,
        details: {
            length: password.length,
            hasLower,
            hasUpper,
            hasDigit,
            hasSymbol,
            patternOk,
            minLength,
            maxLength,
            minScore,
        },
    };
}

function bindPasswordStrengthChecker() {
    const form = document.querySelector("#customer-form");
    const scope = form || document;
    const pwd = scope.querySelector('input[type="password"], input[name="password"]');
    if (!pwd) return;

    const getConstraints = () => {
        const minLength = pwd.dataset.minlength;
        const maxLength = pwd.dataset.maxlength;
        const minScore = pwd.dataset.minscore;
        const pattern = pwd.pattern;

        return {
            minLength,
            maxLength,
            minScore,
            pattern,
        };
    };

    let feedback = scope.querySelector("#mp-password-strength");
    if (!feedback) {
        feedback = document.createElement("div");
        feedback.id = "mp-password-strength";
        feedback.style.marginTop = "6px";
        feedback.style.fontSize = "12px";
        feedback.style.opacity = "0.9";
        pwd.closest(".input-group").insertAdjacentElement("afterend", feedback);
    }

    const render = () => {
        const res = evaluatePasswordStrength(pwd.value, getConstraints());

        const scoreLabel = t("Strength: {n}/5").replace("{n}", String(res.score));
        const okLabel = res.ok ? "✓" : "✗";
        feedback.textContent = `${okLabel} ${scoreLabel}`;
        feedback.style.color = res.ok ? "#198754" : "#b02a37";
    };

    pwd.removeEventListener("input", render);
    pwd.addEventListener("input", render);
    pwd.removeEventListener("blur", render);
    pwd.addEventListener("blur", render);
    render();
}

function getFieldType() {
    const html = `
            <div class="form-group row ">
                <label class="col-md-3 form-control-label" for="field-type">${t("Type")}</label>
                <div class="col-md-6 js-input-column">
                <select id="field-type" class="form-control form-control-select" name="type">
                    <option value="" selected>${t("Choose, please")}</option>
                    <option value="ENTE">ENTE</option>
                    <option value="PARTITA_IVA">PARTITA IVA</option>
                    <option value="PRIVATO">PRIVATO</option>
                </select>
            </div>
            <div class="col-md-3 form-control-comment">${t("Choose...")}</div>
            </div>
        `;

    const el = ElementFromHtml(html);
    return el;
}

function getFormContainer() {
    const html = `<div id="customerInvoiceContainer"></div>`;
    const el = ElementFromHtml(html);

    return el;
}

function getHtmlElement(label, id, name, type, comment, maxlength = 255, required = false, requiredString = null) {
    const requiredAttr = required ? "required" : "";
    const requiredLabelClass = required ? "required" : "";
    if (!requiredString) {
        requiredString = required ? `<span class="text-danger">${t("Required")}</span>` : t("Optional");
    }
    const requiredText = requiredString;

    const html = `
            <div class="form-group row ">
                <label class="col-md-3 form-control-label ${requiredLabelClass}" for="${id}">${label}</label>
                <div class="col-md-6 js-input-column" data-group="customerInvoice">
                    <input id="${id}" class="form-control" name="${name}" type="${type}" value="" ${requiredAttr} maxlength="${maxlength}">
                    <span class="form-control-comment">${comment}</span>
                </div>

                <div class="col-md-3 form-control-comment">${requiredText}</div>
            </div>
        `;

    return ElementFromHtml(html);
}

function getDni() {
    return getHtmlElement(t("Tax code"), "dni", "dni", "text", t("(max {n} characters)").replace("{n}", "16"), 16, true);
}

function getVatNumber() {
    return getHtmlElement(t("VAT number"), "vat_number", "vat_number", "text", t("(max {n} characters)").replace("{n}", "16"), 16, true);
}

function getCIG() {
    return getHtmlElement(t("Contract Identification Code (CIG)"), "cig", "cig", "text", t("(max {n} characters)").replace("{n}", "10"), 10, true);
}

function getCUP() {
    return getHtmlElement(t("Unique Project Code (CUP)"), "cup", "cup", "text", t("(max {n} characters)").replace("{n}", "15"), 15, true);
}

function getSDI() {
    return getHtmlElement(t("Interchange System (SDI)"), "sdi", "sdi", "text", t("(max {n} characters)").replace("{n}", "7"), 7, true);
}

function getPEC() {
    return getHtmlElement(t("Certified email (PEC)"), "pec", "pec", "email", "", 255, false);
}

function setTitle(title) {
    const el = document.createElement("h3");
    el.className = "title";
    el.textContent = title;

    return el;
}

function getCountries() {
    const group = document.createElement("div");
    group.classList = "form-group row";

    const l = document.createElement("label");
    l.setAttribute("for", "country");
    l.classList = "col-md-3 form-control-label required";
    l.innerText = t("Country");

    const div = document.createElement("div");
    div.classList = "col-md-6 js-input-column";
    div.dataset.group = "customerInvoice";

    const req = document.createElement("div");
    req.classList = "col-md-3 form-control-comment";
    req.innerHTML = `<span class="text-danger">${t("Required")}</span>`;

    const el = document.createElement("select");
    el.dataset.group = "customerInvoice";
    el.id = "country";
    el.name = "country";
    el.classList = "form-control chosen";

    const optionsHtml = Object.values(countriesJson)
        .map((c) => `<option value="${c.id_country}">${c.name}</option>`)
        .join("");
    el.innerHTML = `<option value="">${t("Choose...")}</option>${optionsHtml}`;

    div.appendChild(el);

    group.appendChild(l);
    group.appendChild(div);
    group.appendChild(req);

    el.removeEventListener("change", onChangeCountry);
    el.addEventListener("change", onChangeCountry);

    return group;
}

function getAddress() {
    const container = document.createElement("div");
    container.setAttribute("id", "invoiceAddress");
    container.dataset.group = "customerInvoice";
    container.style.paddingTop = "1rem";
    container.style.paddingBottom = "1rem";

    const company = getHtmlElement(t("Company"), "company", "company", "text", t("(max {n} characters)").replace("{n}", "64"), 64, true);
    const country = getCountries();
    const city = getHtmlElement(t("City"), "city", "city", "text", t("(max {n} characters)").replace("{n}", "128"), 128, true);
    const postcode = getHtmlElement(t("Postal code"), "postcode", "postcode", "text", t("(max {n} characters)").replace("{n}", "10"), 10, true);
    const address1 = getHtmlElement(t("Address"), "address1", "address1", "text", t("(max {n} characters)").replace("{n}", "128"), 128, true);
    const address2 = getHtmlElement(t("Address 2"), "address2", "address2", "text", t("(max {n} characters)").replace("{n}", "128"), 128, false);
    const phone = getHtmlElement(t("Phone"), "phone", "phone", "text", t("(max {n} characters)").replace("{n}", "24"), 24, false, t("At Least one number"));
    const phoneMobile = getHtmlElement(t("Mobile phone"), "phone_mobile", "phone_mobile", "text", t("(max {n} characters)").replace("{n}", "24"), 24, false, t("At Least one number"));

    container.appendChild(setTitle(t("Billing address")));
    container.appendChild(company);
    container.appendChild(country);
    container.appendChild(city);
    container.appendChild(address1);
    container.appendChild(address2);
    container.appendChild(phone);
    container.appendChild(phoneMobile);
    container.appendChild(getSeparator());

    return container;
}

function getSeparator() {
    const el = `<div class="form-group row"><hr /></div>`;
    return ElementFromHtml(el);
}

async function showFieldsType(type, container) {
    const nodes = container.querySelectorAll('[data-group="customerInvoice"]');

    nodes.forEach((node) => {
        const row = node.closest(".form-group.row");
        if (row) row.remove();
    });

    const hr = container.querySelectorAll("hr");
    hr.forEach((node) => {
        const row = node.closest(".form-group.row");
        if (row) row.remove();
    });

    switch (type) {
        case "ENTE":
            container.append(getSeparator());
            container.append(getDni());
            container.append(getCIG());
            container.append(getCUP());
            container.append(getSeparator());
            break;
        case "PARTITA_IVA":
            container.append(getSeparator());
            container.append(getVatNumber());
            container.append(getSDI());
            container.append(getPEC());
            container.append(getSeparator());
            break;
        case "PRIVATO":
            container.append(getSeparator());
            container.append(getDni());
            container.append(getSeparator());
            break;
    }

    container.append(getAddress());
    console.log("New Fieldset: tipo " + type);
}

async function bindOnChangeWantInvoice(e) {
    let containerEl = document.getElementById("customerInvoiceContainer");

    if (!containerEl) {
        containerEl = getFormContainer();
        wantInvoice = document.getElementById("field-want_invoice");
        if (wantInvoice) {
            wantInvoice.closest(".form-group.row").insertAdjacentElement("afterend", containerEl);
        }
    }

    const value = e.target.closest("select").value;

    if (value == 1) {
        containerEl.innerHTML = "";
        const fieldTypeEl = getFieldType();
        containerEl.append(fieldTypeEl);

        const fieldTypeSelect = fieldTypeEl.querySelector("select");
        if (fieldTypeSelect) {
            fieldTypeSelect.removeEventListener("change", bindOnChangeFieldType);
            fieldTypeSelect.addEventListener("change", bindOnChangeFieldType);
        }
    } else {
        containerEl.innerHTML = "";
    }
}

async function bindOnChangeFieldType(e) {
    const value = e.target.value;
    showFieldsType(value, e.target.closest("#customerInvoiceContainer"));
}

function initFormControls() {
    const dni = document.getElementsByName("dni");
    console.log("DNI", dni);

    if (dni && dni.length) {
        Array.from(dni).forEach((node) => {
            if (node) {
                console.log("nodo DNI eliminato");

                node.remove();
            }
        });
    }

    const vat = document.getElementsByName("vat_number");
    console.log("VAT", vat);

    if (vat && vat.length) {
        Array.from(vat).forEach((node) => {
            if (node) {
                console.log("Nodo VAT eliminato");

                node.remove();
            }
        });
    }

    container = getFormContainer();

    wantInvoice = document.getElementById("field-want_invoice");
    if (wantInvoice) {
        const parent = wantInvoice.closest(".form-group.row");
        wantInvoice.removeEventListener("change", bindOnChangeWantInvoice);
        wantInvoice.addEventListener("change", bindOnChangeWantInvoice);
    }

    bindPasswordStrengthChecker();
}

function initJobFields() {
    const jobArea = document.getElementById("field-id_customer_invoice_job_area");
    const jobPosition = document.getElementById("field-id_customer_invoice_job_position");

    if (!jobArea || !jobPosition) {
        return;
    }

    const resetPositions = () => {
        jobPosition.replaceChildren(new Option(t("Choose please"), ""));
    };

    jobArea.addEventListener("change", async () => {
        resetPositions();
        if (!jobArea.value) {
            return;
        }

        try {
            const body = new URLSearchParams({
                ajax: "1",
                action: "getJobPositions",
                idJobArea: jobArea.value,
            });
            const response = await fetch(MPCUSTOMERINVOICE_CONTROLLER, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body,
            });
            if (!response.ok) {
                throw new Error("Unable to load job positions");
            }

            const data = await response.json();
            (data.jobPositions || []).forEach((jobPositionData) => {
                jobPosition.add(new Option(jobPositionData.name, jobPositionData.id));
            });
        } catch (_) {
            resetPositions();
        }
    });

    resetPositions();
}

function getFormState(options) {
    const html = `
        <div class="form-state-select form-group row">
            <label for="state" class="col-md-3 form-control-label required">${t("Province")}</label>
            <div class="col-md-6 js-input-column" data-group="customerInvoice">
                <select data-group="customerInvoice" id="state" name="state" class="form-control chosen">
                    <option value="">${t("Choose...")}</option>
                </select>
            </div>
            <div class="col-md-3 form-control-comment">
                <span class="text-danger">${t("Required")}</span>
            </div>
        </div>
    `;

    const formGroup = ElementFromHtml(html);
    const select = formGroup.querySelector("select");
    select.innerHTML = `<option value="">${t("Choose...")}</option>`;

    Object.entries(options).forEach(([key, state]) => {
        const option = document.createElement("option");
        option.value = state.id_state;
        option.textContent = state.name;
        select.appendChild(option);
    });

    return formGroup;
}

async function onChangeCountry(event) {
    const select = event.target;

    if (select.tagName === "SELECT") {
        const container = select.closest("div.form-group.row");

        const stateEl = document.querySelector("#state");
        if (stateEl) {
            const stateContainer = stateEl.closest("div.form-group.row");
            if (stateContainer) {
                stateContainer.remove();
            }
        }

        const zipEl = document.querySelector("#postcode");
        if (zipEl) {
            const zipContainer = zipEl.closest("div.form-group.row");
            if (zipContainer) {
                zipContainer.remove();
            }
        }

        const selectedValue = select.value;
        const selectedText = select.options[select.selectedIndex]?.text || "";

        const data = await fetchAdmin("hasStates", {
            countryId: selectedValue,
            countryName: selectedText,
        });

        if (data.need_zip_code) {
            const zipComment = t("Enter the postal code in this format: {fmt}").replace("{fmt}", data.zip_code_format);
            const formCap = getHtmlElement(t("Postal code"), "postcode", "postcode", "text", zipComment, data.zip_code_format.length, true);
            const capEl = formCap.querySelector("#postcode");
            if (capEl) {
                const zipFormat = data.zip_code_format;
                const regexPattern = convertiPatternInRegex(zipFormat);
                const formControlComment = capEl.closest("div").querySelector("span.form-control-comment");
                const validaZipCode = function (e) {
                    validaInput(e.target, regexPattern, zipFormat, formControlComment);
                };
                capEl.classList += " pattern-input";
                capEl.removeEventListener("input", validaZipCode);
                capEl.addEventListener("input", validaZipCode);

                capEl.removeEventListener("blur", validaZipCode);
                capEl.addEventListener("blur", validaZipCode);
            }

            container.after(formCap);
        }

        if (data && data.hasStates == true) {
            const formState = getFormState(data.options);
            container.after(formState);
        }
    }
}

async function fetchAdmin(action, params = {}) {
    // Validazione input
    if (!action) {
        console.error("fetchAdmin: action è richiesta");
        return false;
    }

    const body = new FormData();
    body.append("ajax", "1"); // Aggiunto come stringa per sicurezza
    body.append("action", action);

    // Aggiungi tutti i parametri
    if (params && typeof params === "object") {
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                body.append(key, value);
            }
        });
    }

    try {
        const response = await fetch(endpoint, {
            method: "POST",
            body: body,
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const json = await response.json();

        // Opzionale: verifica che la risposta sia valida
        if (json && json.error) {
            showErrorMessage(json.error);
            return false;
        }

        return json;
    } catch (error) {
        console.error("fetchAdmin error:", error);
        showErrorMessage(t("Error while calling API {action}: {msg}").replace("{action}", String(action)).replace("{msg}", String(error.message)));
        return false;
    }
}

function showErrorMessage(message) {
    $.growl.error({
        title: t("Error"),
        message: message,
    });
}

function showNoticeMessage(message) {
    $.growl.notice({
        title: t("Info"),
        message: message,
    });
}

function showSuccessMessage(message) {
    $.growl.success({
        title: t("Success"),
        message: message,
    });
}

function showWarningMessage(message) {
    $.growl.warning({
        title: t("Warning"),
        message: message,
    });
}

function validaPatternCombinazioni(input) {
    if (typeof input !== "string") return false;

    // La regex controlla che ogni carattere sia:
    // - Numero (0-9)
    // - Lettera (A-Z a-z)
    // - Spazio
    // - Trattino
    const regex = /^[0-9A-Za-z\s-]+$/;

    // Controlla che non sia vuoto e che rispetti il pattern
    return input.trim().length > 0 && regex.test(input);
}

// Versione più flessibile che converte i tuoi placeholder N/L in regex
function validaPatternConPlaceholder(input, pattern) {
    // Se non viene fornito un pattern, usa una regex predefinita
    if (!pattern) {
        return validaPatternCombinazioni(input);
    }

    // Converti il pattern con placeholder in una regex
    // N → [0-9] (qualsiasi numero)
    // L → [A-Za-z] (qualsiasi lettera)
    // Gli altri caratteri (spazi, trattini) rimangono uguali
    let regexString = pattern.replace(/N/g, "[0-9]").replace(/L/g, "[A-Za-z]");

    // Aggiungi ^ all'inizio e $ alla fine per match esatto
    const regex = new RegExp(`^${regexString}$`);

    return regex.test(input);
}

// Funzione per convertire il pattern con placeholder in regex
function convertiPatternInRegex(pattern) {
    // Escape dei caratteri speciali regex tranne N e L
    let regexString = pattern
        .replace(/[.*+?^${}()|[\]\\]/g, "\\$&") // Escape caratteri speciali
        .replace(/N/g, "[0-9]") // N diventa [0-9]
        .replace(/L/g, "[A-Za-z]"); // L diventa [A-Za-z]

    return new RegExp(`^${regexString}$`);
}

// Funzione principale che crea l'input con validazione
async function creaInputConPattern() {
    try {
        // Simulazione fetch - sostituisci con la tua chiamata reale
        const response = await fetch("tua-api-endpoint/pattern");
        const data = await response.json();

        // Supponiamo che la risposta contenga il pattern
        // Esempio: { pattern: "NNN-NL-NNL", descrizione: "Formato codice" }
        const patternRicevuto = data.pattern || "NNNNN"; // Default se non presente

        // Converti il pattern in regex
        const regexPattern = convertiPatternInRegex(patternRicevuto);

        // Crea l'input element
        const input = document.createElement("input");
        input.type = "text";
        input.placeholder = t("Insert (format: {pattern})").replace("{pattern}", String(patternRicevuto));
        input.className = "pattern-input";

        // Crea container per il feedback
        const container = document.createElement("div");
        container.className = "input-container";

        // Crea label
        const label = document.createElement("label");
        label.textContent = t("Code ({pattern}):").replace("{pattern}", String(patternRicevuto));
        label.htmlFor = `input-${Date.now()}`;
        input.id = label.htmlFor;

        // Crea div per il messaggio di validazione
        const messageDiv = document.createElement("div");
        messageDiv.className = "validation-message";

        // Crea div per le istruzioni
        const instructions = document.createElement("div");
        instructions.className = "instructions";
        instructions.textContent = t("Required format: {pattern} (N=number, L=letter)").replace("{pattern}", String(patternRicevuto));

        // Aggiungi event listener per la validazione
        input.addEventListener("input", function (e) {
            validaInput(this, regexPattern, patternRicevuto, messageDiv);
        });

        input.addEventListener("blur", function (e) {
            validaInput(this, regexPattern, patternRicevuto, messageDiv);
        });

        // Assembla gli elementi
        container.appendChild(label);
        container.appendChild(input);
        container.appendChild(instructions);
        container.appendChild(messageDiv);

        // Aggiungi al DOM (esempio: nel body o in un contenitore specifico)
        document.body.appendChild(container);

        // Ritorna l'input creato per eventuale uso successivo
        return {
            input: input,
            pattern: patternRicevuto,
            regex: regexPattern,
            container: container,
        };
    } catch (error) {
        console.error("Errore nel fetch del pattern:", error);

        // Fallback: crea input con pattern di default
        return creaInputFallback();
    }
}

// Funzione di validazione
function validaInput(inputElement, regexPattern, patternOriginale, messageDiv) {
    const valore = inputElement.value;

    // Rimuovi classi precedenti
    inputElement.classList.remove("valid", "invalid");
    messageDiv.classList.remove("valid", "invalid");

    if (valore === "") {
        messageDiv.textContent = "";
        return;
    }

    if (regexPattern.test(valore)) {
        inputElement.classList.add("valid");
        messageDiv.textContent = t("✓ Valid pattern");
        messageDiv.classList.add("valid");

        // Opzionale: mostra la conversione N/L
        const analisi = analizzaPattern(valore, patternOriginale);
        if (analisi) {
            messageDiv.textContent += ` (${analisi})`;
        }
    } else {
        inputElement.classList.add("invalid");

        // Messaggio di errore dettagliato
        if (valore.length !== patternOriginale.length) {
            messageDiv.textContent = t("✗ Wrong length: required {n} characters").replace("{n}", String(patternOriginale.length));
        } else {
            messageDiv.textContent = t("✗ Invalid format. Use: {pattern} (N=number, L=letter)").replace("{pattern}", String(patternOriginale));
        }

        messageDiv.classList.add("invalid");
    }
}

// Funzione per analizzare il pattern (opzionale)
function analizzaPattern(valore, pattern) {
    if (valore.length !== pattern.length) return null;

    let risultato = "";
    for (let i = 0; i < pattern.length; i++) {
        const char = valore[i];
        const tipoRichiesto = pattern[i];

        if (tipoRichiesto === "N" && /[0-9]/.test(char)) {
            risultato += "N";
        } else if (tipoRichiesto === "L" && /[A-Za-z]/.test(char)) {
            risultato += "L";
        } else if (tipoRichiesto !== "N" && tipoRichiesto !== "L") {
            // Caratteri fissi (es. trattini)
            risultato += tipoRichiesto;
        } else {
            return null;
        }
    }

    return risultato;
}

// Funzione di fallback
function creaInputFallback() {
    const patternDefault = "NNNNN";
    const regexDefault = /^[0-9]{5}$/;

    const input = document.createElement("input");
    input.type = "text";
    input.placeholder = t("Insert (format: {pattern})").replace("{pattern}", String(patternDefault));

    const messageDiv = document.createElement("div");
    messageDiv.className = "validation-message";

    input.addEventListener("input", function (e) {
        if (regexDefault.test(this.value)) {
            this.classList.add("valid");
            messageDiv.textContent = t("✓ Valid");
        } else {
            this.classList.add("invalid");
            messageDiv.textContent = t("✗ Invalid");
        }
    });

    document.body.appendChild(input);
    document.body.appendChild(messageDiv);

    return { input, pattern: patternDefault, regex: regexDefault };
}

// Versione avanzata con supporto per pattern multipli
async function creaInputConPatternMultipli() {
    try {
        // Fetch che potrebbe restituire più pattern
        const response = await fetch("tua-api-endpoint/patterns");
        const data = await response.json();

        // Esempio risposta: { patterns: ["NNN-NN", "LLL-LL", "NL-NL"], default: 0 }
        const patterns = data.patterns || ["NNNNN"];
        const defaultIndex = data.default || 0;

        // Container principale
        const mainContainer = document.createElement("div");
        mainContainer.className = "pattern-selector-container";

        // Crea select per scegliere il pattern
        const select = document.createElement("select");
        select.className = "pattern-select";

        patterns.forEach((pattern, index) => {
            const option = document.createElement("option");
            option.value = pattern;
            option.textContent = `${t("Pattern")} ${index + 1}: ${pattern}`;
            if (index === defaultIndex) option.selected = true;
            select.appendChild(option);
        });

        // Container per l'input dinamico
        const inputContainer = document.createElement("div");
        inputContainer.className = "dynamic-input-container";

        // Funzione per aggiornare l'input in base al pattern selezionato
        function aggiornaInputPerPattern(pattern) {
            inputContainer.innerHTML = ""; // Pulisci

            const regex = convertiPatternInRegex(pattern);

            const label = document.createElement("label");
            label.textContent = t("Insert ({pattern}):").replace("{pattern}", String(pattern));

            const input = document.createElement("input");
            input.type = "text";
            input.placeholder = t("Format: {pattern}").replace("{pattern}", String(pattern));
            input.className = "pattern-input";

            const message = document.createElement("div");
            message.className = "validation-message";

            const esempio = document.createElement("small");
            esempio.textContent = generaEsempio(pattern);
            esempio.style.display = "block";
            esempio.style.color = "#666";
            esempio.style.marginTop = "5px";

            input.addEventListener("input", function () {
                validaInput(this, regex, pattern, message);
            });

            inputContainer.appendChild(label);
            inputContainer.appendChild(input);
            inputContainer.appendChild(esempio);
            inputContainer.appendChild(message);
        }

        // Event listener per il cambio pattern
        select.addEventListener("change", function () {
            aggiornaInputPerPattern(this.value);
        });

        // Inizializza con il pattern di default
        mainContainer.appendChild(select);
        mainContainer.appendChild(inputContainer);
        document.body.appendChild(mainContainer);

        aggiornaInputPerPattern(patterns[defaultIndex]);
    } catch (error) {
        console.error("Errore:", error);
        creaInputFallback();
    }
}

function onBeforeSubmitButton(e) {
    const form = document.querySelector("#customer-form");
    const formData = new FormData(form);
    const jsonData = {};

    for (const [key, value] of formData.entries()) {
        jsonData[key] = value;
    }

    const pwd = document.getElementById("field-password");
    if (pwd) {
        const minLength = pwd.dataset.minlength;
        const maxLength = pwd.dataset.maxlength;
        const minScore = pwd.dataset.minscore;
        const pattern = pwd.pattern;

        const res = evaluatePasswordStrength(pwd.value, {
            minLength,
            maxLength,
            minScore,
            pattern,
        });

        if (!res.ok) {
            pwd.closest("div.form-group").classList.add("has-error");
            tinyAlert(t("Please insert a strength password"), "warning");
            pwd.focus();
            e.preventDefault();
            return false;
        }
    }

    const birthday = document.getElementById("field-birthday");
    if (birthday && birthday.value.length > 0) {
        console.log("Check birthday", birthday.value);

        let validate = false;
        const placeholder = birthday.placeholder;
        switch (placeholder) {
            case "GG/MM/AAAA":
            case "DD/MM/YYYY":
                validate = /^\d{2}\/\d{2}\/\d{4}$/.test(birthday.value);
                break;
            case "AAAA-MM-GG":
            case "YYYY-MM-DD":
                validate = /^\d{4}-\d{2}-\d{2}$/.test(birthday.value);
                break;
            case "MM-GG-AAAA":
            case "MM-DD-YYYY":
                validate = /^\d{2}-\d{2}-\d{4}$/.test(birthday.value);
                break;
            default:
                validate = true;
                break;
        }
        if (!validate) {
            birthday.closest("div.form-group").classList.add("has-error");
            tinyAlert(t("Please insert your birthday date"), "warning");
            birthday.focus();
            e.preventDefault();
            return false;
        }
    }

    const phone = document.getElementById("phone");
    const mobile = document.getElementById("phone_mobile");

    if (phone && mobile) {
        if (!phone.value.length && !mobile.value.length) {
            phone.closest("div.form-group").classList.add("has-error");
            mobile.closest("div.form-group").classList.add("has-error");
            phone.closest("div.form-group").querySelector(".form-control-comment").innerText = t("Please insert a telephone number");
            mobile.closest("div.form-group").querySelector(".form-control-comment").innerText = t("Please insert a mobile number");
            tinyAlert(t("Please insert at least one telephone number"), "warning");
            mobile.focus();
            e.preventDefault();
            return false;
        }
    }
}
