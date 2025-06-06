// NettmobFrance User Feedback Plugin Scripts
(function($) {
    'usestrice'; // Enable strict mode

    // Cookie helper functions
    function setCookie(name, value, days) {
        var expires = "";
        if (days !== null && days !== undefined) {
            var date = new Date();
            if (days === 0) {
                expires = "";
            } else {
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    $(document).ready(function() {
        var ajax_vars = nettmob_feedback_ajax;
        var $formContainer = $('#nettmob-feedback-form-container');
        var $formInner = $('#nettmob-feedback-form-inner');
        var $form = $('#nettmob-feedback-form');
        var $triggerButton = $('#nettmob-feedback-trigger-button');

        if (ajax_vars.debug_mode) {
            console.log('Nettmob Feedback Vars:', ajax_vars);
            console.log('Cookie (' + ajax_vars.cookie_name + '):', getCookie(ajax_vars.cookie_name));
            console.log('Cookie (' + ajax_vars.popup_close_cookie_name + '):', getCookie(ajax_vars.popup_close_cookie_name));
        }

        // 1. Primary Cookie Check (Main submission cookie)
        if (getCookie(ajax_vars.cookie_name)) {
            if (ajax_vars.debug_mode) console.log('Main submission cookie found. Hiding trigger button. No further popup/button logic.');
            $triggerButton.hide();
            return; // Stop further execution if feedback has been submitted.
        }

        // At this point, main submission cookie is NOT set.
        // Proceed with popup or button visibility logic.
        var popup_enabled_val = ajax_vars.popup_enabled === '1' || ajax_vars.popup_enabled === 1 || ajax_vars.popup_enabled === true;
        var show_auto_popup = false; // Flag to determine if auto-popup will be shown

        if (popup_enabled_val) {
            if (ajax_vars.debug_mode) console.log('Popup feature is enabled via settings.');

            var cookie_lifetime_val = parseInt(ajax_vars.cookie_lifetime); // This is for main cookie, but used to gate temp cookie
            var temp_cookie_is_set = getCookie(ajax_vars.popup_close_cookie_name);

            if (cookie_lifetime_val > 0 && temp_cookie_is_set) {
                 if (ajax_vars.debug_mode) console.log('Popup "closed_temp" cookie found. Auto-popup suppressed for this session/day.');
            } else {
                // Determine if popup should show on this page (if no temp cookie or main cookie lifetime is 0)
                var show_popup_on_this_page = false;
                var current_page_id_str = String(ajax_vars.current_page_id);

                if (ajax_vars.popup_pages && ajax_vars.popup_pages.includes('all')) {
                    show_popup_on_this_page = true;
                } else if (ajax_vars.popup_pages && current_page_id_str !== '0') { // Specific page ID match
                    if (ajax_vars.popup_pages.includes(current_page_id_str)) {
                        show_popup_on_this_page = true;
                    }
                } else if (ajax_vars.popup_pages && ajax_vars.is_front_page && current_page_id_str === '0' && ajax_vars.popup_pages.includes(get_option('page_on_front', '0'))) {
                    // This case is tricky: if front page is latest posts, current_page_id_str might be '0'.
                    // Better to rely on a specific 'front_page' value in popup_pages if targeting latest posts front page is needed.
                    // For now, this mostly covers static front pages if their ID is selected.
                    // The existing PHP current_page_id logic tries to get page_on_front if is_front_page is true.
                     if (ajax_vars.popup_pages.includes(current_page_id_str) && current_page_id_str !== '0'){ // check if static front page id is in list
                        show_popup_on_this_page = true;
                     }
                }


                if (ajax_vars.debug_mode) {
                    console.log('Current Page ID for popup check:', current_page_id_str, 'Is Front Page:', ajax_vars.is_front_page, 'Selected pages for popup:', ajax_vars.popup_pages, 'Should show on this page:', show_popup_on_this_page);
                }

                if (show_popup_on_this_page) {
                    if (ajax_vars.debug_mode) console.log('Conditions met: Showing auto-popup.');
                    $formContainer.addClass('nuf-modal-mode').addClass('nuf-is-visible');
                    $triggerButton.hide();
                    show_auto_popup = true; // Set flag
                }
            }
        }

        // 4. Button Visibility if No Cookie and No Auto-Popup
        if (!show_auto_popup) { // This implies main submission cookie is also not set (due to early return)
            if (ajax_vars.debug_mode) console.log('Auto-popup conditions not met or disabled. Ensuring trigger button is visible.');
            $triggerButton.show(); // Make sure button is visible if no auto-popup and no main cookie
        }

        // Event Handlers
        $triggerButton.on('click', function(e) {
            e.preventDefault();
            // Re-check main cookie in case it was set dynamically by another tab (edge case)
            if (getCookie(ajax_vars.cookie_name)) {
                 if (ajax_vars.debug_mode) console.log('Main submission cookie found on button click. Hiding button.');
                 $triggerButton.hide();
                return;
            }
            $formContainer.removeClass('nuf-modal-mode nuf-is-visible'); // Ensure it's not modal
            // Reset to original non-modal position/style if needed
            $formContainer.css({ 'bottom': '70px', 'left': '20px', 'width': '320px', 'height': 'auto', 'top': 'auto', 'justify-content': 'flex-start'});
            $formInner.css({'max-height': '70vh'}); // From original non-modal style

            $formContainer.fadeToggle();
            $formContainer.find('.notice-message').remove();
            $form.show(); // Ensure form is visible
        });

        $('#nettmob-close-feedback-form').on('click', function() {
            var was_modal = $formContainer.hasClass('nuf-modal-mode');

            if (was_modal) {
                var cookie_lifetime_val = parseInt(ajax_vars.cookie_lifetime);
                if (cookie_lifetime_val > 0) {
                    setCookie(ajax_vars.popup_close_cookie_name, '1', 1);
                     if (ajax_vars.debug_mode) console.log('Modal popup closed, "closed_temp" cookie set for 1 day.');
                } else { // cookie_lifetime_val is 0, meaning "show always" for main cookie, so temp cookie also respects this intent
                    if (ajax_vars.debug_mode) console.log('Modal popup closed, main cookie_lifetime is 0, no "closed_temp" cookie set.');
                }
            }

            $formContainer.removeClass('nuf-is-visible').removeClass('nuf-modal-mode').fadeOut();

            if (was_modal && !getCookie(ajax_vars.cookie_name)) { // If it was a modal and main cookie not set
                $triggerButton.show(); // Re-show trigger button
            }

            $formContainer.find('.notice-message').remove();
            $form.show(); // Ensure form is visible for next time (if opened by button)
        });

        $('input[name="nettmob_kyc_difficulties"]').on('change', function() {
            if ($(this).val() === 'oui' && $(this).is(':checked')) {
                $('#nettmob_kyc_details_container').slideDown();
            } else {
                $('#nettmob_kyc_details_container').slideUp();
            }
        });

        $('input[name="nettmob_app_download_problems"]').on('change', function() {
            if ($(this).val() === 'oui' && $(this).is(':checked')) {
                $('#nettmob_app_download_details_container').slideDown();
            } else {
                $('#nettmob_app_download_details_container').slideUp();
            }
        });

        $form.on('submit', function(e) {
            e.preventDefault();
            var currentForm = $(this);
            var formData = currentForm.serialize();
            var submitButton = currentForm.find('input[type="submit"]');
            var originalButtonText = submitButton.val();

            formData += '&action=nettmob_submit_feedback';
            formData += '&nettmob_feedback_nonce_field=' + ajax_vars.nonce;

            currentForm.parent().find('.notice-message').remove();
            submitButton.val('Envoi en cours...').prop('disabled', true);

            $.ajax({
                url: ajax_vars.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    var messageContainer = currentForm.parent();
                    if (response.success) {
                        currentForm.hide();
                        messageContainer.find('.notice-message').remove();
                        currentForm.before('<div class="notice-message notice-success">' + response.data.message + '</div>');
                        currentForm[0].reset();
                        $('#nettmob_kyc_details_container').hide();
                        $('#nettmob_app_download_details_container').hide();

                        var main_cookie_lifetime = parseInt(ajax_vars.cookie_lifetime);
                        if (main_cookie_lifetime > 0) {
                            setCookie(ajax_vars.cookie_name, '1', main_cookie_lifetime);
                            if (ajax_vars.debug_mode) console.log('Main submission cookie set for ' + main_cookie_lifetime + ' days.');
                        } else {
                            // If cookie_lifetime is 0, admin wants popup to show "always" (ignoring submission for suppression)
                            // So, we don't set the main cookie. The page reload will effectively allow it to show again
                            // if conditions are met (unless a temp_close_cookie was set by closing modal).
                            if (ajax_vars.debug_mode) console.log('Main cookie not set due to admin setting cookie_lifetime to 0.');
                        }

                        setTimeout(function() {
                            location.reload();
                        }, 2000);

                    } else {
                        messageContainer.find('.notice-message').remove();
                        currentForm.prepend('<div class="notice-message notice-error">' + response.data.message + '</div>');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var messageContainer = currentForm.parent();
                    messageContainer.find('.notice-message').remove();
                    currentForm.prepend('<div class="notice-message notice-error">Erreur AJAX: ' + textStatus + ' - ' + errorThrown + '</div>');
                },
                complete: function() {
                    submitButton.val(originalButtonText).prop('disabled', false);
                }
            });
        });
    });

})(jQuery);
