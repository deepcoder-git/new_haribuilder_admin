let mix = require("laravel-mix");
const path = require("path");

mix.disableNotifications();
mix.version();

mix
    .js("resources/panel/assets/js/livewire.js", "build/panel/js")
    .js("resources/panel/assets/js/theme-mode.js", "build/panel/js")
    .js("resources/panel/assets/js/module-theme.js", "build/panel/js")
    .js("resources/panel/assets/js/fullcalendar-setup.js", "build/panel/js")
    .js("resources/panel/assets/js/select2-init.js", "build/panel/js")
    // .js("resources/panel/assets/js/alpine-ui.js", "build/panel/js") 
    // .js("resources/panel/assets/js/tailwind.js", "build/panel/js") 
    .copy("resources/panel/assets/css/common.css", "public/build/panel/css/common.css")
    .copyDirectory("resources/panel/assets/images", "public/build/panel/images");
mix.copyDirectory("resources/panel/assets/mv", "public/build/panel/vendors");

mix.copy("node_modules/@fancyapps/ui/dist/fancybox/fancybox.css", "public/build/vendor/fancybox");
mix.copy("node_modules/@fancyapps/ui/dist/fancybox/fancybox.umd.js", "public/build/vendor/fancybox");


mix.copy("node_modules/@fullcalendar/core/index.global.min.js", "public/build/panel/js/fullcalendar.js"); // Copy FullCalendar JS

mix.copy("node_modules/@fullcalendar/daygrid/index.global.min.js", "public/build/panel/js/fullcalendar-daygrid.js"); // Copy FullCalendar DayGrid JS

mix.copy("node_modules/@fullcalendar/timegrid/index.global.min.js", "public/build/panel/js/fullcalendar-timegrid.js"); // Copy FullCalendar TimeGrid JS
mix.copy("node_modules/@fullcalendar/interaction/index.global.min.js", "public/build/panel/js/fullcalendar-interaction.js"); // Copy FullCalendar Interaction JS
