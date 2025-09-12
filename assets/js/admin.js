// Produktor WP - Admin JavaScript

(function($) {
    'use strict';
    
    // Globalne zmienne
    let selectedPosts = [];
    let currentSiteId = null;
    let publishInProgress = false;
    
    // Inicjalizacja
    $(document).ready(function() {
        initializeAdmin();
        bindEvents();
        updateSelectedCount();
    });
    
    function initializeAdmin() {
        // Ustaw domyślne daty
        const now = new Date();
        const nextMonth = new Date(now.getTime() + (30 * 24 * 60 * 60 * 1000));
        
        $('#date-from').val(formatDateForInput(now));
        $('#date-to').val(formatDateForInput(nextMonth));
        
        // Załaduj autorów dla wybranej strony
        $('#target-site').trigger('change');
    }
    
    function bindEvents() {
        // Modal dodawania strony
        $('#add-site-btn').on('click', function() {
            $('#add-site-modal').show();
        });
        
        $('.modal-close').on('click', function() {
            $(this).closest('.produktor-wp-modal').hide();
        });
        
        // Zamknij modal po kliknięciu w tło
        $('.produktor-wp-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Test połączenia
        $('#test-connection-btn').on('click', testConnection);
        
        // Formularz dodawania strony
        $('#add-site-form').on('submit', addSite);
        
        // Synchronizacja strony
        $('.sync-site-btn').on('click', function() {
            const siteId = $(this).data('site-id');
            syncSite(siteId);
        });
        
        // Dodawanie pojedynczego artykułu
        $('.add-post-btn').on('click', function() {
            const siteId = $(this).data('site-id');
            showPostEditor(siteId);
        });
        
        // Wybór wszystkich postów
        $('#select-all-posts, #select-all-checkbox').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.post-checkbox').prop('checked', isChecked);
            updateSelectedPosts();
        });
        
        // Pojedynczy checkbox
        $(document).on('change', '.post-checkbox', updateSelectedPosts);
        
        // Wyczyść zaznaczenie
        $('#clear-selection').on('click', function() {
            $('.post-checkbox').prop('checked', false);
            $('#select-all-checkbox').prop('checked', false);
            updateSelectedPosts();
        });
        
        // Zmiana strony docelowej
        $('#target-site').on('change', function() {
            const siteId = $(this).val();
            if (siteId) {
                loadSiteAuthors(siteId);
            }
        });
        
        // Rozpocznij publikację hurtową
        $('#start-bulk-publish').on('click', startBulkPublish);
    }
    
    function testConnection() {
        const $button = $(this);
        const $form = $('#add-site-form');
        
        const formData = {
            action: 'produktor_wp_action',
            sub_action: 'test_connection',
            nonce: produktorWP.nonce,
            url: $('#site-url').val(),
            username: $('#site-username').val(),
            password: $('#site-password').val()
        };
        
        $button.prop('disabled', true).text('Testowanie...');
        
        $.post(produktorWP.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice('success', 'Połączenie udane! Możesz dodać stronę.');
            } else {
                showNotice('error', 'Błąd połączenia: ' + response.message);
            }
        }).fail(function() {
            showNotice('error', 'Wystąpił błąd podczas testowania połączenia.');
        }).always(function() {
            $button.prop('disabled', false).text('Testuj połączenie');
        });
    }
    
    function addSite(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        const formData = {
            action: 'produktor_wp_action',
            sub_action: 'add_site',
            nonce: produktorWP.nonce,
            name: $('#site-name').val(),
            url: $('#site-url').val(),
            username: $('#site-username').val(),
            password: $('#site-password').val()
        };
        
        $submitBtn.prop('disabled', true).text('Dodawanie...');
        
        $.post(produktorWP.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice('success', response.message);
                $('#add-site-modal').hide();
                $form[0].reset();
                
                // Odśwież stronę po 1 sekundzie
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showNotice('error', response.message);
            }
        }).fail(function() {
            showNotice('error', 'Wystąpił błąd podczas dodawania strony.');
        }).always(function() {
            $submitBtn.prop('disabled', false).text('Dodaj stronę');
        });
    }
    
    function syncSite(siteId) {
        const $button = $('.sync-site-btn[data-site-id="' + siteId + '"]');
        
        const formData = {
            action: 'produktor_wp_action',
            sub_action: 'get_site_stats',
            nonce: produktorWP.nonce,
            site_id: siteId
        };
        
        $button.prop('disabled', true).text('Sync...');
        
        $.post(produktorWP.ajax_url, formData, function(response) {
            if (response.posts_count !== undefined) {
                // Aktualizuj statystyki w tabeli
                const $row = $button.closest('tr');
                $row.find('td:eq(3)').text(response.posts_count);
                
                // Aktualizuj kategorie
                const $select = $row.find('.categories-select');
                $select.empty();
                if (response.categories) {
                    response.categories.forEach(function(category) {
                        $select.append('<option value="' + category.id + '">' + category.name + '</option>');
                    });
                }
                
                showNotice('success', 'Synchronizacja zakończona pomyślnie.');
            } else {
                showNotice('error', 'Błąd podczas synchronizacji.');
            }
        }).fail(function() {
            showNotice('error', 'Wystąpił błąd podczas synchronizacji.');
        }).always(function() {
            $button.prop('disabled', false).text('Sync');
        });
    }
    
    function updateSelectedPosts() {
        selectedPosts = [];
        $('.post-checkbox:checked').each(function() {
            selectedPosts.push($(this).val());
        });
        updateSelectedCount();
    }
    
    function updateSelectedCount() {
        const count = selectedPosts.length;
        $('.selected-count').text(count + ' wybranych');
        
        // Włącz/wyłącz przycisk publikacji
        $('#start-bulk-publish').prop('disabled', count === 0 || publishInProgress);
        
        // Aktualizuj checkbox "zaznacz wszystkie"
        const totalCheckboxes = $('.post-checkbox').length;
        const checkedCheckboxes = $('.post-checkbox:checked').length;
        
        $('#select-all-checkbox').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
        $('#select-all-checkbox').prop('checked', checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0);
    }
    
    function loadSiteAuthors(siteId) {
        const $select = $('#target-author');
        
        // Tu można dodać wywołanie AJAX aby pobrać autorów z zewnętrznej strony
        // Na razie zostawiamy domyślną opcję
        $select.html('<option value="">Domyślny</option>');
    }
    
    function startBulkPublish() {
        if (selectedPosts.length === 0) {
            showNotice('error', 'Wybierz co najmniej jeden post do publikacji.');
            return;
        }
        
        const siteId = $('#target-site').val();
        if (!siteId) {
            showNotice('error', 'Wybierz stronę docelową.');
            return;
        }
        
        const dateFrom = $('#date-from').val();
        const dateTo = $('#date-to').val();
        
        if (!dateFrom || !dateTo) {
            showNotice('error', 'Ustaw przedział dat publikacji.');
            return;
        }
        
        if (new Date(dateFrom) >= new Date(dateTo)) {
            showNotice('error', 'Data początkowa musi być wcześniejsza niż końcowa.');
            return;
        }
        
        const authorId = $('#target-author').val();
        const duplicateAction = $('#duplicate-action').val();
        
        publishInProgress = true;
        updateSelectedCount();
        
        const $button = $('#start-bulk-publish');
        $button.text('Publikuję...').prop('disabled', true);
        
        $('.progress-container').show();
        updateProgress(0);
        
        const formData = {
            action: 'produktor_wp_action',
            sub_action: 'bulk_publish',
            nonce: produktorWP.nonce,
            post_ids: selectedPosts,
            site_id: siteId,
            date_from: dateFrom,
            date_to: dateTo,
            author_id: authorId,
            duplicate_action: duplicateAction
        };
        
        $.post(produktorWP.ajax_url, formData, function(response) {
            if (response.success) {
                const results = response.results;
                
                // Pokaż szczegółowe wyniki
                let message = `Publikacja zakończona!\n`;
                message += `Sukces: ${results.success}\n`;
                message += `Pominięte: ${results.skipped}\n`;
                message += `Błędy: ${results.errors}`;
                
                showNotice(results.errors === 0 ? 'success' : 'warning', message);
                
                updateProgress(100);
                
                // Usuń opublikowane posty z listy
                results.details.forEach(function(detail) {
                    if (detail.success) {
                        $('.post-checkbox[value="' + detail.post_id + '"]').closest('tr').fadeOut();
                    }
                });
                
                // Wyczyść zaznaczenie
                setTimeout(function() {
                    $('#clear-selection').click();
                }, 2000);
                
            } else {
                showNotice('error', 'Błąd podczas publikacji: ' + (response.message || 'Nieznany błąd'));
                updateProgress(0);
            }
        }).fail(function() {
            showNotice('error', 'Wystąpił błąd podczas publikacji.');
            updateProgress(0);
        }).always(function() {
            publishInProgress = false;
            $button.text('Rozpocznij publikację').prop('disabled', false);
            updateSelectedCount();
            
            setTimeout(function() {
                $('.progress-container').hide();
            }, 3000);
        });
    }
    
    function updateProgress(percent) {
        $('.progress-fill').css('width', percent + '%');
        $('.progress-text').text(percent + '%');
    }
    
    function showPostEditor(siteId) {
        // Tu można dodać modal z edytorem WordPress
        // Na razie pokazujemy alert
        alert('Funkcja edytora postów zostanie dodana w następnej wersji.');
    }
    
    function showNotice(type, message) {
        // Usuń poprzednie powiadomienia
        $('.notice').remove();
        
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Zamknij powiadomienie</span>' +
            '</button>' +
        '</div>');
        
        $('.produktor-wp-content').prepend($notice);
        
        // Przewiń do powiadomienia
        $('html, body').animate({
            scrollTop: $notice.offset().top - 50
        }, 500);
        
        // Automatycznie ukryj po 5 sekundach
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Przycisk zamknięcia
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut();
        });
    }
    
    function formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    
    // Funkcje pomocnicze
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Eksport funkcji dla innych skryptów
    window.produktorWP = window.produktorWP || {};
    window.produktorWP.showNotice = showNotice;
    window.produktorWP.updateProgress = updateProgress;
    
})(jQuery);