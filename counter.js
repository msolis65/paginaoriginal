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

  function displayCounts(data) {
    if (!Number.isSafeInteger(data.visits) || !data.requests || typeof data.requests !== "object") {
      throw new Error("La respuesta de contadores tiene un formato inválido.");
    }

    document.querySelectorAll("[data-site-visits]").forEach((element) => {
      element.textContent = data.visits.toLocaleString("es-MX");
    });

    document.querySelectorAll("[data-request-count]").forEach((element) => {
      const count = data.requests[element.dataset.requestCount];
      element.textContent = Number.isSafeInteger(count)
        ? count.toLocaleString("es-MX")
        : "No disponible";
    });
  }

  async function recordEvent(eventData) {
    const response = await fetch(apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(eventData),
      cache: "no-store",
      keepalive: true
    });

    if (!response.ok) {
      throw new Error(`El servidor de contadores respondió ${response.status}.`);
    }

    displayCounts(await response.json());
  }

  function reportCounterError(message, error) {
    document.querySelectorAll("[data-site-visits], [data-request-count]").forEach((element) => {
      element.textContent = "No disponible";
    });
    console.error(message, error);
  }

  recordEvent({ event: "visit" }).catch((error) => {
    reportCounterError("No se pudo registrar la visita.", error);
  });

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[href]");
    if (!link) return;

    let targetUrl;
    try {
      targetUrl = new URL(link.href, window.location.href);
    } catch (error) {
      console.error("No se pudo interpretar el enlace de solicitud.", error);
      return;
    }

    if (targetUrl.hostname !== "wa.me") return;

    const development = link.dataset.development || pageName;
    if (!allowedDevelopments.has(development)) return;

    recordEvent({ event: "request", development }).catch((error) => {
      console.error("No se pudo registrar el clic de WhatsApp.", error);
    });
  }, true);

  const developmentOptions = {
    "Solhá": "solha",
    "Nexum82": "nexum82",
    "Lakuun": "lakuun",
    "Martell": "martell",
    "Marela Life": "marela-life",
    "Hacienda Ticopo Gutiérrez": "hacienda-ticopo-gutierrez",
    "SUNO": "suno",
    "Monarca": "monarca",
    "Anthia Hunucmá": "anthia",
    "Marela Celestún": "marela-celestun",
    "Velamar": "velamar"
  };

  document.addEventListener("submit", (event) => {
    if (event.target.id !== "contact-form") return;

    const selectedDevelopment = new FormData(event.target).get("development");
    const development = developmentOptions[selectedDevelopment];
    if (!development) return;

    recordEvent({ event: "request", development }).catch((error) => {
      console.error("No se pudo registrar la solicitud del formulario.", error);
    });
  }, true);
})();
