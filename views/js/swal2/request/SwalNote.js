async function swalNote(html, cancelBtnId, afterLoading) {
    console.log("swalNote cancelBtnId", cancelBtnId);
    return new Promise((resolve, reject) => {
        Swal.fire({
            html: html,
            width: "60%",
            showConfirmButton: false,
            showCloseButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: {
                container: "swal-note-container",
                popup: "swal-note-popup",
                content: "swal-note-content",
                popup: "swal-note-html-container-transparent"
            },
            didOpen: async () => {
                console.log("swalNote didOpen");
                const cancelBtn = document.getElementById(cancelBtnId);
                cancelBtn.addEventListener("click", () => {
                    console.log("swalNote cancelBtn click");
                    Swal.close();
                });

                console.log("swalNote afterLoading", typeof afterLoading);
                if (typeof afterLoading === "function") {
                    await afterLoading();
                }
            },
            willClose: async () => {
                console.log("swalNote willClose");
            }
        }).then((result) => {
            if (result.isConfirmed) {
                resolve(result.value);
            } else {
                resolve(null);
            }
        });
    });
}
