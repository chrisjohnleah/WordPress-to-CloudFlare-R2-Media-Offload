/* global r2MediaOffload */
jQuery(document).ready(function($) {
    'use strict';

    var isProcessing = false;
    var progressInterval = null;
    var currentAction = null;

    function updateProgress(processed, total) {
        if (!total || total <= 0) {
            total = 1; // Prevent division by zero
        }
        var percentage = Math.min(Math.round((processed / total) * 100), 100);
        $('.r2-media-offload-progress-bar-inner').css('width', percentage + '%');
        $('.r2-media-offload-progress-text').text(processed + ' / ' + total + ' (' + percentage + '%)');
    }

    function getProgressAction() {
        switch (currentAction) {
            case 'r2_migrate_media':
            case 'r2_revert_media':
            case 'r2_reupload_media':
            case 'r2_delete_local_media':
                return currentAction + '_progress';
            default:
                return 'r2_migrate_media_progress';
        }
    }

    function startProgressCheck() {
        if (progressInterval) {
            clearInterval(progressInterval);
        }

        progressInterval = setInterval(function() {
            $.ajax({
                url: r2MediaOffload.ajaxUrl,
                type: 'POST',
                data: {
                    action: getProgressAction(),
                    nonce: r2MediaOffload.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        updateProgress(response.data.current, response.data.total);
                        
                        if (!isProcessing) {
                            clearInterval(progressInterval);
                            progressInterval = null;
                        }
                    } else {
                        console.error('Invalid progress response:', response);
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Progress check error:', error);
                    if (xhr.responseText) {
                        console.error('Server response:', xhr.responseText);
                    }
                    clearInterval(progressInterval);
                    progressInterval = null;
                }
            });
        }, 2000);
    }

    function processMediaBatch(action, offset = 0) {
        if (isProcessing) {
            return;
        }

        isProcessing = true;
        currentAction = action;
        $('.r2-media-offload-progress').show();
        $('.r2-media-offload-buttons button').prop('disabled', true);

        // Start progress check
        startProgressCheck();

        $.ajax({
            url: r2MediaOffload.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                offset: offset,
                nonce: r2MediaOffload.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && response.data.complete) {
                        isProcessing = false;
                        currentAction = null;
                        $('.r2-media-offload-buttons button').prop('disabled', false);
                        $('.r2-media-offload-progress').hide();
                        
                        // Clear progress check
                        if (progressInterval) {
                            clearInterval(progressInterval);
                            progressInterval = null;
                        }

                        location.reload();
                    } else if (response.data && typeof response.data.processed !== 'undefined') {
                        processMediaBatch(action, response.data.processed);
                    } else {
                        console.error('Invalid response format:', response);
                        isProcessing = false;
                        currentAction = null;
                        $('.r2-media-offload-buttons button').prop('disabled', false);
                        $('.r2-media-offload-progress').hide();
                        
                        if (progressInterval) {
                            clearInterval(progressInterval);
                            progressInterval = null;
                        }
                        
                        alert(r2MediaOffload.strings.error + ' ' + r2MediaOffload.strings.unknownError);
                    }
                } else {
                    isProcessing = false;
                    currentAction = null;
                    $('.r2-media-offload-buttons button').prop('disabled', false);
                    $('.r2-media-offload-progress').hide();
                    
                    // Clear progress check
                    if (progressInterval) {
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }

                    var errorMessage = response.data ? response.data : r2MediaOffload.strings.unknownError;
                    alert(r2MediaOffload.strings.error + ' ' + errorMessage);
                }
            },
            error: function(xhr, status, error) {
                isProcessing = false;
                currentAction = null;
                $('.r2-media-offload-buttons button').prop('disabled', false);
                $('.r2-media-offload-progress').hide();
                
                // Clear progress check
                if (progressInterval) {
                    clearInterval(progressInterval);
                    progressInterval = null;
                }

                var errorMessage;
                try {
                    var response = JSON.parse(xhr.responseText);
                    errorMessage = response.data ? response.data : error;
                } catch(e) {
                    errorMessage = error || r2MediaOffload.strings.unknownError;
                }
                
                alert(r2MediaOffload.strings.error + ' ' + errorMessage);
                console.error('AJAX error:', error);
                if (xhr.responseText) {
                    console.error('Server response:', xhr.responseText);
                }
            }
        });
    }

    $('#r2-migrate-media').on('click', function() {
        if (confirm(r2MediaOffload.strings.confirmMigrate)) {
            processMediaBatch('r2_migrate_media');
        }
    });

    $('#r2-revert-media').on('click', function() {
        if (confirm(r2MediaOffload.strings.confirmRevert)) {
            processMediaBatch('r2_revert_media');
        }
    });

    $('#r2-reupload-media').on('click', function() {
        if (confirm(r2MediaOffload.strings.confirmReupload)) {
            processMediaBatch('r2_reupload_media');
        }
    });

    $('#r2-delete-local-media').on('click', function() {
        if (confirm(r2MediaOffload.strings.confirmDeleteLocal)) {
            processMediaBatch('r2_delete_local_media');
        }
    });

    // Handle confirmation dialogs
    $('form').on('submit', function(e) {
        var $submitButton = $(this).find('input[type="submit"]');
        
        if ($submitButton.attr('onclick')) {
            // Button already has onclick handler
            return true;
        }

        if ($submitButton.attr('name') === 'cloudflare_r2_migrate_media') {
            if (!confirm(r2MediaOffload.strings.confirmMigrate)) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Auto-update endpoint URL based on account ID
    $('#cloudflare_r2_account_id').on('input', function() {
        var accountId = $(this).val();
        if (accountId) {
            $('#cloudflare_r2_endpoint').val('https://' + accountId + '.r2.cloudflarestorage.com');
        }
    });

    // Clear logs
    $('#clear-logs').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to clear the logs?')) {
            $.ajax({
                url: r2MediaOffload.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'r2_clear_logs',
                    nonce: r2MediaOffload.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.r2-log-viewer').empty();
                    }
                }
            });
        }
    });

    // Refresh stats
    function refreshStats() {
        $.ajax({
            url: r2MediaOffload.ajaxUrl,
            data: {
                action: 'r2_get_stats',
                nonce: r2MediaOffload.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.r2-stat-box p').text(response.data.total_size);
                }
            }
        });
    }
}); 