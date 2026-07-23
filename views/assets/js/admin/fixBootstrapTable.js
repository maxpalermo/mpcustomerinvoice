function fixBootstrapTable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) {
        return;
    }

    const $wrapper = $(table).closest(".bootstrap-table");
    const $toolbar = $wrapper.find(".fixed-table-toolbar");
    const $pagination = $wrapper.find(".fixed-table-pagination");

    $pagination.find(".dropdown-toggle").attr("data-toggle", "dropdown").removeAttr("data-bs-toggle");
    $toolbar.find("button[name=refresh] i").attr("class", "material-icons").text("refresh");
    $toolbar.find("button[name=filterControlSwitch] i").attr("class", "material-icons").text("filter_list");
    $toolbar.find(".search input").addClass("form-control");
    $toolbar.find(".columns label").removeClass("checkbox-inline").addClass("dropdown-item");
    $toolbar.find(".columns label input").removeClass("checkbox-inline").addClass("mr-2");
}
