function fillJobArea() {
    const jobArea = document.getElementById("field-id_customer_invoice_job_area");
    const jobAreaPos = document.getElementById("field-id_customer_invoice_job_position");
    if (jobArea && jobAreaPos) {
        jobAreaPos.innerHTML = `<option value="">${t("Choose please")}</option>`;
        jobArea.innerHTML = `<option value="">${t("Choose please")}</option>`;

        const jobAreas = Array.isArray(jobs) ? jobs : Object.values(jobs);
        jobAreas.forEach((job) => {
            const jobEl = document.createElement("option");
            jobEl.value = job.id;
            jobEl.textContent = job.name;
            jobArea.append(jobEl);
        });

        jobArea.addEventListener("change", (e) => {
            fillJobPositions(e.target.value);
        });
    }
}

function fillJobPositions(idJobArea) {
    const jobsEl = document.getElementById("field-id_customer_invoice_job_position");
    if (jobsEl) {
        jobsEl.innerHTML = `<option value="">${t("Choose please")}</option>`;

        const source = Array.isArray(jobs) ? jobs.find((j) => String(j.id) === String(idJobArea)) : jobs?.[idJobArea];
        const jobsChildren = source?.jobs || [];
        jobsChildren.forEach((job) => {
            const el = document.createElement("option");
            el.value = job.id;
            el.textContent = job.name;

            jobsEl.append(el);
        });
    }
}

function base64Decode(str) {
    // Decodifica Base64 → stringa binaria
    const binaryString = atob(str);
    // Converte la stringa binaria in un array di byte
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    // Decodifica UTF-8
    return new TextDecoder().decode(bytes);
}
