(() => {
  const apiUrl = new URL("api/counter.php", document.currentScript.src);
  const pageName = decodeURIComponent(window.location.pathname.split("/").pop() || "index")
    .replace(/\.html?$/i, "") || "index";

  const reportError = (error) => {
    document.querySelectorAll("[data-site-visits], [data-page-visits], [data-request-count], [data-most-requested]")
      .forEach((element) => { element.textContent = "No disponible"; });
    console.error("No se pudo actualizar las estadísticas de visitas.", error);
  };

  const sendEvent = (event, values = {}) => fetch(apiUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ event, ...values }),
    cache: "no-store",
    keepalive: true
  }).then((response) => {
    if (!response.ok) throw new Error(`El servidor respondió ${response.status}.`);
    return response.json();
  }).then(displayCounts).catch(reportError);

  const refreshCounts = () => fetch(apiUrl, { cache: "no-store" })
    .then((response) => {
      if (!response.ok) throw new Error(`El servidor respondió ${response.status}.`);
      return response.json();
    })
    .then(displayCounts)
    .catch(reportError);

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
    document.querySelectorAll("[data-most-visited]").forEach((element) => {
      const top = data.most_visited_development;
      const names = {
        solha: "Solhá", nexum82: "Nexum82", lakuun: "Lakuun", martell: "Martell",
        "marela-life": "Marela Life", "hacienda-ticopo-gutierrez": "Hacienda Ticopó Gutiérrez",
        suno: "SUNO", monarca: "Monarca", anthia: "Anthia",
        "marela-celestun": "Marela Celestún", velamar: "Velamar",
        "lote-220-ciudad-central-progreso": "Lote 220 · Ciudad Central Progreso"
      };
      element.textContent = top ? `${names[top.development] || top.development} (${Number(top.count).toLocaleString("es-MX")} visitas)` : "Sin visitas registradas";
    });
  }

  // Cada carga de página suma una visita total y otra para la página correspondiente.
  sendEvent("visit", { page: pageName });

  // Actualizar la cifra mientras la página permanece abierta sin contar otra visita.
  window.setInterval(() => {
    if (!document.hidden) refreshCounts();
  }, 30000);

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
