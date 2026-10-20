/* =================================
   FAQ ACCORDION
================================= */

document.addEventListener("DOMContentLoaded", function () {

    const faqQuestions =
        document.querySelectorAll(".faq-question");


    faqQuestions.forEach(function (question) {

        question.addEventListener("click", function () {

            const currentItem =
                this.closest(".faq-item");


            /*
             * Tutup FAQ lainnya
             */

            document
                .querySelectorAll(".faq-item")
                .forEach(function (item) {

                    if (item !== currentItem) {

                        item.classList.remove("active");

                    }

                });


            /*
             * Buka / tutup FAQ yang diklik
             */

            currentItem.classList.toggle("active");

        });

    });

});

function updateWishlistCount() {

    const wishlist =
        JSON.parse(
            localStorage.getItem("wishlist")
        ) || [];

    const count =
        document.getElementById("wishlist-count");

    if (!count) {
        return;
    }

    count.textContent = wishlist.length;

    count.style.display =
        wishlist.length > 0
            ? "flex"
            : "none";
}