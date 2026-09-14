(function () {
    'use strict';

    document.documentElement.classList.remove('no-js');

    document.querySelectorAll('[data-javaroot-theme-link]').forEach(function (link) {
        link.addEventListener('click', function () {
            document.documentElement.dataset.javarootTheme = link.dataset.javarootThemeLink;
        });
    });
}());
