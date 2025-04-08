async function swalInput(message) {
    return new Promise((resolve, reject) => {
        Swal.fire({
            title: "Input",
            text: message,
            icon: "question",
            input: "text",
            showCancelButton: true,
            confirmButtonText: "Si",
            cancelButtonText: "Annulla",
            confirmButtonColor: "#25b9d7"
        }).then((result) => {
            if (result.isConfirmed) {
                resolve(result.value);
            } else {
                resolve(null);
            }
        });
    });
}
