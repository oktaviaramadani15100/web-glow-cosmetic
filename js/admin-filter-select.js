document.querySelectorAll("select[data-filter-select]").forEach(function(select) {
    const wrapper = document.createElement("div");
    wrapper.className = "admin-filter-select";

    const filterIcon = document.createElement("i");
    filterIcon.className = "fa-solid fa-filter admin-filter-select-icon";
    filterIcon.setAttribute("aria-hidden", "true");

    const toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "admin-filter-select-toggle";
    toggle.setAttribute("aria-haspopup", "listbox");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", select.getAttribute("aria-label") || "Filter status");

    const label = document.createElement("span");
    label.className = "admin-filter-select-label";

    const chevron = document.createElement("i");
    chevron.className = "fa-solid fa-chevron-down admin-filter-select-chevron";
    chevron.setAttribute("aria-hidden", "true");
    toggle.append(label, chevron);

    const menu = document.createElement("div");
    menu.id = select.id ? select.id + "Menu" : "adminFilterMenu";
    menu.className = "admin-filter-select-menu";
    menu.setAttribute("role", "listbox");
    menu.setAttribute("aria-label", select.getAttribute("aria-label") || "Pilih status");
    menu.hidden = true;
    toggle.setAttribute("aria-controls", menu.id);

    const options = Array.from(select.options).map(function(option) {
        const item = document.createElement("button");
        item.type = "button";
        item.className = "admin-filter-select-option";
        item.setAttribute("role", "option");
        item.dataset.value = option.value;
        item.disabled = option.disabled;

        const dot = document.createElement("span");
        dot.className = "admin-filter-select-dot";
        dot.setAttribute("aria-hidden", "true");

        const text = document.createElement("span");
        text.textContent = option.textContent.trim();

        const check = document.createElement("i");
        check.className = "fa-solid fa-check";
        check.setAttribute("aria-hidden", "true");

        item.append(dot, text, check);
        menu.appendChild(item);
        return item;
    });

    select.parentNode.insertBefore(wrapper, select);
    wrapper.append(filterIcon, toggle, menu, select);
    select.hidden = true;
    select.tabIndex = -1;
    select.setAttribute("aria-hidden", "true");

    function updateSelection() {
        const selectedOption = select.options[select.selectedIndex];
        label.textContent = selectedOption ? selectedOption.textContent.trim() : "";

        options.forEach(function(option) {
            const isSelected = option.dataset.value === select.value;
            option.classList.toggle("is-selected", isSelected);
            option.setAttribute("aria-selected", String(isSelected));
        });
    }

    function setOpen(isOpen) {
        menu.hidden = !isOpen;
        toggle.setAttribute("aria-expanded", String(isOpen));
        wrapper.classList.toggle("is-open", isOpen);
    }

    toggle.addEventListener("click", function() {
        setOpen(menu.hidden);
    });

    options.forEach(function(option, index) {
        option.addEventListener("click", function() {
            select.value = option.dataset.value;
            updateSelection();
            setOpen(false);
            toggle.focus();
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });

        option.addEventListener("keydown", function(event) {
            if (event.key === "Escape") {
                event.preventDefault();
                setOpen(false);
                toggle.focus();
            } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                const direction = event.key === "ArrowDown" ? 1 : -1;
                const nextIndex = (index + direction + options.length) % options.length;
                options[nextIndex].focus();
            } else if (event.key === "Home" || event.key === "End") {
                event.preventDefault();
                options[event.key === "Home" ? 0 : options.length - 1].focus();
            }
        });
    });

    toggle.addEventListener("keydown", function(event) {
        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            setOpen(true);
            const selectedOption = menu.querySelector(".is-selected");
            if (selectedOption) selectedOption.focus();
        }
    });

    select.addEventListener("change", updateSelection);
    document.addEventListener("click", function(event) {
        if (!wrapper.contains(event.target)) setOpen(false);
    });
    wrapper.addEventListener("focusout", function() {
        window.setTimeout(function() {
            if (!wrapper.contains(document.activeElement)) setOpen(false);
        }, 0);
    });

    updateSelection();
});
