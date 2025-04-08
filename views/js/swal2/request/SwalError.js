async function swalError(message) {
    return new Promise((resolve, reject) => {
        Swal.fire({
            title: "Errore",
            text: message,
            icon: "error",
            confirmButtonText: "OK",
            confirmButtonColor: "#25b9d7"
        }).then((result) => {
            if (result.isConfirmed) {
                resolve(true);
            } else {
                resolve(false);
            }
        });
    });
}
