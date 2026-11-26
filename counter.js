(() => {
  const apiUrl = new URL("api/counter.php", document.currentScript.src);
  const pageName = decodeURIComponent(window.location.pathname.split("/").pop() || "index")
    .replace(/\.html?$/i, "") || "index";

  const sendEvent = (event, values = {}) => fetch(apiUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ event, ...values }),
    keepalive: true
  }).then((response) => response.ok ? response.json() : null).then(displayCounts).catch(() => {});

  function displayCounts(data) {
    if (!data) return;
    document.querySelectorAll("[data-site-visits]").forEach((element) => {
      element.textContent = Number(data.visits || 0).toLocaleString("es-MX");
    });
    document.querySelectorAll("[data-page-visits]").forEach((element) => {
      element.textContent = Number(data.page_visits?.[element.dataset.pageVisits || pageName] || 0).toLocaleString("es-MX");
    });
    document.querySelectorAll("[data-request-count]").forEach((element) => {
      element.textContent = Number(data.requests?.[element.dataset.requestCount] || 0).toLocaleString("es-MX");
    });
    document.querySelectorAll("[data-most-requested]").forEach((element) => {
      const top = data.most_requested_development;
      element.textContent = top ? `${top.development} (${Number(top.count).toLocaleString("es-MX")} solicitudes)` : "Sin solicitudes registradas";
    });
  }

  // Cada carga de página suma una visita total y otra para la página correspondiente.
  sendEvent("visit", { page: pageName });

  document.addEventListener("click", (event) => {
    const link = event.target.closest(".whatsapp-link");
    if (link?.dataset.development) sendEvent("request", { development: link.dataset.development });
  });

  document.querySelectorAll(".dropdown a").forEach((link) => {
    link.addEventListener("click", () => {
      const development = link.textContent.toLowerCase().trim().normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
      if (development) sendEvent("request", { development });
    });
  });
})();
