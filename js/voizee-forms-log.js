function install_logs(log_btn, log_list) {
    var btn = document.getElementById(log_btn);
    var list = document.getElementById(log_list);
    var list_elem = list.getElementsByClassName("voizee_list")[0];

    var logs = JSON.parse(list_elem.getAttribute("data-logs") || "[]");
    for (var i = logs.length - 1; i >= 0; i--) {
        var row = document.createElement("div");
        row.className = "voizee_row";
        row.textContent = logs[i].message;
        row.innerHTML = row.innerHTML +
            "<span class='voizee_date'>" +
            logs[i].date +
            "</span>";
        list_elem.appendChild(row);
    }

    btn.addEventListener("click", function (e) {
        e.preventDefault();

        if (list.style.display === "none") {
            list.style.display = "block";
            btn.getElementsByTagName("span")[0].innerHTML = "&#x00D7;";
        } else {
            list.style.display = "none";
            btn.getElementsByTagName("span")[0].innerHTML = "&#9662;";
        }
    });
}

// Инициализируем функции с соответствующими ID элементов
document.addEventListener("DOMContentLoaded", function () {
    if (document.getElementById("voizee_cf7-logs-btn")) {
        install_logs("voizee_cf7-logs-btn", "voizee_cf7-logs-list");
    }
    if (document.getElementById("voizee_gf-logs-btn")) {
        install_logs("voizee_gf-logs-btn", "voizee_gf-logs-list");
    }
});
