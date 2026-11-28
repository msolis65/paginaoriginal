(() => {
  if (document.getElementById('site-image-viewer')) return;

  const style = document.createElement('style');
  style.textContent = `
    .image-zoomable { cursor: zoom-in; }
    .image-zoomable:focus-visible { outline: 3px solid #c3a36a; outline-offset: 3px; }
    #site-image-viewer[hidden] { display: none !important; }
    #site-image-viewer { position: fixed; inset: 0; z-index: 2147483647; display: grid; place-items: center; padding: 56px 24px 64px; background: rgba(7, 18, 17, .9); backdrop-filter: blur(5px); }
    #site-image-viewer img { display: block; width: auto; height: auto; max-width: min(94vw, 1800px); max-height: 88vh; object-fit: contain; border-radius: 10px; box-shadow: 0 20px 70px rgba(0,0,0,.4); }
    #site-image-viewer-close { position: absolute; top: 16px; right: 18px; display: grid; width: 48px; height: 48px; place-items: center; border: 1px solid rgba(255,255,255,.55); border-radius: 50%; background: rgba(16,42,42,.8); color: #fff; font: 32px/1 Arial,sans-serif; cursor: pointer; }
    #site-image-viewer-close:hover, #site-image-viewer-close:focus-visible { background: #173c38; outline: 2px solid #e0c58f; outline-offset: 2px; }
    #site-image-viewer-caption { position: absolute; right: 80px; bottom: 17px; left: 24px; color: #fff; text-align: center; font: 14px/1.4 Arial,sans-serif; }
    @media(max-width:600px) { #site-image-viewer { padding: 64px 12px 72px; } #site-image-viewer-close { top: 10px; right: 10px; } #site-image-viewer-caption { right: 12px; bottom: 14px; left: 12px; } }
    @media(prefers-reduced-motion:reduce) { #site-image-viewer { backdrop-filter: none; } }
  `;
  document.head.append(style);

  const viewer = document.createElement('div');
  viewer.id = 'site-image-viewer';
  viewer.hidden = true;
  viewer.setAttribute('role', 'dialog');
  viewer.setAttribute('aria-modal', 'true');
  viewer.setAttribute('aria-label', 'Imagen ampliada');

  const closeButton = document.createElement('button');
  closeButton.id = 'site-image-viewer-close';
  closeButton.type = 'button';
  closeButton.setAttribute('aria-label', 'Cerrar imagen ampliada');
  closeButton.innerHTML = '&times;';

  const largeImage = document.createElement('img');
  largeImage.alt = '';
  largeImage.draggable = false;

  const caption = document.createElement('p');
  caption.id = 'site-image-viewer-caption';

  viewer.append(closeButton, largeImage, caption);
  document.body.append(viewer);

  let trigger = null;
  let previousOverflow = '';

  function eligible(image) {
    if (!image || image.dataset.noLightbox !== undefined || image.closest('#site-image-viewer')) return false;
    if (image.closest('.admin-brand, .simple-page-brand, .brand, .brand-logo')) return false;
    const source = image.getAttribute('src') || image.currentSrc || '';
    return !/(^|\/)logos\//i.test(source) && !/tierrayuc\.(?:jpg|png|webp)$/i.test(source);
  }

  function openViewer(image) {
    if (!eligible(image)) return;
    trigger = image;
    const linkedImage = image.closest('a[href]');
    const linkedSource = linkedImage && /\.(?:avif|gif|jpe?g|png|webp)(?:[?#]|$)/i.test(linkedImage.href) ? linkedImage.href : '';
    largeImage.src = linkedSource || image.currentSrc || image.src;
    largeImage.alt = image.alt || 'Imagen ampliada';
    caption.textContent = image.alt || '';
    caption.hidden = !caption.textContent;
    previousOverflow = document.documentElement.style.overflow;
    document.documentElement.style.overflow = 'hidden';
    viewer.hidden = false;
    closeButton.focus({ preventScroll: true });
  }

  function closeViewer() {
    if (viewer.hidden) return;
    viewer.hidden = true;
    largeImage.removeAttribute('src');
    document.documentElement.style.overflow = previousOverflow;
    if (trigger && trigger.isConnected) trigger.focus({ preventScroll: true });
    trigger = null;
  }

  document.querySelectorAll('img').forEach((image) => {
    if (!eligible(image)) return;
    image.classList.add('image-zoomable');
    if (!image.closest('a') && !image.hasAttribute('tabindex')) {
      image.tabIndex = 0;
      image.setAttribute('role', 'button');
      image.setAttribute('aria-label', image.alt ? `Ampliar imagen: ${image.alt}` : 'Ampliar imagen');
    }
  });

  document.addEventListener('click', (event) => {
    const image = event.target instanceof Element ? event.target.closest('img') : null;
    if (!image || !eligible(image)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openViewer(image);
  }, true);

  document.addEventListener('keydown', (event) => {
    if (viewer.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeViewer();
      return;
    }
    if (event.key === 'Tab') {
      event.preventDefault();
      closeButton.focus();
    }
  });

  closeButton.addEventListener('click', closeViewer);
  viewer.addEventListener('click', (event) => {
    if (event.target === viewer) closeViewer();
  });
})();
