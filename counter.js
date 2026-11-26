(() => {
  const allowedDevelopments = new Set([
    "acd-desarrollos",
    "anthia",
    "hacienda-ticopo-gutierrez",
    "lakuun",
    "lote-telchac",
    "marela-celestun",
    "marela-life",
    "martell",
    "monarca",
    "nexum82",
    "solha",
    "suno",
    "terreno-dzoyola",
    "valora",
    "velamar"
  ]);
  const apiUrl = new URL("api/counter.php", document.currentScript.src);
  const pageName = decodeURIComponent(window.location.pathname.split("/").pop() || "")
    .replace(/\.html?$/i, "");

  const API_URL = "api/guardar_prospecto.php";

  // 1. Registrar visita automáticamente al cargar cualquier página
  fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ event: "visit" })
  }).then(r => r.json()).then(data => displayCounts(data));

  // 2. Función para registrar clics de interés (solicitudes)
  const registrarInteres = (development) => {
    if (!development) return;
    fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ event: "request", development: development })
    }).then(r => r.json()).then(data => displayCounts(data));
  };

  function displayCounts(data) {
    if (!data) return;
    document.querySelectorAll("[data-site-visits]").forEach((element) => {
      element.textContent = data.visits ? data.visits.toLocaleString("es-MX") : "0";
    });

    document.querySelectorAll("[data-request-count]").forEach((element) => {
      const dev = element.dataset.requestCount;
      const count = data.requests && data.requests[dev] ? data.requests[dev] : 0;
      element.textContent = count.toLocaleString("es-MX");
    });
  }

  // 3. Detectar clics en botones de WhatsApp
  document.addEventListener("click", (e) => {
    const link = e.target.closest(".whatsapp-link");
    if (link && link.dataset.development) {
      registrarInteres(link.dataset.development);
    }
  });

  // 4. Detectar clics en el menú de desarrollos
  document.querySelectorAll(".dropdown a").forEach((link) => {
    link.addEventListener("click", (e) => {
      // Extrae el nombre del desarrollo del texto del enlace
      const devName = e.target.textContent.toLowerCase().trim().replace(/\s+/g, '-');
      registrarInteres(devName);
    });
  });
})();

