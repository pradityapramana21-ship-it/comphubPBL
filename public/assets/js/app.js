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
