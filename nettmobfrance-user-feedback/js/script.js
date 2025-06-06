// NettmobFrance User Feedback Plugin Scripts
(function($) {
    'use strict'; // Enable strict mode for better error handling and to prevent common mistakes.

    // Execute when the DOM is fully loaded.
    $(document).ready(function() {

        /**
         * Handles the click event on the feedback trigger button.
         * Toggles the visibility of the feedback form container with a fade effect.
         * Clears any previous messages and ensures the form is visible if previously hidden by success.
         */
        $('#nettmob-feedback-trigger-button').on('click', function() {
            $('#nettmob-feedback-form-container').fadeToggle();
            // Clear any previous success/error messages shown in the container.
            $('#nettmob-feedback-form-container .notice-message').remove();
            // Ensure the form itself is visible (it might be hidden after a successful submission).
            $('#nettmob-feedback-form').show();
        });

        /**
         * Handles the click event on the close button within the feedback form container.
         * Hides the feedback form container with a fade effect.
         * Clears any previous messages and ensures the form is visible for the next opening.
         */
        $('#nettmob-close-feedback-form').on('click', function() {
            $('#nettmob-feedback-form-container').fadeOut();
            // Clear any previous success/error messages.
            $('#nettmob-feedback-form-container .notice-message').remove();
            // Ensure the form is ready to be shown next time.
            $('#nettmob-feedback-form').show();
        });

        /**
         * Handles conditional display for KYC (Know Your Customer) details.
         * Shows or hides the KYC details textarea based on the radio button selection.
         */
        $('input[name="nettmob_kyc_difficulties"]').on('change', function() {
            // Check if the 'oui' (yes) option is selected.
            if ($(this).val() === 'oui' && $(this).is(':checked')) {
                $('#nettmob_kyc_details_container').slideDown(); // Show with a slide effect.
            } else {
                $('#nettmob_kyc_details_container').slideUp(); // Hide with a slide effect.
            }
        });

        /**
         * Handles conditional display for App download problem details.
         * Shows or hides the app download details textarea based on the radio button selection.
         */
        $('input[name="nettmob_app_download_problems"]').on('change', function() {
            // Check if the 'oui' (yes) option is selected.
            if ($(this).val() === 'oui' && $(this).is(':checked')) {
                $('#nettmob_app_download_details_container').slideDown(); // Show with a slide effect.
            } else {
                $('#nettmob_app_download_details_container').slideUp(); // Hide with a slide effect.
            }
        });

        /**
         * Handles the AJAX submission of the feedback form.
         */
        $('#nettmob-feedback-form').on('submit', function(e) {
            e.preventDefault(); // Prevent the default browser form submission.

            var form = $(this);
            var formData = form.serialize(); // Collect all form data.
            var submitButton = form.find('input[type="submit"]');
            var originalButtonText = submitButton.val(); // Store original button text.

            // Append WordPress AJAX action and nonce to the form data.
            // `nettmob_feedback_ajax.ajax_url` and `nettmob_feedback_ajax.nonce` are localized from PHP.
            formData += '&action=nettmob_submit_feedback';
            formData += '&nettmob_feedback_nonce_field=' + nettmob_feedback_ajax.nonce;

            // Clear previous messages and update button state.
            form.find('.notice-message').remove();
            submitButton.val('Envoi en cours...').prop('disabled', true); // Indicate processing.

            // Perform AJAX request.
            $.ajax({
                url: nettmob_feedback_ajax.ajax_url, // WordPress AJAX handler.
                type: 'POST',
                data: formData,
                dataType: 'json', // Expect JSON response from the server.
                success: function(response) {
                    if (response.success) {
                        // On successful submission:
                        form.hide(); // Hide the form.
                        // Display success message (provided by server) before the form.
                        form.before('<div class="notice-message notice-success" style="padding:10px; margin-bottom:10px; border:1px solid green; color:green;">' + response.data.message + '</div>');
                        form[0].reset(); // Reset all form fields, including textarea and radio buttons.
                        // Ensure conditional sections are hidden after reset.
                        $('#nettmob_kyc_details_container').hide();
                        $('#nettmob_app_download_details_container').hide();
                        // For star ratings, form[0].reset() unchecks the radio. CSS handles the visual update.
                    } else {
                        // On error reported by server (e.g., validation, nonce failure):
                        // Display error message (provided by server) at the top of the form.
                        form.prepend('<div class="notice-message notice-error" style="padding:10px; margin-bottom:10px; border:1px solid red; color:red;">' + response.data.message + '</div>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // On AJAX communication error (e.g., network issue, server error 500):
                    form.prepend('<div class="notice-message notice-error" style="padding:10px; margin-bottom:10px; border:1px solid red; color:red;">Erreur AJAX: ' + textStatus + ' - ' + errorThrown + '</div>');
                },
                complete: function() {
                    // After request completes (either success or error):
                    submitButton.val(originalButtonText).prop('disabled', false); // Restore button.
                }
            });
        });
    });

})(jQuery);
