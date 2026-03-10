function fixOptionalComments() {
    const formGroups = document.querySelectorAll(".form-group.row");
    const fix = ["want_invoice", "type", "vat_number", "dni", "cuu", "sdi", "pec", "cig", "cup"];
    formGroups.forEach((formGroup) => {
        fix.filter((item) => {
            const nameEl = `[name="${item}"]`;
            const el = formGroup.querySelector(nameEl);

            if (el) {
                const comment = el.closest(".form-group").querySelector(".form-control-comment");
                console.log("comment", comment);
                comment.innerHTML = "";
                return false; // lo rimuove da fix
            }
            return true;
        });
    });
}

function removeUnusedElements() {
    const formGroups = document.querySelectorAll(".form-group.row");
    const remove = ["dni"];
    formGroups.forEach((formGroup) => {
        remove.filter((item) => {
            const nameEl = `[name="${item}"]`;
            const el = formGroup.querySelector(nameEl);

            if (el) {
                formGroup.remove();
                return false; // lo rimuove da fix
            }
            return true;
        });
    });
}

document.addEventListener("DOMContentLoaded", (e) => {
    const MPCUSTOMERINVOICE_CONTROLLER = document.querySelector("input[name='MPCUSTOMERINVOICE_CONTROLLER']").value;
    const fieldCheckInvoice = document.getElementsByName("want_invoice")[0];
    const fieldType = document.getElementById("field-type");
    const fieldVatNumber = document.getElementById("field-piva");
    const fieldFiscalCode = document.getElementById("field-cf");
    const fieldCuu = document.getElementById("field-cuu");
    const fieldSdi = document.getElementById("field-sdi");
    const fieldPec = document.getElementById("field-pec");
    const fieldCig = document.getElementById("field-cig");
    const fieldCup = document.getElementById("field-cup");

    if (fieldCheckInvoice && !fieldCheckInvoice.checked) {
        hideField(fieldType);
        hideField(fieldVatNumber);
        hideField(fieldFiscalCode);
        hideField(fieldCuu);
        hideField(fieldSdi);
        hideField(fieldPec);
        hideField(fieldCig);
        hideField(fieldCup);
    } else if (fieldCheckInvoice && fieldCheckInvoice.checked) {
        showField(fieldType);
        checkFieldType(fieldType);
    }

    fieldCheckInvoice.addEventListener("change", (e) => {
        if (e.target.checked) {
            showField(fieldType);
            fieldType.value = "";
        } else {
            hideField(fieldType);
            hideField(fieldVatNumber);
            hideField(fieldFiscalCode);
            hideField(fieldCuu);
            hideField(fieldSdi);
            hideField(fieldPec);
            hideField(fieldCig);
            hideField(fieldCup);
        }
    });

    fieldType.addEventListener("change", (e) => {
        checkFieldType(fieldType);
    });

    function hideField(element) {
        if (element == null) {
            return;
        }
        const form_group = element.closest(".form-group");
        if (form_group) {
            form_group.style.display = "none";
            element.removeAttribute("required");
            element.value = "";
        }
    }

    function showField(element) {
        if (element == null) {
            return;
        }
        const form_group = element.closest(".form-group");
        if (form_group) {
            form_group.style.display = "block";
            element.setAttribute("required", "required");
        }
    }

    function checkFieldType(fieldType) {
        console.log("check Field type" + fieldType.value);

        if (fieldType.value === "ENTE") {
            hideField(fieldVatNumber);
            showField(fieldFiscalCode);
            hideField(fieldCuu);
            hideField(fieldSdi);
            hideField(fieldPec);
            showField(fieldCig);
            showField(fieldCup);
        } else if (fieldType.value === "PARTITA_IVA") {
            showField(fieldVatNumber);
            hideField(fieldFiscalCode);
            hideField(fieldCuu);
            showField(fieldSdi);
            showField(fieldPec);
            hideField(fieldCig);
            hideField(fieldCup);
        } else if (fieldType.value === "PRIVATO") {
            hideField(fieldVatNumber);
            showField(fieldFiscalCode);
            hideField(fieldCuu);
            hideField(fieldSdi);
            hideField(fieldPec);
            hideField(fieldCig);
            hideField(fieldCup);
        } else {
            hideField(fieldVatNumber);
            hideField(fieldFiscalCode);
            hideField(fieldCuu);
            hideField(fieldSdi);
            hideField(fieldPec);
            hideField(fieldCig);
            hideField(fieldCup);
        }
    }

    const jobAreaSelect = document.querySelector("select[name='id_customer_invoice_job_area']");
    const jobPositionSelect = document.querySelector("select[name='id_customer_invoice_job_position']");
    if (jobAreaSelect && jobPositionSelect) {
        jobAreaSelect.addEventListener("change", async () => {
            const idJobArea = jobAreaSelect.value;
            // Remove all options except the first (empty)
            jobPositionSelect.innerHTML = '<option value=""></option>';

            console.log("idJobArea", idJobArea);

            if (!idJobArea) return;

            try {
                const response = await fetch(MPCUSTOMERINVOICE_CONTROLLER, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    body: new URLSearchParams({
                        action: "getJobPositions",
                        ajax: 1,
                        idJobArea: idJobArea,
                    }),
                });

                const data = await response.json();
                jobPositionSelect.innerHTML = '<option value="">Seleziona una professione</option>';
                data.jobPositions.forEach(function (item) {
                    const opt = document.createElement("option");
                    opt.value = item.id;
                    opt.textContent = item.name;
                    jobPositionSelect.appendChild(opt);
                });
            } catch (error) {
                console.error("Error fetching job positions:", error);
            }
        });
    }

    fixOptionalComments();
    removeUnusedElements();
});
