export const normalizeSearch = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();

let sequence = 0;

export function registerSearchableSelects(Livewire) {
    if (customElements.get('searchable-select')) return;

    customElements.define('searchable-select', class extends HTMLElement {
        connectedCallback() {
            // Wait for Blade/Livewire to finish inserting the select and its options.
            queueMicrotask(() => this.setup());
        }

        disconnectedCallback() {
            this.observer?.disconnect();
            this.listeners?.abort();
            cancelAnimationFrame(this.frame);
            this.ready = false;
        }

        setup() {
            if (!this.isConnected || this.ready) return;
            this.native = this.querySelector('select');
            this.ui = this.querySelector('[data-select-ui]');
            if (!this.native || !this.ui) return;
            this.ready = true;
            this.opened = false;
            this.query = '';
            this.active = -1;
            this.uid = `searchable-select-${++sequence}`;
            this.listeners = new AbortController();
            const on = (node, event, callback, options = {}) => node.addEventListener(event, callback, { ...options, signal: this.listeners.signal });
            this.ui.innerHTML = `
                <button type="button" class="field-input select-trigger" aria-haspopup="listbox" aria-expanded="false">
                    <span class="select-label"></span><span class="select-chevron" aria-hidden="true">⌄</span>
                </button>
                <p class="field-error select-invalid" role="alert" hidden>Selecione uma opção para continuar.</p>
                <div class="select-panel" hidden>
                    <div class="select-search-wrap"><input type="search" class="field-input select-search" placeholder="Pesquisar opções…" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"></div>
                    <div class="select-options" role="listbox"></div>
                    <p class="select-empty" role="status" hidden>Nenhuma opção encontrada.</p>
                    <p class="select-hint">↑ ↓ para navegar · Enter para selecionar · Esc para fechar</p>
                </div>`;
            this.trigger = this.ui.querySelector('button');
            this.label = this.ui.querySelector('.select-label');
            this.panel = this.ui.querySelector('.select-panel');
            this.search = this.ui.querySelector('input');
            this.list = this.ui.querySelector('[role="listbox"]');
            this.empty = this.ui.querySelector('.select-empty');
            this.invalidMessage = this.ui.querySelector('.select-invalid');
            this.invalidMessage.id = `${this.uid}-error`;
            this.list.id = `${this.uid}-options`;
            this.search.setAttribute('aria-controls', this.list.id);
            this.trigger.setAttribute('aria-controls', this.list.id);
            this.native.tabIndex = -1;
            this.native.setAttribute('aria-hidden', 'true');
            this.setAttribute('data-enhanced', '');

            on(this.trigger, 'click', () => this.opened ? this.close() : this.open());
            on(this.trigger, 'keydown', (event) => {
                if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                    event.preventDefault();
                    this.open();
                }
            });
            on(this.search, 'input', () => {
                this.query = this.search.value;
                this.active = -1;
                this.renderOptions();
            });
            on(this.search, 'keydown', (event) => this.navigate(event));
            on(this.list, 'mousedown', (event) => event.preventDefault());
            on(this.list, 'click', (event) => {
                const option = event.target.closest('[data-index]');
                if (option) this.choose(Number(option.dataset.index));
            });
            on(this.native, 'change', () => this.refresh());
            on(this.native, 'focus', () => this.trigger.focus());
            on(this.native, 'invalid', (event) => {
                event.preventDefault();
                this.validationFailed = true;
                this.open();
            });
            on(document, 'pointerdown', (event) => {
                if (this.opened && !this.contains(event.target)) this.close(false);
            });
            on(this, 'focusout', () => queueMicrotask(() => {
                if (!this.contains(document.activeElement)) this.close(false);
            }));
            on(window, 'resize', () => this.position());
            on(window, 'scroll', (event) => {
                if (this.opened && !this.panel.contains(event.target)) this.position();
            }, { capture: true, passive: true });
            if (window.visualViewport) {
                on(window.visualViewport, 'resize', () => this.position());
                on(window.visualViewport, 'scroll', () => this.position());
            }
            if (this.native.form) on(this.native.form, 'reset', () => this.scheduleRefresh());
            this.observer = new MutationObserver(() => this.scheduleRefresh());
            this.observer.observe(this.native, { childList: true, subtree: true, attributes: true, characterData: true });
            this.refresh();
            this.scheduleRefresh();
        }

        scheduleRefresh() {
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => this.refresh());
        }

        refresh() {
            if (!this.isConnected || !this.ready) return;
            if (this.native.tabIndex !== -1) this.native.tabIndex = -1;
            if (this.native.getAttribute('aria-hidden') !== 'true') this.native.setAttribute('aria-hidden', 'true');
            this.options = Array.from(this.native.options).map((option, index) => ({
                index, value: option.value, label: option.textContent.trim(),
                disabled: option.disabled || option.parentElement?.disabled === true,
            }));
            const label = Array.from(this.native.labels ?? []).map((el) => el.textContent.trim()).join(' ') || 'Opção';
            const selected = this.options[this.native.selectedIndex];
            this.label.textContent = selected?.label || 'Selecione';
            this.trigger.setAttribute('aria-label', `${label}: ${this.label.textContent}`);
            this.trigger.disabled = this.native.disabled || this.native.matches(':disabled');
            this.search.setAttribute('aria-required', String(this.native.required));
            const invalid = this.validationFailed && !this.native.validity.valid;
            this.invalidMessage.hidden = !invalid;
            this.trigger.setAttribute('aria-invalid', invalid ? 'true' : this.native.getAttribute('aria-invalid') || 'false');
            const description = [this.native.getAttribute('aria-describedby'), invalid ? this.invalidMessage.id : ''].filter(Boolean).join(' ');
            if (description) this.trigger.setAttribute('aria-describedby', description);
            else this.trigger.removeAttribute('aria-describedby');
            this.search.setAttribute('aria-label', `Pesquisar em ${label}`);
            this.list.setAttribute('aria-label', label);
            if (this.trigger.disabled) this.close(false);
            if (this.opened) this.renderOptions();
        }

        open() {
            this.refresh();
            if (this.trigger.disabled) return;
            document.querySelectorAll('searchable-select').forEach((select) => {
                if (select !== this) select.close?.(false);
            });
            this.opened = true;
            this.query = '';
            this.search.value = '';
            this.active = this.native.selectedIndex;
            this.panel.hidden = false;
            this.trigger.setAttribute('aria-expanded', 'true');
            this.search.setAttribute('aria-expanded', 'true');
            this.renderOptions();
            this.position();
            this.search.focus({ preventScroll: true });
        }

        close(focus = true) {
            if (!this.ready) return;
            const wasOpen = this.opened;
            this.opened = false;
            this.panel.hidden = true;
            this.trigger.setAttribute('aria-expanded', 'false');
            this.search.setAttribute('aria-expanded', 'false');
            this.search.removeAttribute('aria-activedescendant');
            if (wasOpen && focus) this.trigger.focus({ preventScroll: true });
        }

        renderOptions() {
            const term = normalizeSearch(this.query);
            this.filtered = this.options.filter((option) => normalizeSearch(option.label).includes(term));
            if (!this.filtered.some((option) => option.index === this.active && !option.disabled)) {
                this.active = this.filtered.find((option) => !option.disabled)?.index ?? -1;
            }
            this.list.replaceChildren(...this.filtered.map((option) => {
                const row = document.createElement('div');
                row.id = `${this.uid}-${option.index}`;
                row.dataset.index = option.index;
                row.className = 'select-option';
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', String(this.native.selectedIndex === option.index));
                row.setAttribute('aria-disabled', String(option.disabled));
                row.dataset.active = String(this.active === option.index);
                row.textContent = option.label;
                return row;
            }));
            this.empty.hidden = this.filtered.length !== 0;
            this.updateActive();
        }

        updateActive() {
            this.list.querySelectorAll('[data-index]').forEach((row) => {
                row.dataset.active = String(Number(row.dataset.index) === this.active);
            });
            const active = this.list.querySelector(`[data-index="${this.active}"]`);
            if (active) {
                this.search.setAttribute('aria-activedescendant', active.id);
                active.scrollIntoView({ block: 'nearest' });
            } else this.search.removeAttribute('aria-activedescendant');
        }

        navigate(event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.close(); return; }
            if (event.key === 'Tab') { this.close(false); return; }
            if (event.key === 'Enter') { event.preventDefault(); this.choose(this.active); return; }
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            const available = this.filtered.filter((option) => !option.disabled);
            if (!available.length) return;
            const current = available.findIndex((option) => option.index === this.active);
            const next = (current + (event.key === 'ArrowDown' ? 1 : -1) + available.length) % available.length;
            this.active = available[next].index;
            this.updateActive();
        }

        choose(index) {
            const option = this.native.options[index];
            if (!option || option.disabled || option.parentElement?.disabled || this.native.disabled) return;
            this.native.selectedIndex = index;
            this.native.dispatchEvent(new Event('change', { bubbles: true }));
            this.refresh();
            this.close();
        }

        position() {
            if (!this.opened) return;
            const rect = this.trigger.getBoundingClientRect();
            const viewport = window.visualViewport;
            const top = viewport?.offsetTop ?? 0;
            const height = viewport?.height ?? innerHeight;
            const width = viewport?.width ?? innerWidth;
            const panelWidth = Math.min(Math.max(rect.width, 280), width - 16);
            const below = top + height - rect.bottom - 12;
            const above = rect.top - top - 12;
            const upwards = below < 220 && above > below;
            const panelHeight = Math.min(360, Math.max(140, upwards ? above : below));
            Object.assign(this.panel.style, {
                width: `${panelWidth}px`, maxHeight: `${panelHeight}px`,
                left: `${Math.max(8, Math.min(rect.left, width - panelWidth - 8))}px`,
                top: `${upwards ? Math.max(top + 8, rect.top - panelHeight - 6) : rect.bottom + 6}px`,
            });
        }
    });

    // Server-driven options and values may change without a native change event.
    Livewire.hook('morph.updated', ({ el }) => {
        const select = el.closest?.('searchable-select');
        select?.scheduleRefresh();
    });
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(() => {
            document.querySelectorAll('searchable-select').forEach((select) => select.scheduleRefresh?.());
        }));
    });
}
