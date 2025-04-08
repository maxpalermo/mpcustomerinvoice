async function swalSuccess(message) {
    return new Promise((resolve, reject) => {
        Swal.fire({
            title: "Successo",
            text: message,
            icon: "success",
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
