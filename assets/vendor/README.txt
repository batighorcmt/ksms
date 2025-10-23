Local vendor assets (optional, recommended for offline deployments)

Place the following files under this folder to avoid CDN dependencies:

- assets/vendor/fontawesome/css/all.min.css
  and fonts under assets/vendor/fontawesome/webfonts/*
  (From https://cdnjs.com/libraries/font-awesome or https://fontawesome.com/download)

- assets/vendor/bootstrap/css/bootstrap.min.css
  assets/vendor/bootstrap/js/bootstrap.bundle.min.js
  (Bootstrap 4.6.x)

- assets/vendor/adminlte/css/adminlte.min.css
  assets/vendor/adminlte/js/adminlte.min.js
  (AdminLTE 3.2.x)

- assets/vendor/jquery/jquery.min.js
  (jQuery 3.6.x)

- Optional Bangla font:
  assets/vendor/solaiman-lipi/solaiman-lipi.css
  assets/vendor/solaiman-lipi/SolaimanLipi.woff2 (and related)

After placing files, pages will automatically use local assets via admin/inc/head_assets.php and scripts_assets.php. If a file is missing locally, the app will try CDN as a fallback.
