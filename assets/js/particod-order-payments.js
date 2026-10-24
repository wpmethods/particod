jQuery(function ($) {
    $(document).on('click', '.particod-record-collection, .particod-reverse-collection, .particod-sync-analytics', function () {
        const $button = $(this);
        const $panel = $button.closest('.particod-order-payments');
        const operation = $button.hasClass('particod-record-collection') ? 'record' : ($button.hasClass('particod-reverse-collection') ? 'reverse' : 'sync');
        if (operation === 'reverse' && !window.confirm($panel.data('reverse-confirm'))) return;
        const $feedback = $panel.find('.particod-collection-feedback');
        if (operation === 'record' && !$panel.find('.particod-collection-amount')[0].reportValidity()) return;
        $panel.find('button').prop('disabled', true);
        $panel.attr('aria-busy', 'true');
        $feedback.removeClass('is-error').text($panel.data('saving-text'));
        $.post(ajaxurl, {
            action: 'particod_order_collection',
            order_id: $panel.data('order-id'),
            nonce: $panel.data('nonce'),
            request_id: $panel.data('request-id'),
            operation: operation,
            amount: $panel.find('.particod-collection-amount').val(),
            method: $panel.find('.particod-collection-method').val(),
            reference: $panel.find('.particod-collection-reference').val(),
            entry_id: $button.data('entry-id') || ''
        }).done(function (response) {
            if (response.success) {
                const $updated = $(response.data.html);
                $panel.find('details[open]').each(function () {
                    const detailClass = this.className;
                    $updated.find('details').filter(function () {
                        return this.className === detailClass;
                    }).prop('open', true);
                });
                if (operation === 'record' || operation === 'reverse') {
                    $updated.find('.particod-collection-history').prop('open', true);
                }
                $panel.replaceWith($updated);
                if (response.data.totals_html) {
                    const $totals = $('#woocommerce-order-items .particod-admin-payment-total');
                    $totals.first().before(response.data.totals_html);
                    $totals.remove();
                }
                $updated.find('.particod-collection-feedback').text(response.data.message).trigger('focus');
            } else {
                $feedback.addClass('is-error').text(response.data.message || $panel.data('error-text'));
            }
        }).fail(function (xhr) {
            const error = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
            $feedback.addClass('is-error').text(error || $panel.data('error-text'));
        }).always(function () {
            $panel.find('button').prop('disabled', false);
            $panel.removeAttr('aria-busy');
        });
    });
    $(document).on('keydown', '.particod-collection-fields input', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).closest('.particod-order-payments').find('.particod-record-collection:not(:disabled)').trigger('click');
        }
    });
});
