document.addEventListener("MpCustomerInvoiceReady", (e) => {
    console.log("DOMContentLoaded MPCUSTOMERINVOICE");
    const customerCard = document.querySelector(".card.customer-personal-informations-card");
    const eurosolutionRow = document.getElementById("mpcustomerinvoice-personal-info");
    if (customerCard && eurosolutionRow) {
        const templateContent = eurosolutionRow.content;
        const cardBody = customerCard.querySelector(".card-body");
        const childNode = templateContent.cloneNode(true);

        cardBody.appendChild(childNode);
    }
});
