class GFSelect {
  constructor(selector, options = {}) {
    this.select = document.querySelector(selector);
    if (!this.select) return;

    this.settings = Object.assign({
      searchable: true,
      maxHeight: 250,
      wrapperClass: '',
      toggleClass: '',
      menuClass: '',
      searchWrapperClass: '',
      searchInputClass: '',
      searchLabel: 'Buscar...',
      autoFocusSearch: true,
      prefixHtml: '',
      onChange: null,
      onReady: null
    }, options);

    const scriptEl = [...document.querySelectorAll('script')].find(s =>
      s.getAttribute('src') && s.getAttribute('src').includes('gf-select.js')
    );
    const jsPath = scriptEl.getAttribute('src');
    const basePath = jsPath.substring(0, jsPath.lastIndexOf('/') + 1);

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = `${basePath}gf-select.css`;
    link.onload = () => {
      this.render();
      this.bindEvents();
    };
    document.head.insertBefore(link, document.head.firstChild);
  }

  render() {
    this.select.style.display = 'none';

    this.wrapper = document.createElement('div');
    this.wrapper.className = 'gf-select-wrapper';
    if (this.select.id) this.wrapper.id = 'gf-' + this.select.id;
    if (this.settings.wrapperClass) this.addCleanedClasses(this.wrapper, this.settings.wrapperClass);
    this.select.parentNode.insertBefore(this.wrapper, this.select);
    this.wrapper.appendChild(this.select);

    this.toggle = document.createElement('div');
    this.toggle.className = 'gf-select-toggle';
    if (this.settings.toggleClass) this.addCleanedClasses(this.toggle, this.settings.toggleClass)
    this.wrapper.appendChild(this.toggle);

    this.selectedText = document.createElement('span');
    if (this.settings.prefixHtml) {
      this.prefixSpan = document.createElement('span');
      this.prefixSpan.innerHTML = this.settings.prefixHtml;
      this.selectedText.appendChild(this.prefixSpan);
    }
    this.dynamicText = document.createElement('span');
    this.dynamicText.textContent = this.select.options[this.select.selectedIndex].text;
    this.selectedText.appendChild(this.dynamicText);
    this.toggle.appendChild(this.selectedText);

    this.menu = document.createElement('div');
    this.menu.className = 'gf-select-menu';
    if (this.settings.menuClass) this.addCleanedClasses(this.menu, this.settings.menuClass)
    this.wrapper.appendChild(this.menu);

    if (this.settings.searchable) {
      const searchWrapper = document.createElement('div');
      searchWrapper.className = 'gf-select-search-wrapper';
      if (this.settings.searchWrapperClass) this.addCleanedClasses(searchWrapper, this.settings.searchWrapperClass);
      this.searchInput = document.createElement('input');
      this.searchInput.type = 'text';
      this.searchInput.className = 'gf-select-search-input';
      if (this.settings.searchInputClass) this.addCleanedClasses(this.searchInput, this.settings.searchInputClass)
      this.searchInput.placeholder = this.settings.searchLabel;
      searchWrapper.appendChild(this.searchInput);
      this.menu.appendChild(searchWrapper);
    }

    const list = document.createElement('div');
    list.className = 'gf-select-list';
    list.style.maxHeight = this.settings.maxHeight + 'px';
    list.style.overflowY = 'auto';
    this.menu.appendChild(list);
    this.list = list;

    const ul = document.createElement('ul');
    list.appendChild(ul);
    this.ul = ul;

    Array.from(this.select.options).forEach(option => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'gf-select-list-item';
      a.dataset.value = option.value;
      a.textContent = option.text;
      if (option.id) a.id = option.id;
      if (option.selected) a.classList.add('selected');
      li.appendChild(a);
      ul.appendChild(li);
    });

    if (typeof this.settings.onReady === 'function') this.settings.onReady(this);
  }

  bindEvents() {
    this.boundToggleMenu  = this.toggleMenu.bind(this);
    this.boundOptionClick = this.handleOptionClick.bind(this);
    this.boundFilter      = this.handleFilter.bind(this);
    // El boundDocumentClick se crea dinámicamente al abrir
    this.toggle.addEventListener('click', this.boundToggleMenu);
    this.ul.addEventListener('click', this.boundOptionClick);
    if (this.settings.searchable && this.searchInput) {
      this.searchInput.addEventListener('input', this.boundFilter);
    }
  }

  handleDocumentClick(e) {
    if (!this.wrapper.contains(e.target)) {
      this.closeMenu();
    }
  }

  handleOptionClick(e) {
    e.preventDefault();
    if (e.target.tagName.toLowerCase() === 'a') {
      this.selectOption(e.target);
    }
  }

  handleFilter(e) {
    this.filterOptions(e.target.value.toLowerCase());
  }

  toggleMenu() {
  const isOpen = this.menu.style.display === 'block';

  if (isOpen) {
    this.closeMenu();
  } else {
    document.querySelectorAll('.gf-select-menu').forEach(menu => {
      if (menu !== this.menu && menu.style.display === 'block') {
        const instToggle = menu.parentElement.querySelector('.gf-select-toggle');
        menu.style.display = 'none';
        instToggle?.classList.remove('active');
      }
    });

    this.openMenu();
  }
}

