jQuery(function($){
   
    // Gestione toggle
    $(document).on('change', 'input[type=radio][name*="[pma_private]"]', function(){
        const $input = $(this);
        const attachIdMatch = $input.attr('name').match(/\[(\d+)\]/);
        const attachId = attachIdMatch ? attachIdMatch[1] : null;
        if (!attachId) return;

        const newVal = $input.val();
        const oldVal = (newVal === '1' ? '0' : '1');

        const message = (newVal === '1')
            ? "Sei sicuro di voler rendere privato questo file? Il file verrà spostato nella cartella protetta e il suo URL pubblico non sarà più accessibile."
            : "Sei sicuro di voler rendere pubblico questo file? Il file verrà riportato nella cartella pubblica originale.";

        if (!confirm(message)) {
            $('input[name="attachments['+attachId+'][pma_private]"][value="'+oldVal+'"]').prop('checked', true);
            return;
        }

        const action = (newVal === '1') ? 'pma_set_private' : 'pma_set_public';

        $.post(PMA_Ajax.ajax_url, {
            action: action,
            attach_id: attachId,
            _ajax_nonce: PMA_Ajax.nonce
        }, function(response){

            console.log("📦 Risposta AJAX:", response);
            if (response.success) {

                console.log("✅ AJAX successo, nuovo URL:", response.data.url);
                const url = response.data.url;

                // aggiorna il campo sotto al toggle
                const $urlField = $('#pma-url-field-' + attachId + ' input');
                $urlField.val(url);

                $('#pma-url-field-' + attachId).show();

                // Aggiorna Thumbnail
                if (newVal === '1') {
                    $('.attachment-media-view img').attr('src', PMA_Ajax.plugin_url + 'reserved-thumbnail.png');
                } else {
                    const $img = $('.attachment-media-view img');
                    if ($img.length) {
                        const currentSrc = $img.attr('src');
                        $img.attr('src', currentSrc.split('?')[0] + '?refresh=' + new Date().getTime());
                    }
                }

                // aggiorna anche l'input principale della sidebar
                $('#attachment_url').val(url);

            } else {
                alert("Errore: " + (response.data || "operazione fallita"));
            }
        });
    });

    // Pulsante "Copia URL"
    $(document).on('click', '.copy-pma-url', function(){
        const $btn = $(this);
        const $urlField = $btn.siblings('input[readonly]');
        if ($urlField.length) {
            $urlField.trigger('focus').trigger('select');
            try {
                navigator.clipboard.writeText($urlField.val());
            } catch(e) {
                console.error("Copy failed:", e);
            }
        }
    });
});
