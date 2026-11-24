(() => {
  const form = document.querySelector("#contact-form");
  if (!form) return;

  const phoneInput = document.querySelector("#phone");
  const phoneCodeInput = document.querySelector("#phone-code");
  const consentInput = document.querySelector("#phone-consent");
  const consentWrap = document.querySelector("#phone-consent-wrap");
  const sendCodeButton = document.querySelector("#phone-send-code");
  const codeStep = document.querySelector("#phone-code-step");
  const verifyCodeButton = document.querySelector("#phone-verify-code");
  const useAnotherButton = document.querySelector("#phone-use-another");
  const changeNumberButton = document.querySelector("#phone-change-number");
  const deleteAccountButton = document.querySelector("#phone-delete-account");
  const submitButton = document.querySelector("#contact-submit");
  const status = document.querySelector("#phone-auth-status");
  const endpoint = new URL("api/customer-auth.php", document.currentScript.src);

  let mode = "checking";
  let csrfToken = "";
  let pendingPhone = "";
  let verifiedPhone = "";

  function setStatus(message, state) {
    status.textContent = message;
    status.dataset.state = state || "info";
  }

  function updateCsrf(data) {
    if (typeof data.csrf === "string" && data.csrf !== "") {
      csrfToken = data.csrf;
    }
  }

  function setLegacyMode(message) {
    mode = "legacy";
    phoneInput.readOnly = false;
    consentWrap.hidden = true;
    sendCodeButton.hidden = true;
    codeStep.hidden = true;
    useAnotherButton.hidden = true;
    changeNumberButton.hidden = true;
    deleteAccountButton.hidden = true;
    submitButton.disabled = false;
    submitButton.textContent = "Enviar por WhatsApp →";
    setStatus(message, "info");
  }

  function setUnavailable(message) {
    mode = "error";
    submitButton.disabled = true;
    submitButton.textContent = "Verificación no disponible";
    setStatus(message, "error");
  }

  function setActiveMode(message) {
    mode = "active";
    verifiedPhone = "";
    pendingPhone = "";
    phoneInput.readOnly = false;
    consentInput.checked = false;
    consentWrap.hidden = false;
    sendCodeButton.hidden = false;
    sendCodeButton.disabled = false;
    codeStep.hidden = true;
    phoneCodeInput.value = "";
    useAnotherButton.hidden = true;
    changeNumberButton.hidden = true;
    deleteAccountButton.hidden = true;
    submitButton.disabled = true;
    submitButton.textContent = "Verifica tu teléfono para continuar";
    setStatus(message, "info");
  }

  function setAuthenticated(phone, message) {
    mode = "authenticated";
    verifiedPhone = phone;
    pendingPhone = "";
    phoneInput.value = phone;
    phoneInput.readOnly = true;
    consentWrap.hidden = true;
    sendCodeButton.hidden = true;
    codeStep.hidden = true;
    useAnotherButton.hidden = true;
    changeNumberButton.hidden = false;
    deleteAccountButton.hidden = false;
    submitButton.disabled = false;
    submitButton.textContent = "Enviar por WhatsApp →";
    setStatus(message, "success");
  }

  async function readResponse(response) {
    let data;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error("El servidor de verificación devolvió una respuesta inválida.");
    }
    updateCsrf(data);
    if (!response.ok) {
      throw new Error(data.error || "No se pudo completar la verificación.");
    }
    return data;
  }

  async function postAction(action, values) {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      cache: "no-store",
      body: JSON.stringify({ action, csrf: csrfToken, ...values }),
    });
    return readResponse(response);
  }

  function showPendingPhone(phone, message) {
    mode = "active";
    pendingPhone = phone;
    phoneInput.value = phone;
    phoneInput.readOnly = true;
    consentWrap.hidden = true;
    sendCodeButton.hidden = true;
    codeStep.hidden = false;
    useAnotherButton.hidden = false;
    changeNumberButton.hidden = true;
    deleteAccountButton.hidden = true;
    submitButton.disabled = true;
    submitButton.textContent = "Confirma tu teléfono para continuar";
    phoneCodeInput.focus();
    setStatus(message, "info");
  }

  sendCodeButton.addEventListener("click", async () => {
    if (!consentInput.checked) {
      setStatus("Acepta el aviso para guardar el teléfono verificado en tu cuenta.", "error");
      consentInput.focus();
      return;
    }
    if (!phoneInput.value.trim()) {
      setStatus("Ingresa tu teléfono con código de país para recibir el código.", "error");
      phoneInput.focus();
      return;
    }

    sendCodeButton.disabled = true;
    setStatus("Enviando el código por WhatsApp…", "info");
    try {
      const data = await postAction("request_code", {
        phone: phoneInput.value.trim(),
        consent: true,
      });
      showPendingPhone(data.phone, data.message);
    } catch (error) {
      sendCodeButton.disabled = false;
      setStatus(error.message, "error");
    }
  });

  verifyCodeButton.addEventListener("click", async () => {
    const code = phoneCodeInput.value.trim();
    if (!/^[0-9]{6}$/.test(code)) {
      setStatus("Ingresa el código de seis dígitos que recibiste.", "error");
      phoneCodeInput.focus();
      return;
    }

    verifyCodeButton.disabled = true;
    setStatus("Verificando tu teléfono…", "info");
    try {
      const data = await postAction("verify_code", {
        phone: pendingPhone,
        code,
      });
      setAuthenticated(data.phone, data.message);
    } catch (error) {
      setStatus(error.message, "error");
    } finally {
      verifyCodeButton.disabled = false;
    }
  });

  useAnotherButton.addEventListener("click", () => {
    pendingPhone = "";
    phoneInput.readOnly = false;
    phoneCodeInput.value = "";
    consentInput.checked = false;
    consentWrap.hidden = false;
    sendCodeButton.hidden = false;
    sendCodeButton.disabled = false;
    codeStep.hidden = true;
    useAnotherButton.hidden = true;
    setStatus("Puedes corregir el número y solicitar otro código.", "info");
    phoneInput.focus();
  });

  changeNumberButton.addEventListener("click", async () => {
    changeNumberButton.disabled = true;
    try {
      const data = await postAction("logout", {});
      updateCsrf(data);
      phoneInput.value = "";
      setActiveMode(data.message);
      phoneInput.focus();
    } catch (error) {
      setStatus(error.message, "error");
    } finally {
      changeNumberButton.disabled = false;
    }
  });

  deleteAccountButton.addEventListener("click", async () => {
    if (!window.confirm("¿Eliminar tu cuenta y el teléfono verificado guardado?")) return;

    deleteAccountButton.disabled = true;
    try {
      const data = await postAction("delete_account", {});
      updateCsrf(data);
      phoneInput.value = "";
      setActiveMode(data.message);
      phoneInput.focus();
    } catch (error) {
      setStatus(error.message, "error");
    } finally {
      deleteAccountButton.disabled = false;
    }
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    if (mode === "checking" || mode === "error") {
      setStatus("La verificación aún no está lista. Intenta de nuevo más tarde.", "error");
      return;
    }
    if (mode === "active" && !verifiedPhone) {
      setStatus("Verifica tu teléfono por WhatsApp antes de enviar la solicitud.", "error");
      return;
    }
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    const phone = mode === "authenticated"
      ? verifiedPhone
      : String(formData.get("phone") || "");
    const message = [
      "Hola, quiero solicitar información inmobiliaria.",
      `Nombre: ${formData.get("name")}`,
      `Teléfono verificado: ${phone}`,
      `Desarrollo de interés: ${formData.get("development")}`,
      `Mensaje: ${formData.get("message")}`,
    ].join("\n");
    const whatsappUrl = `https://wa.me/${form.dataset.whatsappNumber}?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, "_blank", "noopener,noreferrer");
  });

  phoneCodeInput.addEventListener("input", () => {
    phoneCodeInput.value = phoneCodeInput.value.replace(/[^0-9]/g, "").slice(0, 6);
  });

  async function initialize() {
    if (window.location.protocol === "file:") {
      setLegacyMode("La verificación de cuenta funciona en hosting con HTTPS y PHP; en esta vista local el formulario conserva el envío manual.");
      return;
    }

    try {
      const response = await fetch(`${endpoint}?action=state`, {
        credentials: "same-origin",
        cache: "no-store",
      });
      const data = await readResponse(response);
      if (!data.configured) {
        setLegacyMode("La verificación de WhatsApp está pendiente de configuración. Por ahora el teléfono se enviará manualmente y podrá editarse.");
        return;
      }

      setActiveMode("Verifica tu WhatsApp para guardar el teléfono en tu cuenta y desbloquear el envío.");
      if (data.authenticated && typeof data.phone === "string") {
        setAuthenticated(data.phone, "Tu teléfono está verificado y asociado a tu cuenta.");
      }
    } catch (error) {
      setUnavailable(`${error.message} No se enviará el formulario hasta que podamos validar el estado de la cuenta.`);
    }
  }

  initialize();
})();
