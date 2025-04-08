async function swalWarning(message) {
    return new Promise((resolve, reject) => {
        Swal.fire({
            title: "Warning",
            text: message,
            icon: "warning",
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
