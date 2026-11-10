<style>
    /* Numbers and dates read left-to-right even inside the Arabic (RTL) layout. */
    .fi-fo-text-input input[type="number"],
    .fi-fo-date-time-picker input,
    .fi-ta-text-input input[type="number"] {
        direction: ltr;
        text-align: start;
    }

    /* Crane number shown as a bordered tag, like a plate. */
    .crane-code {
        display: inline-block;
        border: 2px solid currentColor;
        border-radius: 0.375rem;
        padding: 0.125rem 0.625rem;
        letter-spacing: 0.02em;
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
