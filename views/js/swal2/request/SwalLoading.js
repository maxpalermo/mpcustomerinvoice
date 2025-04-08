function swalLoading(message = null) {
    const html = `
    <div class="swal2-loader">
            <i class="icon-spinner icon-spin icon-3x"></i>
            <p class="mt-2">Creazione file in corso...</p>
            <progress id="export-progress" value="0" max="100" class="w-100 mt-2"></progress>
        </div>
    `;
    return Swal.fire({
        title: "Caricamento in corso",
        html: message || html,
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
}
