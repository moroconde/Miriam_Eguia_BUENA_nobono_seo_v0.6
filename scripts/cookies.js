(function () {
  var KEY = "me_consent";
  var GA_ID = "";
  var banner =
    '<div id="cookie-banner" class="cookie-me" role="dialog" aria-live="polite" aria-label="Cookies">' +
    "<p>Usamos cookies técnicas y, si lo aceptas, cookies analíticas de Google para medir visitas. Puedes aceptarlas o seguir solo con las necesarias.</p>" +
    '<div class="cookie-me-actions">' +
    '<button type="button" class="btn btn-verde" id="cookie-aceptar">Aceptar</button>' +
    '<button type="button" class="btn btn-linea" id="cookie-rechazar">Solo necesarias</button>' +
    '<a href="politica-cookies.html">Más información</a>' +
    "</div></div>";

  function getConsent() {
    try {
      var m = document.cookie.match(/(?:^|; )me_consent=([^;]*)/);
      if (m) return decodeURIComponent(m[1]);
      return localStorage.getItem(KEY) || "";
    } catch (e) {
      return "";
    }
  }

  function setConsent(value) {
    try {
      localStorage.setItem(KEY, value);
      document.cookie =
        "me_consent=" +
        encodeURIComponent(value) +
        ";path=/;max-age=" +
        60 * 60 * 24 * 395 +
        ";SameSite=Lax";
    } catch (e) {}
  }

  function loadAnalytics() {
    if (!GA_ID || window.gtag) return;
    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GA_ID);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () {
      window.dataLayer.push(arguments);
    };
    window.gtag("js", new Date());
    window.gtag("consent", "update", { analytics_storage: "granted" });
    window.gtag("config", GA_ID, { anonymize_ip: true });
  }

  function apply(value) {
    if (value === "all") loadAnalytics();
  }

  function showBanner() {
    if (document.getElementById("cookie-banner")) return;
    document.body.insertAdjacentHTML("beforeend", banner);
    document.getElementById("cookie-aceptar").addEventListener("click", function () {
      setConsent("all");
      apply("all");
      document.getElementById("cookie-banner").remove();
    });
    document.getElementById("cookie-rechazar").addEventListener("click", function () {
      setConsent("necessary");
      document.getElementById("cookie-banner").remove();
    });
  }

  window.MECookies = {
    reset: function () {
      try {
        localStorage.removeItem(KEY);
      } catch (e) {}
      document.cookie = "me_consent=;path=/;max-age=0";
      showBanner();
    },
  };

  var current = getConsent();
  if (current === "all" || current === "necessary") {
    apply(current);
    return;
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", showBanner);
  } else {
    showBanner();
  }
})();
