document.querySelectorAll(".navbar").forEach((header, index) => {
    const desktopMenu = header.querySelector(".desktop-menu");
    const desktopActions = header.querySelector(".desktop-actions");

    if (!desktopMenu && !desktopActions) {
        return;
    }

    document.body.classList.add("customer-site");

    const toggle = document.createElement("button");
    const panel = document.createElement("div");
    const panelId = `customer-navigation-${index + 1}`;

    toggle.type = "button";
    toggle.className = "customer-navigation-toggle";
    toggle.setAttribute("aria-label", "Buka menu navigasi");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-controls", panelId);
    toggle.innerHTML = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    `;

    panel.id = panelId;
    panel.className = "customer-navigation-panel";
    const mobileViewport = window.matchMedia("(max-width: 600px)");
    panel.hidden = mobileViewport.matches;

    header.append(toggle, panel);

    if (desktopMenu) {
        panel.append(desktopMenu);
    }
    if (desktopActions) {
        panel.append(desktopActions);
    }

    const syncNavigation = () => {
        const isMobile = mobileViewport.matches;
        panel.hidden = isMobile;
        header.classList.remove("menu-open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Buka menu navigasi");
        toggle.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        `;
    };

    const closeMenu = () => {
        panel.hidden = mobileViewport.matches;
        header.classList.remove("menu-open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Buka menu navigasi");
        toggle.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        `;
    };

    syncNavigation();
    mobileViewport.addEventListener("change", syncNavigation);

    toggle.addEventListener("click", () => {
        if (!mobileViewport.matches) {
            return;
        }

        const isOpen = !panel.hidden;

        if (isOpen) {
            closeMenu();
            return;
        }

        panel.hidden = false;
        header.classList.add("menu-open");
        toggle.setAttribute("aria-expanded", "true");
        toggle.setAttribute("aria-label", "Tutup menu navigasi");
        toggle.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m6 6 12 12M18 6 6 18"></path>
            </svg>
        `;
    });

    panel.addEventListener("click", (event) => {
        if (mobileViewport.matches && event.target.closest("a")) {
            closeMenu();
        }
    });

    document.addEventListener("click", (event) => {
        if (mobileViewport.matches && !header.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (mobileViewport.matches && event.key === "Escape" && !panel.hidden) {
            closeMenu();
            toggle.focus();
        }
    });
});
