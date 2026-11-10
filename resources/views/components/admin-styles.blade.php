<style>
    /* Numbers and dates read left-to-right even inside the Arabic (RTL) layout. */
    .fi-fo-text-input input[type="number"],
    .fi-fo-date-time-picker input,
    .fi-ta-text-input input[type="number"] {
        direction: ltr;
        text-align: start;
    }

    /* Crane number plate, rendered from the official SVG template. */
    .crane-plate-img {
        display: block;
        width: 11rem;
        height: auto;
        direction: ltr;
    }

    /* Comfortable tap targets on phones. */
    @media (max-width: 767px) {
        .fi-btn {
            min-height: 2.75rem;
        }

        .fi-ta-record-actions .fi-icon-btn {
            width: 2.75rem;
            height: 2.75rem;
        }

        .fi-header-heading {
            font-size: 1.25rem;
            line-height: 1.75rem;
        }

        .fi-section {
            border-radius: 0.75rem;
        }
    }
</style>