openMenu() {
  // Mostrar temporalmente para medir correctamente
  this.menu.style.visibility = 'hidden';
  this.menu.style.display = 'block';
  this.menu.style.position = 'fixed';
  this.menu.style.width = this.wrapper.offsetWidth + 'px';
  this.menu.style.left = this.wrapper.getBoundingClientRect().left + 'px';

  // Obtener alturas y espacios
  const rect = this.wrapper.getBoundingClientRect();
  const menuHeight = this.menu.offsetHeight;
  const viewportHeight = window.innerHeight;
  const spaceBelow = viewportHeight - rect.bottom;
  const spaceAbove = rect.top;

  // Decidir posición
  let topPosition;
  if (spaceBelow < menuHeight && spaceAbove > menuHeight) {
    topPosition = rect.top - menuHeight;
    this.menu.classList.add('gf-select-menu-up');
  } else {
    topPosition = rect.bottom;
    this.menu.classList.remove('gf-select-menu-up');
  }

  // Aplicar posición final
  this.menu.style.top = topPosition + 'px';
  this.menu.style.visibility = 'visible';

  this.toggle.classList.add('active');

  // Registrar handler de cierre
  this.boundDocumentClick = this.handleDocumentClick.bind(this);
  document.addEventListener('click', this.boundDocumentClick);

  // Filtro y foco
  if (this.settings.searchable && this.searchInput) {
    this.searchInput.value = '';
    this.filterOptions('');
    if (this.settings.autoFocusSearch) this.searchInput.focus();
  }

  // Scroll automático al elemento seleccionado
  const selected = this.ul.querySelector('a.selected');
  if (selected && this.list) {
    const listRect = this.list.getBoundingClientRect();
    const selRect  = selected.getBoundingClientRect();
    this.list.scrollTop += (selRect.top - listRect.top);
  }

  // Bloqueo de scroll general si hay algún menú abierto
  if (document.querySelectorAll('.gf-select-menu[style*="display: block"]').length > 0) {
    document.body.style.overflow = 'hidden';
  }
}


closeMenu() {
  this.menu.style.display = 'none';
  this.toggle.classList.remove('active');
  document.removeEventListener('click', this.boundDocumentClick);

  if (document.querySelectorAll('.gf-select-menu[style*="display: block"]').length === 0) {
    document.body.style.overflow = '';
  }
}


  selectOption(anchorEl) {
    this.dynamicText.textContent = anchorEl.textContent;
    this.select.value = anchorEl.dataset.value;

    this.ul.querySelectorAll('a').forEach(a => a.classList.remove('selected'));
    anchorEl.classList.add('selected');

    if (typeof this.settings.onChange === 'function') {
      this.settings.onChange(anchorEl.dataset.value, anchorEl.textContent);
    }

    this.closeMenu();
  }

  filterOptions(term) {
    this.ul.innerHTML = '';
    this.select.querySelectorAll('option').forEach(option => {
      if (option.text.toLowerCase().includes(term)) {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = '#';
        a.className = 'gf-select-list-item';
        a.dataset.value = option.value;
        a.textContent   = option.text;
        if (option.id)  a.id = option.id;
        if (option.value === this.select.value) a.classList.add('selected');
        li.appendChild(a);
        this.ul.appendChild(li);
      }
    });
  }

  destroy() {
    this.toggle.removeEventListener('click', this.boundToggleMenu);
    this.ul.removeEventListener('click', this.boundOptionClick);
    document.removeEventListener('click', this.boundDocumentClick);
    if (this.settings.searchable && this.searchInput) {
      this.searchInput.removeEventListener('input', this.boundFilter);
    }

    this.select.style.display = '';
    this.wrapper.parentNode.insertBefore(this.select, this.wrapper);
    this.wrapper.remove();
  }

  addCleanedClasses(el, classString) {
  if (!classString) return;
  classString.split(' ').map(c => c.trim()).filter(c => c).forEach(c => el.classList.add(c));
  }

}
