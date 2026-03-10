document.addEventListener("DOMContentLoaded", async (e) => {
    const selectJobArea = document.querySelector("#customer_id_customer_invoice_job_area");
    if (!selectJobArea) return;

    const selectJobPosition = document.querySelector("#customer_id_customer_invoice_job_position");
    if (!selectJobPosition) return;

    selectJobArea.addEventListener("change", async () => {
        const idJobArea = selectJobArea.value;
        if (!idJobArea) return;

        selectJobPosition.innerHTML = "<option value=''>Attendere...</option>";

        const response = await fetch(MPCUSTOMERINVOICE_ADMIN_CONTROLLER, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: new URLSearchParams({
                action: "getJobPositions",
                ajax: true,
                idJobArea: idJobArea,
            }),
        });

        if (!response.ok) {
            throw new Error("Network response was not ok");
        }

        const data = await response.json();
        selectJobPosition.innerHTML = "<option value=''>Seleziona una professione</option>";
        data.jobPositions.forEach((item) => {
            const option = document.createElement("option");
            option.value = item.id;
            option.textContent = item.name;
            selectJobPosition.appendChild(option);
        });
    });
});
