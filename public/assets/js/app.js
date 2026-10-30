"use strict";

document.querySelectorAll("[data-dismiss]").forEach(function (button) {
    button.addEventListener("click", function () {
        var target = button.closest(".alert");
        if (target) {
            target.remove();
        }
    });
});

var navToggle = document.querySelector("[data-nav-toggle]");
var nav = document.querySelector("[data-nav]");
if (navToggle && nav) {
    navToggle.addEventListener("click", function () {
        var open = nav.classList.toggle("is-open");
        navToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
}

document.querySelectorAll("[data-countdown]").forEach(function (node) {
    var deadline = new Date(node.getAttribute("data-countdown"));
    if (Number.isNaN(deadline.getTime())) {
        return;
    }

    var render = function () {
        var seconds = Math.max(0, Math.floor((deadline.getTime() - Date.now()) / 1000));
        if (seconds === 0) {
            node.textContent = "Pendaftaran ditutup";
            return;
        }
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        node.textContent = days + " hari " + hours + " jam " + minutes + " menit";
    };

    render();
    window.setInterval(render, 60000);
});
