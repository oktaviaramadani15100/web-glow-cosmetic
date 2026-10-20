async function refreshCustomerHeaderCounts() {
    try {
        const response = await fetch("../process/customer_header_counts.php", {
            headers: {
                Accept: "application/json"
            },
            cache: "no-store",
            credentials: "same-origin"
        });

        if (!response.ok) {
            throw new Error(`Header counts request failed: ${response.status}`);
        }

        const counts = await response.json();

        document.querySelectorAll("[data-header-count]").forEach((badge) => {
            const count = Number(counts[badge.dataset.headerCount]);

            if (!Number.isInteger(count) || count < 0) {
                throw new Error("Header counts response contains an invalid count.");
            }

            badge.textContent = String(count);
            badge.hidden = count === 0;
        });
    } catch (error) {
        console.error("Unable to refresh customer header counts.", error);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", refreshCustomerHeaderCounts);
} else {
    refreshCustomerHeaderCounts();
}
