// NettmobFrance User Feedback Plugin Scripts
(function($) {
    'use strict'; // Enable strict mode

    // Cookie helper functions
    function setCookie(name, value, days) {
        var expires = "";
        if (days !== null && days !== undefined) {
            var date = new Date();
            if (days === 0) {
                expires = ""; // Session cookie
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
        var $form = $('#nettmob-feedback-form');
        var $triggerButton = $('#nettmob-feedback-trigger-button');

        // 1. Initialize Core State Flags
        window.nettmobFeedbackAlreadyGiven = false;
        if (ajax_vars.user_has_submitted === true || ajax_vars.user_has_submitted === '1' || ajax_vars.user_has_submitted === 1) {
            window.nettmobFeedbackAlreadyGiven = true;
        } else if (getCookie(ajax_vars.cookie_name)) {
            window.nettmobFeedbackAlreadyGiven = true;
        }

        var isFloatingButtonClickedSession = !!getCookie(ajax_vars.button_clicked_session_cookie_name);
        var isAutoPopupActive = false;

        if (ajax_vars.debug_mode) {
            console.log('NUF Initial State ---');
            console.log('NUF Vars:', ajax_vars);
            console.log('NUF: Feedback Already Given Flag:', window.nettmobFeedbackAlreadyGiven);
            console.log('NUF: Is Floating Button Clicked Session:', isFloatingButtonClickedSession);
        }

        // Shortcode trigger click listeners (should always be bound)
        $(document).on('click', '.nuf-popup-trigger-shortcode, .nuf-elementor-popup-trigger', function(e) {
            e.preventDefault();
            var triggerType = $(this).hasClass('nuf-elementor-popup-trigger') ? 'Elementor' : 'Generic';
            if (ajax_vars.debug_mode) console.log('NUF: ' + triggerType + ' popup trigger clicked.');

            if (window.nettmobFeedbackAlreadyGiven) {
                alert(ajax_vars.i18n_already_submitted);
                return;
            }
            // These shortcodes always open as a modal
            $formContainer.removeClass('nuf-overlay-mode').addClass('nuf-modal-mode').addClass('nuf-is-visible');
            $triggerButton.hide();
        });

        // 3. Primary Logic Branching for initial display state
        if (window.nettmobFeedbackAlreadyGiven) {
            if (ajax_vars.debug_mode) console.log('NUF: Feedback already given. Hiding floating button.');
            $triggerButton.hide();
        } else {
            // Handle auto-popup and floating button visibility
            var popup_enabled_val = ajax_vars.popup_enabled === '1' || ajax_vars.popup_enabled === 1 || ajax_vars.popup_enabled === true;

            if (popup_enabled_val) {
                if (ajax_vars.debug_mode) console.log('NUF: Auto-popup feature is enabled.');
                var main_cookie_lifetime_val = parseInt(ajax_vars.cookie_lifetime);
                var temp_popup_cookie = getCookie(ajax_vars.popup_close_cookie_name);

                if (!(main_cookie_lifetime_val > 0 && temp_popup_cookie)) {
                    var show_popup_on_this_page = false;
                    var popup_setting = ajax_vars.popup_pages.toString();
                    var current_page_id_str = ajax_vars.current_page_id.toString();
                    var page_on_front_str = ajax_vars.page_on_front.toString();

                    if (popup_setting === 'all') {
                        show_popup_on_this_page = true;
                    } else if (popup_setting !== 'none' && parseInt(popup_setting) > 0) {
                        if (ajax_vars.is_front_page && popup_setting === page_on_front_str && current_page_id_str === page_on_front_str) {
                            show_popup_on_this_page = true;
                        } else if (popup_setting === current_page_id_str && current_page_id_str !== '0' && current_page_id_str !== '') {
                            show_popup_on_this_page = true;
                        }
                    }

                    if (ajax_vars.debug_mode) {
                        console.log('NUF: Auto-Popup Check -> Setting:', popup_setting, 'CurrentPageID:', current_page_id_str, 'IsFront:', ajax_vars.is_front_page, 'PageOnFront:', page_on_front_str, 'ShowOnThisPage:', show_popup_on_this_page);
                    }

                    if (show_popup_on_this_page) {
                        if (ajax_vars.debug_mode) console.log('NUF: Conditions met for auto-popup. Showing modal.');
                        $formContainer.removeClass('nuf-overlay-mode').addClass('nuf-modal-mode').addClass('nuf-is-visible');
                        isAutoPopupActive = true;
                    }
                } else {
                     if (ajax_vars.debug_mode) console.log('NUF: Temp popup close cookie found. Auto-popup suppressed for this load.');
                }
            }

            // Floating Button Visibility
            if (isFloatingButtonClickedSession) {
                if (ajax_vars.debug_mode) console.log('NUF: Floating button clicked this session. Hiding button.');
                $triggerButton.hide();
            } else if (!isAutoPopupActive) {
                if (ajax_vars.debug_mode) console.log('NUF: No auto-popup, no session click. Showing floating button.');
                $triggerButton.show();
            } else { // isAutoPopupActive is true
                 if (ajax_vars.debug_mode) console.log('NUF: Auto-popup IS showing. Button also shown (as per spec E).');
                 $triggerButton.show();
            }
        }

        // --- Event Handlers ---
        $triggerButton.on('click', function(e) {
            e.preventDefault();
            if (ajax_vars.debug_mode) console.log('NUF: Floating button clicked, toggling chat window.');

            // If a modal (auto or shortcode) is already visible, ignore button click
            if ($formContainer.hasClass('nuf-modal-mode') && $formContainer.hasClass('nuf-is-visible')) {
                if (ajax_vars.debug_mode) console.log('NUF: Floating button clicked, but modal already visible. Ignoring.');
                return;
            }

            $formContainer.removeClass('nuf-modal-mode'); // Ensure it's not modal

            var isNowVisible = !$formContainer.is(':visible') || $formContainer.css('opacity') === '0';

            $formContainer.fadeToggle(function() {
                if (isNowVisible && !getCookie(ajax_vars.button_clicked_session_cookie_name)) {
                    setCookie(ajax_vars.button_clicked_session_cookie_name, '1', 0);
                    $triggerButton.hide();
                    if (ajax_vars.debug_mode) console.log('NUF: Chat window opened, session cookie set, button hidden.');
                }
            });

            $formContainer.find('.notice-message').remove();
            $form.show();
        });

        $('#nettmob-close-feedback-form').on('click', function() {
            var was_modal = $formContainer.hasClass('nuf-modal-mode');

            if (was_modal) {
                var main_cookie_lifetime_val = parseInt(ajax_vars.cookie_lifetime);
                if (main_cookie_lifetime_val > 0) {
                    setCookie(ajax_vars.popup_close_cookie_name, '1', 1);
                     if (ajax_vars.debug_mode) console.log('NUF: Modal (auto/shortcode) closed, "closed_temp" cookie set for 1 day.');
                } else {
                    if (ajax_vars.debug_mode) console.log('NUF: Modal (auto/shortcode) closed, main cookie_lifetime is 0, no "closed_temp" cookie set.');
                }
            }

            $formContainer.removeClass('nuf-is-visible').hide();
            setTimeout(function(){
                $formContainer.removeClass('nuf-modal-mode');
            }, 400);

            if (!window.nettmobFeedbackAlreadyGiven && !getCookie(ajax_vars.button_clicked_session_cookie_name)) {
                $triggerButton.show();
            }

            $formContainer.find('.notice-message').remove();
            $form.show();
        });

        // Conditional Field Logic
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

        // Form Submission
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
                        }
                        if (ajax_vars.debug_mode) console.log('NUF: Form submitted successfully. Main cookie lifetime setting: ' + main_cookie_lifetime + ' days.');
                        setTimeout(function() { location.reload(); }, 2000);
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
                complete: function() { submitButton.val(originalButtonText).prop('disabled', false); }
            });
        });
    });
})(jQuery);
