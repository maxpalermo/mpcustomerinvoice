function initFormControls() {
    const dni = document.querySelector("#field-dni");
    const vat = document.querySelector("#field-vat_number");
    const alias = document.querySelector("#field-alias");
    const addAddress = document.querySelector("p.add-address");
    const deliveryAddresses = document.querySelector("#delivery-addresses");
    const invoiceAddresses = document.querySelector("#invoice-addresses");

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

    if (addAddress) {
        addAddress.style.display = "none";
    }

    if (useSameAddress) {
        const invoiceAddress = document.querySelector("#invoice-address");
        let title = invoiceAddress?.previousElementSibling;

        while (title && title.tagName !== "H2") {
            title = title.previousElementSibling;
        }

        if (title) {
            title.textContent = "Indirizzo di spedizione";
        }
    }

    if (deliveryAddresses) {
        const footerDelivery = deliveryAddresses.querySelectorAll("footer.address-footer");
        if (footerDelivery) {
            footerDelivery.forEach((el) => {
                el.style.display = "none";
            });
        }
    }

    if (invoiceAddresses) {
        const footerInvoice = invoiceAddresses.querySelectorAll("footer.address-footer");
        if (footerInvoice) {
            footerInvoice.forEach((el) => {
                el.style.display = "none";
            });
        }
    }
}
