(() => {
  "use strict";

  const body = document.body;
  const navToggle = document.querySelector(".nav-toggle");
  const navBackdrop = document.querySelector(".nav-backdrop");
  const searchDialog = document.querySelector(".search-dialog");
  const searchTrigger = document.querySelector(".search-trigger");
  const searchClose = document.querySelector(".search-close");
  const searchInput = document.querySelector("#documentation-search");
  const searchResults = document.querySelector(".search-results");
  let lastFocused = null;
  let lightboxLastFocused = null;

  const setNavigation = (open) => {
    body.classList.toggle("nav-open", open);
    navToggle?.setAttribute("aria-expanded", String(open));
    if (navBackdrop) navBackdrop.hidden = !open;
  };

  navToggle?.addEventListener("click", () => setNavigation(!body.classList.contains("nav-open")));
  navBackdrop?.addEventListener("click", () => setNavigation(false));
  document.querySelectorAll(".nav-link").forEach((link) => link.addEventListener("click", () => setNavigation(false)));

  const normalize = (value) => value.toLocaleLowerCase("de-DE").normalize("NFKD");
  const escapeHtml = (value) => value.replace(/[&<>'"]/g, (character) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;",
  })[character]);

  const excerpt = (text, query) => {
    const position = normalize(text).indexOf(query);
    const start = Math.max(0, position > -1 ? position - 70 : 0);
    const end = Math.min(text.length, start + 190);
    return `${start > 0 ? "…" : ""}${text.slice(start, end).trim()}${end < text.length ? "…" : ""}`;
  };

  const runSearch = () => {
    const query = normalize(searchInput.value.trim());
    searchResults.replaceChildren();
    if (query.length < 2) return;
    const terms = query.split(/\s+/).filter(Boolean);
    const source = Array.isArray(window.AREA_NET_SEARCH_INDEX) ? window.AREA_NET_SEARCH_INDEX : [];
    const matches = source.map((entry) => {
      const haystack = normalize(`${entry.title} ${entry.text}`);
      if (!terms.every((term) => haystack.includes(term))) return null;
      const titleMatches = terms.filter((term) => normalize(entry.title).includes(term)).length;
      const score = titleMatches * 10 + terms.reduce((total, term) => total + haystack.split(term).length - 1, 0);
      return { entry, score };
    }).filter(Boolean).sort((a, b) => b.score - a.score).slice(0, 18);

    if (!matches.length) {
      const empty = document.createElement("li");
      empty.className = "search-empty";
      empty.textContent = "Keine passenden Abschnitte gefunden.";
      searchResults.append(empty);
      return;
    }

    matches.forEach(({ entry }) => {
      const item = document.createElement("li");
      const link = document.createElement("a");
      link.className = "search-result";
      link.href = `${body.dataset.rootPrefix || ""}${entry.url}`;
      link.innerHTML = `<strong>${escapeHtml(entry.title)}</strong><span>${escapeHtml(excerpt(entry.text, terms[0]))}</span>`;
      item.append(link);
      searchResults.append(item);
    });
  };

  const openSearch = () => {
    lastFocused = document.activeElement;
    searchDialog.hidden = false;
    body.style.overflow = "hidden";
    requestAnimationFrame(() => searchInput.focus());
  };

  const closeSearch = () => {
    searchDialog.hidden = true;
    body.style.overflow = "";
    searchInput.value = "";
    searchResults.replaceChildren();
    lastFocused?.focus();
  };

  searchTrigger?.addEventListener("click", openSearch);
  searchClose?.addEventListener("click", closeSearch);
  searchDialog?.addEventListener("click", (event) => { if (event.target === searchDialog) closeSearch(); });
  searchInput?.addEventListener("input", runSearch);

  const lightbox = document.createElement("div");
  lightbox.className = "diagram-lightbox";
  lightbox.hidden = true;
  lightbox.setAttribute("role", "dialog");
  lightbox.setAttribute("aria-modal", "true");
  lightbox.setAttribute("aria-labelledby", "diagram-lightbox-title");
  lightbox.innerHTML = `
    <div class="lightbox-shell">
      <header class="lightbox-toolbar">
        <div class="lightbox-heading">
          <strong id="diagram-lightbox-title">Abbildung</strong>
          <span>Mit den Schaltflächen vergrößern oder verkleinern</span>
        </div>
        <div class="lightbox-actions">
          <button type="button" data-lightbox-action="out" aria-label="Verkleinern">−</button>
          <button type="button" class="lightbox-scale" data-lightbox-action="reset" aria-label="Zoom zurücksetzen">100 %</button>
          <button type="button" data-lightbox-action="in" aria-label="Vergrößern">+</button>
          <button type="button" class="lightbox-close" data-lightbox-action="close" aria-label="Zoomansicht schließen">×</button>
        </div>
      </header>
      <div class="lightbox-viewport" tabindex="0">
        <div class="lightbox-canvas"></div>
      </div>
    </div>`;
  body.append(lightbox);

  const lightboxTitle = lightbox.querySelector("#diagram-lightbox-title");
  const lightboxCanvas = lightbox.querySelector(".lightbox-canvas");
  const lightboxViewport = lightbox.querySelector(".lightbox-viewport");
  const lightboxScale = lightbox.querySelector(".lightbox-scale");
  const lightboxZoomOut = lightbox.querySelector('[data-lightbox-action="out"]');
  const lightboxZoomIn = lightbox.querySelector('[data-lightbox-action="in"]');
  let activeVisual = null;
  let activePlaceholder = null;
  let zoom = 1;

  const findFigureTitle = (element) => {
    const panel = element.closest("figure, .diagram-panel");
    const explicitCaption = panel?.querySelector("figcaption")?.textContent.trim();
    if (explicitCaption) return explicitCaption;
    let sibling = panel?.previousElementSibling;
    while (sibling && !/^H[1-4]$/.test(sibling.tagName)) sibling = sibling.previousElementSibling;
    return sibling?.textContent.replace("#", "").trim() || element.getAttribute("alt") || "Technische Abbildung";
  };

  const setZoom = (value) => {
    zoom = Math.min(3, Math.max(0.75, value));
    lightboxCanvas.style.width = `${Math.round(zoom * 100)}%`;
    lightboxScale.textContent = `${Math.round(zoom * 100)} %`;
    lightboxZoomOut.disabled = zoom <= 0.75;
    lightboxZoomIn.disabled = zoom >= 3;
  };

  const closeLightbox = () => {
    if (lightbox.hidden) return;
    if (activeVisual && activePlaceholder) {
      activeVisual.classList.remove("lightbox-visual");
      activePlaceholder.replaceWith(activeVisual);
    }
    activeVisual = null;
    activePlaceholder = null;
    lightboxCanvas.replaceChildren();
    lightboxCanvas.style.width = "";
    lightbox.hidden = true;
    body.classList.remove("lightbox-open");
    lightboxLastFocused?.focus();
  };

  const openLightbox = (trigger, visual) => {
    if (!visual || !lightbox.hidden) return;
    if (!searchDialog.hidden) closeSearch();
    lightboxLastFocused = trigger;
    activeVisual = visual;
    activePlaceholder = document.createComment("Abbildung in Zoomansicht");
    visual.replaceWith(activePlaceholder);
    visual.classList.add("lightbox-visual");
    lightboxCanvas.append(visual);
    lightboxTitle.textContent = findFigureTitle(trigger);
    lightbox.hidden = false;
    body.classList.add("lightbox-open");
    setZoom(1);
    lightboxViewport.scrollTo(0, 0);
    requestAnimationFrame(() => lightbox.querySelector(".lightbox-close").focus());
  };

  lightbox.addEventListener("click", (event) => {
    if (event.target === lightbox) closeLightbox();
    const action = event.target.closest("[data-lightbox-action]")?.dataset.lightboxAction;
    if (action === "close") closeLightbox();
    if (action === "out") setZoom(zoom - 0.25);
    if (action === "reset") setZoom(1);
    if (action === "in") setZoom(zoom + 0.25);
  });

  const prepareLightboxes = () => {
    document.querySelectorAll(".diagram-panel .mermaid").forEach((diagram) => {
      const svg = diagram.querySelector("svg");
      const panel = diagram.closest(".diagram-panel");
      if (!svg || !panel || diagram.dataset.lightboxReady) return;
      diagram.dataset.lightboxReady = "true";
      diagram.classList.add("is-zoomable");
      diagram.setAttribute("role", "button");
      diagram.setAttribute("tabindex", "0");
      const title = findFigureTitle(diagram);
      diagram.setAttribute("aria-label", `${title} vergrößern`);
      panel.classList.add("has-lightbox");

      const button = document.createElement("button");
      button.type = "button";
      button.className = "diagram-zoom";
      button.innerHTML = '<span aria-hidden="true">⌕</span> Vergrößern';
      button.setAttribute("aria-label", `${title} in Zoomansicht öffnen`);
      button.addEventListener("click", () => openLightbox(button, diagram.querySelector("svg")));
      panel.prepend(button);

      diagram.addEventListener("click", () => openLightbox(diagram, diagram.querySelector("svg")));
      diagram.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          openLightbox(diagram, diagram.querySelector("svg"));
        }
      });
    });

    document.querySelectorAll(".content img:not(.lightbox-visual)").forEach((image) => {
      if (image.dataset.lightboxReady) return;
      image.dataset.lightboxReady = "true";
      image.classList.add("is-zoomable");
      image.setAttribute("role", "button");
      image.setAttribute("tabindex", "0");
      image.setAttribute("aria-label", `${image.alt || "Abbildung"} vergrößern`);
      image.addEventListener("click", () => openLightbox(image, image));
      image.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          openLightbox(image, image);
        }
      });
    });
  };

  document.addEventListener("keydown", (event) => {
    if (!lightbox.hidden) {
      if (event.key === "Escape") closeLightbox();
      else if (event.key === "+" || event.key === "=") setZoom(zoom + 0.25);
      else if (event.key === "-") setZoom(zoom - 0.25);
      else if (event.key === "0") setZoom(1);
      else if (event.key === "Tab") {
        const controls = [...lightbox.querySelectorAll("button:not(:disabled), [tabindex='0']")];
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      openSearch();
    } else if (event.key === "Escape") {
      if (!searchDialog.hidden) closeSearch();
      else setNavigation(false);
    }
  });

  const tocLinks = [...document.querySelectorAll(".page-toc a")];
  if (tocLinks.length && "IntersectionObserver" in window) {
    const targets = tocLinks.map((link) => document.querySelector(link.hash)).filter(Boolean);
    const observer = new IntersectionObserver((entries) => {
      const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (!visible.length) return;
      tocLinks.forEach((link) => link.classList.toggle("is-current", link.hash === `#${visible[0].target.id}`));
    }, { rootMargin: "-15% 0px -75% 0px" });
    targets.forEach((target) => observer.observe(target));
  }

  if (window.mermaid) {
    window.mermaid.initialize({
      startOnLoad: false,
      securityLevel: "strict",
      theme: "base",
      fontFamily: "Arial, Helvetica, sans-serif",
      themeVariables: {
        primaryColor: "#fff2f3",
        primaryTextColor: "#1d1d1b",
        primaryBorderColor: "#c0001a",
        lineColor: "#656565",
        secondaryColor: "#f4f4f4",
        tertiaryColor: "#ffffff",
      },
    });
    window.mermaid.run({ querySelector: ".mermaid" })
      .then(prepareLightboxes)
      .catch(() => {
        document.querySelectorAll(".diagram-panel").forEach((panel) => panel.classList.add("diagram-error"));
      });
  } else {
    document.querySelectorAll(".diagram-panel").forEach((panel) => panel.classList.add("diagram-error"));
  }
})();
