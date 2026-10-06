(function (window, document) {
  "use strict";

  class GFSelect {
    constructor(selector, options = {}) {
      const select = typeof selector === "string" ? document.querySelector(selector) : selector;
      if (!(select instanceof HTMLSelectElement)) return;

      const currentInstance = GFSelect.instances.get(select);
      if (currentInstance) return currentInstance;

      this.select = select;
      this.settings = Object.assign({
        searchable: true,
        maxHeight: 250,
        wrapperClass: "",
        toggleClass: "",
        menuClass: "",
        searchWrapperClass: "",
        searchInputClass: "",
        searchLabel: "Buscar...",
        emptyLabel: "Sin resultados",
        placeholder: "Seleccionar",
        autoFocusSearch: true,
        prefixHtml: "",
        multiple: null,
        maxSelections: null,
        summaryLimit: 2,
        closeOnSelect: null,
        loadStyles: true,
        hideNative: false,
        onChange: null,
        onReady: null
      }, options);

      if (this.settings.multiple !== null) this.select.multiple = Boolean(this.settings.multiple);
      this.settings.multiple = this.select.multiple;
      this.configuredMaxSelections = options.maxSelections === undefined ? null : options.maxSelections;
      this.configuredCloseOnSelect = options.closeOnSelect === undefined ? null : options.closeOnSelect;
      this.settings.closeOnSelect = this.settings.closeOnSelect === null
        ? !this.settings.multiple
        : Boolean(this.settings.closeOnSelect);
      this.settings.maxSelections = this.resolveMaximum(this.configuredMaxSelections);
      this.originalDisplay = this.select.style.display;
      this.destroyed = false;
      this.invalid = false;
      this.uid = "gf-select-" + (++GFSelect.uid);
      GFSelect.instances.set(this.select, this);

      if (this.settings.loadStyles) {
        GFSelect.ensureStyles().then(() => this.initialize());
      } else {
        this.initialize();
      }
    }

    static getInstance(selector) {
      const select = typeof selector === "string" ? document.querySelector(selector) : selector;
      return select ? GFSelect.instances.get(select) || null : null;
    }

    static ensureStyles() {
      if (GFSelect.stylesPromise) return GFSelect.stylesPromise;

      GFSelect.stylesPromise = new Promise((resolve) => {
        const existing = Array.from(document.querySelectorAll("link[rel='stylesheet']")).find((link) =>
          String(link.getAttribute("href") || "").includes("gf-select.css")
        );
        if (existing) {
          resolve();
          return;
        }

        const script = Array.from(document.querySelectorAll("script[src]")).find((item) =>
          String(item.getAttribute("src") || "").includes("gf-select.js")
        );
        if (!script) {
          resolve();
          return;
        }

        const source = script.getAttribute("src");
        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = source.substring(0, source.lastIndexOf("/") + 1) + "gf-select.css";
        link.addEventListener("load", resolve, { once: true });
        link.addEventListener("error", resolve, { once: true });
        document.head.insertBefore(link, document.head.firstChild);
      });

      return GFSelect.stylesPromise;
    }

    initialize() {
      if (this.destroyed || this.wrapper) return;
      this.render();
      this.bindEvents();
      this.observeSelect();
      this.refresh();
      if (typeof this.settings.onReady === "function") this.settings.onReady(this);
    }

    render() {
      if (this.settings.hideNative) this.select.style.display = "none";
      this.select.classList.add("gf-select-native");

      this.wrapper = document.createElement("div");
      this.wrapper.className = "gf-select-wrapper";
      this.wrapper.classList.toggle("is-multiple", this.settings.multiple);
      if (this.select.id) this.wrapper.id = "gf-" + this.select.id;
      this.addCleanedClasses(this.wrapper, this.settings.wrapperClass);
      this.select.parentNode.insertBefore(this.wrapper, this.select);
      this.wrapper.appendChild(this.select);

      this.toggle = document.createElement("button");
      this.toggle.type = "button";
      this.toggle.className = "gf-select-toggle";
      this.toggle.id = this.uid + "-toggle";
      this.toggle.setAttribute("aria-haspopup", "listbox");
      this.toggle.setAttribute("aria-expanded", "false");
      this.toggle.setAttribute("aria-controls", this.uid + "-menu");
      this.addCleanedClasses(this.toggle, this.settings.toggleClass);
      this.wrapper.appendChild(this.toggle);

      this.selection = document.createElement("span");
      this.selection.className = "gf-select-selection";
      this.toggle.appendChild(this.selection);

      this.menu = document.createElement("div");
      this.menu.className = "gf-select-menu";
      this.menu.id = this.uid + "-menu";
      this.menu.setAttribute("role", "listbox");
      this.menu.setAttribute("aria-labelledby", this.toggle.id);
      if (this.settings.multiple) this.menu.setAttribute("aria-multiselectable", "true");
      this.addCleanedClasses(this.menu, this.settings.menuClass);
      this.wrapper.appendChild(this.menu);

      if (this.settings.searchable) {
        this.searchWrapper = document.createElement("div");
        this.searchWrapper.className = "gf-select-search-wrapper";
        this.addCleanedClasses(this.searchWrapper, this.settings.searchWrapperClass);

        this.searchInput = document.createElement("input");
        this.searchInput.type = "search";
        this.searchInput.className = "gf-select-search-input";
        this.searchInput.placeholder = this.settings.searchLabel;
        this.searchInput.setAttribute("aria-label", this.settings.searchLabel);
        this.addCleanedClasses(this.searchInput, this.settings.searchInputClass);
        this.searchWrapper.appendChild(this.searchInput);
        this.menu.appendChild(this.searchWrapper);
      }

      this.list = document.createElement("div");
      this.list.className = "gf-select-list";
      this.list.style.maxHeight = this.settings.maxHeight + "px";
      this.list.style.overflowY = "auto";
      this.menu.appendChild(this.list);

      this.ul = document.createElement("ul");
      this.list.appendChild(this.ul);

      this.empty = document.createElement("div");
      this.empty.className = "gf-select-empty";
      this.empty.textContent = this.settings.emptyLabel;
      this.empty.hidden = true;
      this.menu.appendChild(this.empty);

      if (this.settings.multiple && this.settings.maxSelections > 0) {
        this.limit = document.createElement("small");
        this.limit.className = "gf-select-limit";
        this.menu.appendChild(this.limit);
      }
    }

    bindEvents() {
      this.boundToggleMenu = this.toggleMenu.bind(this);
      this.boundOptionClick = this.handleOptionClick.bind(this);
      this.boundFilter = this.handleFilter.bind(this);
      this.boundNativeChange = this.handleNativeChange.bind(this);
      this.boundNativeFocus = this.handleNativeFocus.bind(this);
      this.boundDocumentPointer = this.handleDocumentPointer.bind(this);
      this.boundViewportChange = this.positionMenu.bind(this);
      this.boundToggleKeydown = this.handleToggleKeydown.bind(this);
      this.boundMenuKeydown = this.handleMenuKeydown.bind(this);

      this.toggle.addEventListener("click", this.boundToggleMenu);
      this.toggle.addEventListener("keydown", this.boundToggleKeydown);
      this.ul.addEventListener("click", this.boundOptionClick);
      this.menu.addEventListener("keydown", this.boundMenuKeydown);
      this.select.addEventListener("change", this.boundNativeChange);
      this.select.addEventListener("focus", this.boundNativeFocus);
      if (this.searchInput) this.searchInput.addEventListener("input", this.boundFilter);
    }

    observeSelect() {
      this.observer = new MutationObserver(() => {
        window.clearTimeout(this.refreshTimer);
        this.refreshTimer = window.setTimeout(() => this.refresh(), 0);
      });
      this.observer.observe(this.select, {
        attributes: true,
        childList: true,
        subtree: true,
        attributeFilter: ["disabled", "hidden", "label", "selected", "multiple", "data-max-selections"]
      });
    }

    handleNativeChange() {
      this.invalid = false;
      this.refresh();
    }

    handleNativeFocus() {
      if (this.select.form?.classList.contains("was-validated")) {
        this.setInvalid(!this.select.validity.valid);
      }
      this.focus();
    }

    handleDocumentPointer(event) {
      if (!this.wrapper.contains(event.target)) this.closeMenu();
    }

    handleOptionClick(event) {
      const item = event.target.closest(".gf-select-list-item");
      if (!item || item.disabled) return;
      event.preventDefault();
      this.selectOption(Number(item.dataset.optionIndex));
    }

    handleFilter(event) {
      this.filterOptions(event.target.value);
    }

    handleToggleKeydown(event) {
      if (["ArrowDown", "Enter", " "].includes(event.key)) {
        event.preventDefault();
        if (!this.isOpen()) this.openMenu();
        const selected = this.ul.querySelector(".gf-select-list-item.selected:not(:disabled)");
        const first = this.ul.querySelector(".gf-select-list-item:not(:disabled)");
        (selected || first)?.focus();
      }
    }

    handleMenuKeydown(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        this.closeMenu();
        this.toggle.focus();
      }
    }

    toggleMenu() {
      if (this.toggle.disabled) return;
      this.isOpen() ? this.closeMenu() : this.openMenu();
    }

    isOpen() {
      return Boolean(this.wrapper && this.wrapper.classList.contains("is-open"));
    }

    openMenu() {
      if (!this.menu || this.select.disabled) return;
      if (GFSelect.openInstance && GFSelect.openInstance !== this) GFSelect.openInstance.closeMenu();

      GFSelect.openInstance = this;
      this.wrapper.classList.add("is-open");
      this.toggle.classList.add("active");
      this.toggle.setAttribute("aria-expanded", "true");
      this.menu.style.display = "block";
      this.menu.style.visibility = "hidden";
      this.positionMenu();
      this.menu.style.visibility = "visible";

      document.addEventListener("pointerdown", this.boundDocumentPointer);
      window.addEventListener("resize", this.boundViewportChange);
      window.addEventListener("scroll", this.boundViewportChange, true);

      if (this.searchInput) {
        this.searchInput.value = "";
        this.filterOptions("");
        if (this.settings.autoFocusSearch) this.searchInput.focus();
      }

      this.ul.querySelector(".gf-select-list-item.selected")?.scrollIntoView({ block: "nearest" });
    }

    positionMenu() {
      if (!this.isOpen()) return;

      const gap = 6;
      const edge = 8;
      const rect = this.wrapper.getBoundingClientRect();
      const viewportWidth = document.documentElement.clientWidth;
      const viewportHeight = window.innerHeight;
      const width = Math.min(rect.width, viewportWidth - (edge * 2));
      const left = Math.max(edge, Math.min(rect.left, viewportWidth - width - edge));
      const spaceBelow = Math.max(0, viewportHeight - rect.bottom - edge - gap);
      const spaceAbove = Math.max(0, rect.top - edge - gap);
      const openAbove = spaceBelow < Math.min(this.settings.maxHeight, this.menu.scrollHeight) && spaceAbove > spaceBelow;
      const available = Math.max(96, openAbove ? spaceAbove : spaceBelow);
      const chromeHeight = Math.max(0, this.menu.scrollHeight - this.list.scrollHeight);

      this.list.style.maxHeight = Math.max(64, Math.min(this.settings.maxHeight, available - chromeHeight)) + "px";
      this.menu.style.width = width + "px";
      this.menu.style.left = left + "px";
      this.menu.classList.toggle("gf-select-menu-up", openAbove);

      const menuHeight = this.menu.offsetHeight;
      const top = openAbove
        ? Math.max(edge, rect.top - menuHeight - gap)
        : Math.min(viewportHeight - menuHeight - edge, rect.bottom + gap);
      this.menu.style.top = Math.max(edge, top) + "px";
    }

    closeMenu() {
      if (!this.menu) return;
      this.wrapper.classList.remove("is-open");
      this.toggle.classList.remove("active");
      this.toggle.setAttribute("aria-expanded", "false");
      this.menu.style.display = "none";
      document.removeEventListener("pointerdown", this.boundDocumentPointer);
      window.removeEventListener("resize", this.boundViewportChange);
      window.removeEventListener("scroll", this.boundViewportChange, true);
      if (GFSelect.openInstance === this) GFSelect.openInstance = null;
    }

    selectOption(index) {
      const option = this.select.options[index];
      if (!option || option.disabled || this.select.disabled) return;

      if (this.settings.multiple) {
        const selectedCount = this.select.selectedOptions.length;
        if (!option.selected && this.settings.maxSelections > 0 && selectedCount >= this.settings.maxSelections) return;
        option.selected = !option.selected;
      } else {
        this.select.value = option.value;
      }

      this.invalid = false;
      this.refresh();
      this.select.dispatchEvent(new Event("change", { bubbles: true }));

      if (typeof this.settings.onChange === "function") {
        if (this.settings.multiple) {
          const selected = Array.from(this.select.selectedOptions);
          this.settings.onChange(
            selected.map((item) => item.value),
            selected.map((item) => item.textContent)
          );
        } else {
          this.settings.onChange(option.value, option.textContent);
        }
      }

      if (this.settings.closeOnSelect) {
        this.closeMenu();
        this.toggle.focus();
      } else {
        this.positionMenu();
      }
    }

    refresh() {
      if (!this.wrapper || this.destroyed) return;
      this.settings.multiple = this.select.multiple;
      this.settings.maxSelections = this.resolveMaximum(this.configuredMaxSelections);
      if (this.configuredCloseOnSelect === null) this.settings.closeOnSelect = !this.settings.multiple;
      this.wrapper.classList.toggle("is-multiple", this.settings.multiple);
      if (this.settings.multiple) this.menu.setAttribute("aria-multiselectable", "true");
      else this.menu.removeAttribute("aria-multiselectable");
      this.wrapper.classList.toggle("is-disabled", this.select.disabled);
      this.toggle.disabled = this.select.disabled;
      if (this.select.disabled) this.closeMenu();
      this.renderSelection();
      this.renderOptions();
      this.syncValidity();
      if (this.isOpen()) this.positionMenu();
    }

    renderSelection() {
      this.selection.replaceChildren();
      if (this.settings.prefixHtml) {
        const prefix = document.createElement("span");
        prefix.className = "gf-select-prefix";
        prefix.innerHTML = this.settings.prefixHtml;
        this.selection.appendChild(prefix);
      }

      if (this.settings.multiple) {
        const selected = Array.from(this.select.selectedOptions);
        if (!selected.length) {
          this.appendSelectionText(this.settings.placeholder, "gf-select-placeholder");
        } else {
          const limit = Math.max(1, this.settings.summaryLimit);
          selected.slice(0, limit).forEach((option) => this.appendSelectionText(option.textContent, "gf-select-chip"));
          if (selected.length > limit) this.appendSelectionText("+" + (selected.length - limit), "gf-select-count");
        }
        this.toggle.title = selected.map((option) => option.textContent).join(", ");
        this.toggle.setAttribute("aria-label", selected.length
          ? selected.map((option) => option.textContent).join(", ")
          : this.settings.placeholder);
        return;
      }

      const selected = this.select.options[this.select.selectedIndex];
      const isPlaceholder = !selected || selected.value === "";
      this.appendSelectionText(selected ? selected.textContent : this.settings.placeholder, isPlaceholder ? "gf-select-placeholder" : "gf-select-value");
      this.toggle.title = selected ? selected.textContent : "";
      this.toggle.setAttribute("aria-label", selected ? selected.textContent : this.settings.placeholder);
    }

    appendSelectionText(text, className) {
      const item = document.createElement("span");
      item.className = className;
      item.textContent = text;
      this.selection.appendChild(item);
    }

    renderOptions() {
      this.ul.replaceChildren();
      const selectedCount = this.select.selectedOptions.length;
      let optionIndex = 0;

      if (this.settings.multiple && this.settings.maxSelections > 0 && !this.limit) {
        this.limit = document.createElement("small");
        this.limit.className = "gf-select-limit";
        this.menu.appendChild(this.limit);
      } else if ((!this.settings.multiple || this.settings.maxSelections === 0) && this.limit) {
        this.limit.remove();
        this.limit = null;
      }

      Array.from(this.select.children).forEach((child) => {
        if (child.tagName === "OPTGROUP") {
          if (child.hidden) {
            optionIndex += child.children.length;
            return;
          }
          const heading = document.createElement("li");
          heading.className = "gf-select-group";
          heading.textContent = child.label;
          heading.setAttribute("role", "presentation");
          this.ul.appendChild(heading);
          Array.from(child.children).forEach((option) => this.appendOption(option, optionIndex++, selectedCount, child));
          return;
        }

        if (child.tagName === "OPTION") this.appendOption(child, optionIndex++, selectedCount, null);
      });

      if (this.limit) this.limit.textContent = selectedCount + " de " + this.settings.maxSelections + " seleccionados";
      this.filterOptions(this.searchInput ? this.searchInput.value : "");
    }

    appendOption(option, index, selectedCount, group) {
      if (option.hidden) return;
      const limitReached = this.settings.multiple
        && this.settings.maxSelections > 0
        && selectedCount >= this.settings.maxSelections
        && !option.selected;
      const disabled = option.disabled || Boolean(group && group.disabled) || limitReached;
      const li = document.createElement("li");
      li.className = "gf-select-option";

      const item = document.createElement("button");
      item.type = "button";
      item.className = "gf-select-list-item";
      item.dataset.optionIndex = String(index);
      item.setAttribute("role", "option");
      item.setAttribute("aria-selected", option.selected ? "true" : "false");
      item.disabled = disabled;
      item.classList.toggle("selected", option.selected);

      const label = document.createElement("span");
      label.className = "gf-select-option-label";
      label.textContent = option.textContent;
      item.appendChild(label);
      li.appendChild(item);
      this.ul.appendChild(li);
    }

    filterOptions(term) {
      if (!this.ul) return;
      const query = String(term || "").trim().toLocaleLowerCase();
      let visibleOptions = 0;

      this.ul.querySelectorAll(".gf-select-option").forEach((li) => {
        const label = li.querySelector(".gf-select-option-label");
        const visible = !query || String(label?.textContent || "").toLocaleLowerCase().includes(query);
        li.hidden = !visible;
        if (visible) visibleOptions += 1;
      });

      this.ul.querySelectorAll(".gf-select-group").forEach((heading) => {
        let sibling = heading.nextElementSibling;
        let hasVisibleOption = false;
        while (sibling && !sibling.classList.contains("gf-select-group")) {
          if (sibling.classList.contains("gf-select-option") && !sibling.hidden) hasVisibleOption = true;
          sibling = sibling.nextElementSibling;
        }
        heading.hidden = !hasVisibleOption;
      });

      this.empty.hidden = visibleOptions > 0;
    }

    resolveMaximum(value) {
      const source = value === null || value === undefined ? this.select.dataset.maxSelections : value;
      const maximum = parseInt(source || "0", 10);
      return Number.isFinite(maximum) && maximum > 0 ? maximum : 0;
    }

    setInvalid(invalid) {
      this.invalid = Boolean(invalid);
      this.syncValidity();
    }

    syncValidity() {
      if (!this.wrapper) return;
      const form = this.select.form;
      const shouldShow = this.invalid
        || this.select.classList.contains("is-invalid")
        || Boolean(form && form.classList.contains("was-validated") && !this.select.validity.valid);
      this.wrapper.classList.toggle("is-invalid", shouldShow);
      this.toggle.setAttribute("aria-invalid", shouldShow ? "true" : "false");
    }

    focus() {
      this.toggle?.focus();
    }

    getValue() {
      return this.settings.multiple
        ? Array.from(this.select.selectedOptions).map((option) => option.value)
        : this.select.value;
    }

    destroy() {
      if (this.destroyed) return;
      this.destroyed = true;
      this.closeMenu();
      window.clearTimeout(this.refreshTimer);
      this.observer?.disconnect();
      this.toggle?.removeEventListener("click", this.boundToggleMenu);
      this.toggle?.removeEventListener("keydown", this.boundToggleKeydown);
      this.ul?.removeEventListener("click", this.boundOptionClick);
      this.menu?.removeEventListener("keydown", this.boundMenuKeydown);
      this.select.removeEventListener("change", this.boundNativeChange);
      this.select.removeEventListener("focus", this.boundNativeFocus);
      this.searchInput?.removeEventListener("input", this.boundFilter);

      if (this.wrapper?.parentNode) {
        this.wrapper.parentNode.insertBefore(this.select, this.wrapper);
        this.wrapper.remove();
      }
      this.select.style.display = this.originalDisplay;
      this.select.classList.remove("gf-select-native");
      GFSelect.instances.delete(this.select);
    }

    addCleanedClasses(element, classString) {
      if (!classString) return;
      String(classString).split(/\s+/).filter(Boolean).forEach((className) => element.classList.add(className));
    }
  }

  GFSelect.instances = new WeakMap();
  GFSelect.openInstance = null;
  GFSelect.stylesPromise = null;
  GFSelect.uid = 0;
  window.GFSelect = GFSelect;
})(window, document);
