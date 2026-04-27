const htmlRoot = document.documentElement;
const body = document.body;

const themeToggle = document.getElementById("themeToggle");
const themeLabel = document.getElementById("themeLabel");
const openSidebarBtn = document.getElementById("openSidebar");
const closeSidebarBtn = document.getElementById("closeSidebar");
const backdrop = document.getElementById("backdrop");
const globalSearch = document.getElementById("globalSearch");
const exportLink = document.querySelector("[data-export-link]");
const moduleTriggers = document.querySelectorAll("[data-module-trigger]");
const moduleLinks = document.querySelectorAll(".module-view-link");

function applyTheme(theme) {
  htmlRoot.dataset.theme = theme;

  if (themeLabel) {
    themeLabel.textContent = theme === "dark" ? "Modo claro" : "Modo oscuro";
  }

  localStorage.setItem("erp-theme", theme);
}

function initTheme() {
  const storedTheme = localStorage.getItem("erp-theme");

  if (storedTheme === "light" || storedTheme === "dark") {
    applyTheme(storedTheme);
    return;
  }

  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  applyTheme(prefersDark ? "dark" : "light");
}

function openSidebar() {
  body.classList.add("sidebar-open");
}

function closeSidebar() {
  body.classList.remove("sidebar-open");
}

function toggleModule(targetTrigger) {
  const selectedItem = targetTrigger.closest(".module-item");

  document.querySelectorAll(".module-item").forEach((item) => {
    const trigger = item.querySelector(".module-trigger");
    const shouldOpen = item === selectedItem ? !item.classList.contains("open") : false;

    item.classList.toggle("open", shouldOpen);

    if (trigger) {
      trigger.setAttribute("aria-expanded", String(shouldOpen));
    }
  });
}

function initAccordion() {
  moduleTriggers.forEach((trigger) => {
    trigger.addEventListener("click", () => toggleModule(trigger));
  });

  const activeItem = document.querySelector(".module-view-link.active")?.closest(".module-item");

  document.querySelectorAll(".module-item").forEach((item, index) => {
    const shouldOpen = activeItem ? item === activeItem : index === 0;
    const trigger = item.querySelector(".module-trigger");

    item.classList.toggle("open", shouldOpen);

    if (trigger) {
      trigger.setAttribute("aria-expanded", String(shouldOpen));
    }
  });
}

function filterRows(term) {
  const table = document.querySelector(".table-panel table") || document.querySelector(".panel table");

  if (!table) {
    return;
  }

  const tbody = table.querySelector("tbody");

  if (!tbody) {
    return;
  }

  const rows = Array.from(tbody.querySelectorAll("tr")).filter((row) => row.dataset.searchEmpty !== "1");
  const normalizedTerm = term.trim().toLowerCase();
  let visibleCount = 0;

  rows.forEach((row) => {
    const rowText = row.textContent.toLowerCase();
    const visible = !normalizedTerm || rowText.includes(normalizedTerm);

    row.style.display = visible ? "" : "none";

    if (visible) {
      visibleCount += 1;
    }
  });

  let emptyRow = tbody.querySelector('tr[data-search-empty="1"]');

  if (visibleCount === 0 && normalizedTerm) {
    if (!emptyRow) {
      emptyRow = document.createElement("tr");
      emptyRow.dataset.searchEmpty = "1";
      emptyRow.innerHTML = `<td colspan="${table.querySelectorAll("thead th").length || 1}">No se encontraron resultados para la busqueda actual.</td>`;
      tbody.appendChild(emptyRow);
    }

    emptyRow.style.display = "";
    return;
  }

  if (emptyRow) {
    emptyRow.remove();
  }
}

function updateExportLink(term) {
  if (!exportLink) {
    return;
  }

  const baseUrl = exportLink.dataset.exportUrl || exportLink.href;
  const exportUrl = new URL(baseUrl, window.location.origin);
  const normalizedTerm = term.trim();

  if (normalizedTerm) {
    exportUrl.searchParams.set("search", normalizedTerm);
  } else {
    exportUrl.searchParams.delete("search");
  }

  exportLink.href = exportUrl.toString();
}

function bindEvents() {
  if (themeToggle) {
    themeToggle.addEventListener("click", () => {
      const nextTheme = htmlRoot.dataset.theme === "dark" ? "light" : "dark";
      applyTheme(nextTheme);
    });
  }

  if (openSidebarBtn) {
    openSidebarBtn.addEventListener("click", openSidebar);
  }

  if (closeSidebarBtn) {
    closeSidebarBtn.addEventListener("click", closeSidebar);
  }

  if (backdrop) {
    backdrop.addEventListener("click", closeSidebar);
  }

  if (globalSearch) {
    globalSearch.addEventListener("input", (event) => {
      const term = event.target.value || "";
      filterRows(term);
      updateExportLink(term);
    });

    updateExportLink(globalSearch.value || "");
  }

  moduleLinks.forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth <= 960) {
        closeSidebar();
      }
    });
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 960) {
      closeSidebar();
    }
  });
}

function init() {
  initTheme();
  initAccordion();
  bindEvents();
}

init();
