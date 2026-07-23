function initFormControls() {
    const dni = document.querySelector("#field-dni");
    const vat = document.querySelector("#field-vat_number");
    const alias = document.querySelector("#field-alias");

    if (dni) {
        dni.closest(".form-group.row").remove();
    }

    if (vat) {
        vat.closest(".form-group.row").remove();
    }

    if (alias) {
        alias.value = "SPEDIZIONE";
        alias.closest(".form-group.row").style.display = "none";
    }
}
