function initFormControls() {
    const addressesBox = document.querySelectorAll("article[id^=address-]");
    if (addressesBox) {
        addressesBox.forEach((box) => {
            const h4 = box.querySelector(".address-body h4");
            const footer = box.querySelector(".address-footer");

            if (h4 && h4.innerText == "FATTURAZIONE") {
                if (footer) {
                    footer.remove();
                }
            }
        });
    }
}
