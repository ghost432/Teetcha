// NettmobFrance User Feedback Plugin Scripts
(function($) {
    'use strict'; // Enable strict mode

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

        // Determine overall submission status
        window.nettmobFeedbackAlreadyGiven = false;
        if (ajax_vars.user_has_submitted) { // Priority for logged-in user meta
            window.nettmobFeedbackAlreadyGiven = true;
            if (ajax_vars.debug_mode) console.log('NUF: User meta indicates feedback already submitted.');
        } else if (getCookie(ajax_vars.cookie_name)) { // Fallback to cookie for guests or if meta somehow not set
            window.nettmobFeedbackAlreadyGiven = true;
            if (ajax_vars.debug_mode) console.log('NUF: Main submission cookie found.');
        }

        if (ajax_vars.debug_mode) {
            console.log('NUF Vars:', ajax_vars);
            console.log('NUF: Feedback Already Given Flag:', window.nettmobFeedbackAlreadyGiven);
        }

        // Listener for shortcode-generated triggers (should always be bound)
        $(document).on('click', '.nuf-popup-trigger-shortcode', function(e) {
            e.preventDefault();
            if (ajax_vars.debug_mode) console.log('NUF: Shortcode trigger clicked.');

            if (window.nettmobFeedbackAlreadyGiven) {
                alert(ajax_vars.i18n_already_submitted || 'You have already submitted feedback.');
                return;
            }
            $formContainer.addClass('nuf-modal-mode').addClass('nuf-is-visible');
            $triggerButton.hide(); // Hide main floating button if shortcode opens modal
        });


        if (window.nettmobFeedbackAlreadyGiven) {
            if (ajax_vars.debug_mode) console.log('NUF: Feedback already given. Hiding trigger button. No auto-popup.');
            $triggerButton.hide();
            // No further auto-popup or trigger button logic needed.
        } else {
            // --- Auto-Popup Logic ---
            var popup_enabled_val = ajax_vars.popup_enabled === '1' || ajax_vars.popup_enabled === 1 || ajax_vars.popup_enabled === true;
            var show_auto_popup = false;

            if (popup_enabled_val) {
                if (ajax_vars.debug_mode) console.log('NUF: Popup feature is enabled via settings.');

                var cookie_lifetime_val = parseInt(ajax_vars.cookie_lifetime); // This is for main cookie, used to gate temp cookie
                var temp_cookie_is_set = getCookie(ajax_vars.popup_close_cookie_name);

                if (cookie_lifetime_val > 0 && temp_cookie_is_set) {
                     if (ajax_vars.debug_mode) console.log('NUF: Popup "closed_temp" cookie found. Auto-popup suppressed.');
                } else {
                    var show_popup_on_this_page = false;
                    var popup_setting = ajax_vars.popup_pages.toString();
                    var current_page_id_str = ajax_vars.current_page_id.toString();
                    var page_on_front_str = ajax_vars.page_on_front.toString();

                    if (popup_setting === 'all') {
                        show_popup_on_this_page = true;
                    } else if (popup_setting !== 'none' && parseInt(popup_setting) > 0) {
                        if (ajax_vars.is_front_page && popup_setting === page_on_front_str && current_page_id_str === page_on_front_str) {
                            show_popup_on_this_page = true;
                        } else if (popup_setting === current_page_id_str && current_page_id_str !== '0') {
                            show_popup_on_this_page = true;
                        }
                    }

                    if (ajax_vars.debug_mode) {
                        console.log('NUF: Popup Setting:', popup_setting, 'Current Page ID:', current_page_id_str, 'Is Front Page:', ajax_vars.is_front_page, 'Page on Front ID:', page_on_front_str, 'Should show on this page:', show_popup_on_this_page);
                    }

                    if (show_popup_on_this_page) {
                        if (ajax_vars.debug_mode) console.log('NUF: Conditions met: Showing auto-popup.');
                        $formContainer.addClass('nuf-modal-mode').addClass('nuf-is-visible');
                        $triggerButton.hide();
                        show_auto_popup = true;
                    }
                }
            }

            // --- Trigger Button Visibility (if not already given feedback & no auto-popup) ---
            if (!show_auto_popup) {
                if (ajax_vars.debug_mode) console.log('NUF: Auto-popup did not show. Ensuring trigger button is visible.');
                $triggerButton.show();
            }
        }

        // --- Event Handlers (common to both button and popup) ---
        $triggerButton.on('click', function(e) {
            e.preventDefault();
            // The window.nettmobFeedbackAlreadyGiven check at the start should prevent this if already submitted.
            // This handler is for when the button is visible (i.e., feedback not given, auto-popup didn't show).
            $formContainer.removeClass('nuf-modal-mode nuf-is-visible');
            $formContainer.css({ 'bottom': '70px', 'left': '20px', 'width': '320px', 'height': 'auto', 'top': 'auto', 'justify-content': 'flex-start'});
            $formInner.css({'max-height': '70vh'});
            $formContainer.fadeToggle();
            $formContainer.find('.notice-message').remove();
            $form.show();
        });

        $('#nettmob-close-feedback-form').on('click', function() {
            var was_modal = $formContainer.hasClass('nuf-modal-mode');

            if (was_modal) {
                var cookie_lifetime_val = parseInt(ajax_vars.cookie_lifetime);
                if (cookie_lifetime_val > 0) {
                    setCookie(ajax_vars.popup_close_cookie_name, '1', 1);
                     if (ajax_vars.debug_mode) console.log('NUF: Modal popup closed, "closed_temp" cookie set for 1 day.');
                } else {
                    if (ajax_vars.debug_mode) console.log('NUF: Modal popup closed, main cookie_lifetime is 0, no "closed_temp" cookie set.');
                }
            }

            $formContainer.removeClass('nuf-is-visible nuf-modal-mode').fadeOut();

            if (was_modal && !window.nettmobFeedbackAlreadyGiven) {
                $triggerButton.show();
            } else if (!was_modal && window.nettmobFeedbackAlreadyGiven){ // If it was button mode but feedback now given (unlikely path here)
                 $triggerButton.hide();
            }

            $formContainer.find('.notice-message').remove();
            $form.show();
        });

        // --- Conditional Field Logic ---
        var $missionNotificationsRadios = $('input[name="nettmob_mission_notifications"]');
        var $notificationSuggestionsContainer = $('#nettmob_notification_suggestions_container');

        function toggleNotificationSuggestions() {
            if ($missionNotificationsRadios.is(':checked')) {
                $notificationSuggestionsContainer.slideDown();
            } else {
                $notificationSuggestionsContainer.slideUp();
            }
        }
        toggleNotificationSuggestions();
        $missionNotificationsRadios.on('change', toggleNotificationSuggestions);

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

        $form.on('reset', function() {
            setTimeout(function() {
                toggleNotificationSuggestions();
                if (!$('input[name="nettmob_kyc_difficulties"]:checked').length || $('input[name="nettmob_kyc_difficulties"][value="non"]').is(':checked')) {
                    $('#nettmob_kyc_details_container').slideUp();
                }
                if (!$('input[name="nettmob_app_download_problems"]:checked').length || $('input[name="nettmob_app_download_problems"][value="non"]').is(':checked')) {
                    $('#nettmob_app_download_details_container').slideUp();
                }
            }, 50);
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

                        var main_cookie_lifetime = parseInt(ajax_vars.cookie_lifetime);
                        if (main_cookie_lifetime > 0) {
                            setCookie(ajax_vars.cookie_name, '1', main_cookie_lifetime);
                            if (ajax_vars.debug_mode) console.log('NUF: Main submission cookie set for ' + main_cookie_lifetime + ' days.');
                        } else {
                            if (ajax_vars.debug_mode) console.log('NUF: Main cookie not set due to admin setting cookie_lifetime to 0.');
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
