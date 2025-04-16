document.addEventListener("DOMContentLoaded", (e) => {
    const fieldCheckInvoice = document.getElementsByName("want_invoice")[0];
    const fieldType = document.getElementById("field-type");
    const fieldVatNumber = document.getElementById("field-vat_number");
    const fieldFiscalCode = document.getElementById("field-fiscal_code");
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
});
