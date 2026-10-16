
(function () {
  "use strict";

  // ---------- TAB NAVIGATION ----------
  function switchTab(name, updateUrl = false) {
    if (name === "home") {
      const homeTab = document.getElementById("tab-home");
      if (homeTab) {
        name = "home";
      } else {
        name = "overview";
      }
    }

    const target = document.getElementById("tab-" + name);

    // Ignore invalid tab names
    if (!target) return;

    document.body.dataset.tab = name;

    document.querySelectorAll(".dash-section").forEach((section) => {
      section.classList.toggle("active", section.id === "tab-" + name);
    });

    document.querySelectorAll(".nav-link[data-tab]").forEach((link) => {
      link.classList.toggle("active", link.dataset.tab === name);
    });

    if (updateUrl) {
      const url = new URL(window.location.href);
      url.searchParams.set("tab", name);
      url.searchParams.delete("msg");
      url.searchParams.delete("type");
      window.history.replaceState({}, "", url);
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // Only navigation links should handle tab clicks.
  // Do not use [data-tab] here because other elements may have that attribute.
  document.querySelectorAll("a[data-tab]").forEach((link) => {
    link.addEventListener("click", (event) => {
      event.preventDefault();
      switchTab(link.dataset.tab, true);
    });
  });

  // Open the tab specified in the URL.
  const params = new URLSearchParams(window.location.search);
  const defaultTab = document.getElementById("tab-home") ? "home" : "overview";
  switchTab(params.get("tab") || document.body.dataset.tab || defaultTab);
  // Make tab navigation available to other dashboard functions.
  window.switchTab = switchTab;


  // ---------- APPOINTMENT HISTORY TOGGLE ----------
  const historyToggle = document.getElementById("historyToggle");
  const historyContent = document.getElementById("appointmentHistoryContent");
  const historyIcon = document.getElementById("historyToggleIcon");

  if (historyToggle && historyContent && historyIcon) {
    historyToggle.addEventListener("click", function () {
      const isExpanded =
        this.getAttribute("aria-expanded") === "true";

      this.setAttribute("aria-expanded", String(!isExpanded));
      historyContent.classList.toggle("is-collapsed", isExpanded);
      historyIcon.textContent = isExpanded ? "+" : "−";
    });
  }


  // ---------- PASSWORD SHOW / HIDE ----------
  document.querySelectorAll(".toggle-password").forEach((button) => {
    button.addEventListener("click", () => {
      const targetId = button.dataset.target;
      const input = targetId
        ? document.getElementById(targetId)
        : button.parentElement.querySelector("input");

      if (!input) return;

      const showing = input.type === "text";
      input.type = showing ? "password" : "text";

      const eye = button.querySelector(".icon-eye");
      const eyeOff = button.querySelector(".icon-eye-off");

      if (eye) eye.style.display = showing ? "block" : "none";
      if (eyeOff) eyeOff.style.display = showing ? "none" : "block";

      button.setAttribute(
        "aria-label",
        showing ? "Show password" : "Hide password"
      );
    });
  });

  // Support existing HTML that calls onclick="togglePassword(this)".
  window.togglePassword = function (button) {
    const input = button?.parentElement?.querySelector("input");
    if (!input) return;

    input.type = input.type === "password" ? "text" : "password";
  };


  // ---------- LIVE PASSWORD REQUIREMENTS ----------
  const newPass = document.getElementById("acc-newpass");
  const requirementsList = document.getElementById("pwRequirements");

  if (newPass && requirementsList) {
    const rules = {
      length: (value) => value.length >= 8,
      number: (value) => /[0-9]/.test(value),
      special: (value) => /[^A-Za-z0-9]/.test(value)
    };

    function updatePasswordRequirements() {
      const value = newPass.value;

      requirementsList.querySelectorAll("li[data-rule]").forEach((item) => {
        const rule = rules[item.dataset.rule];

        if (rule) {
          item.classList.toggle("met", rule(value));
        }
      });
    }

    newPass.addEventListener("input", updatePasswordRequirements);
    updatePasswordRequirements();
  }


  // ---------- LOCAL DATE HELPER ----------
  function getLocalDateString() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
  }


  // ---------- APPOINTMENT DATE VALIDATION ----------
  const dateInput = document.getElementById("ap-date");

  if (dateInput) {
    dateInput.min = getLocalDateString();
  }


  // ---------- DATE OF BIRTH VALIDATION ----------
  const dobInput = document.getElementById("pf-dob");

  if (dobInput) {
    dobInput.max = getLocalDateString();
  }


  // ---------- APPOINTMENT TIME OPTIONS ----------
  const CENTER_HOURS = window.CENTER_HOURS || {};

  function formatMinutes(minutes) {
    const hour = Math.floor(minutes / 60);
    const minute = minutes % 60;
    const hour12 = ((hour + 11) % 12) + 1;
    const period = hour >= 12 ? "PM" : "AM";

    return `${hour12}:${String(minute).padStart(2, "0")} ${period}`;
  }

  function updateTimeOptions() {
    const select = document.getElementById("timeSelect");
    const hint = document.getElementById("hoursHint");
    const centerSelect = document.getElementById("centerSelect");

    if (!select || !centerSelect) return;

    const hours = CENTER_HOURS[centerSelect.value];

    if (!hours) {
      select.innerHTML =
        '<option value="">Select a health center first</option>';

      if (hint) hint.textContent = "";
      return;
    }

    select.innerHTML = '<option value="">Select time</option>';

    for (
      let minutes = Number(hours.open);
      minutes + 30 <= Number(hours.close);
      minutes += 30
    ) {
      const hour = Math.floor(minutes / 60);
      const minute = minutes % 60;

      const value =
        `${String(hour).padStart(2, "0")}:` +
        `${String(minute).padStart(2, "0")}:00`;

      const option = document.createElement("option");
      option.value = value;
      option.textContent = formatMinutes(minutes);
      select.appendChild(option);
    }

    if (hint) {
      hint.textContent =
        `Open ${formatMinutes(Number(hours.open))} – ` +
        `${formatMinutes(Number(hours.close))}` +
        (hours.parsed
          ? ""
          : " (default hours; this center has no readable hours on file)");
    }
  }

  window.updateTimeOptions = updateTimeOptions;


  // ---------- PRESELECT CENTER FOR BOOKING ----------
  function bookAt(centerId) {
    switchTab("appointments");

    const centerSelect = document.getElementById("centerSelect");

    if (centerSelect) {
      centerSelect.value = centerId;
      updateTimeOptions();
    }
  }

  window.bookAt = bookAt;


  // ---------- CANCEL APPOINTMENT MODAL ----------
  function openCancelModal(id) {
    const cancelId = document.getElementById("cancelId");
    const modal = document.getElementById("cancelModal");

    if (cancelId) cancelId.value = id;
    if (modal) modal.classList.add("open");
  }

  function closeCancelModal() {
    document.getElementById("cancelModal")?.classList.remove("open");
  }

  window.openCancelModal = openCancelModal;
  window.closeCancelModal = closeCancelModal;

  const cancelModal = document.getElementById("cancelModal");

  if (cancelModal) {
    cancelModal.addEventListener("click", (event) => {
      if (event.target.id === "cancelModal") {
        closeCancelModal();
      }
    });
  }
})();