<?php
/**
 * Plugin Name: Private Media Access
 * Description: Gestisce file pubblici e privati con spostamento automatico, URL protetto e thumbnail riservata.
 * Version: 1.1.1
 * Author: Michele Minno & ChatGPT
 */

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('pma-admin', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], null, true);
    wp_localize_script('pma-admin', 'PMA_Ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pma_nonce'),
        'plugin_url' => plugin_dir_url(__FILE__)
    ]);

    wp_enqueue_style('pma-admin-style', plugin_dir_url(__FILE__) . 'pm-accessibility-admin.css');
});

register_activation_hook(__FILE__, 'pma_bootstrap_protected_media');

function pma_bootstrap_protected_media() {
    $base = WP_CONTENT_DIR . '/protected-media';
    if (!file_exists($base)) {
        wp_mkdir_p($base);
    }

    // .htaccess: blocca accesso diretto (Apache)
    $htaccess = $base . '/.htaccess';
    if (!file_exists($htaccess)) {
        $rules = <<<HT
# PMA: blocca accesso diretto ai file
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Deny from all
</IfModule>

# (opzionale) in caso di fallback su directory listing
Options -Indexes
HT;
        @file_put_contents($htaccess, $rules);
    }

    // index.php: evita directory listing
    $index = $base . '/index.php';
    if (!file_exists($index)) {
        @file_put_contents($index, "<?php\n// Silence is golden.\n");
    }
}


// Thumbnail riservata
add_filter('wp_generate_attachment_metadata', function($meta, $post_id) {
    $is_private = get_post_meta($post_id, '_pma_private', true) === '1';
    if ($is_private) {
        $meta['sizes'] = [];
        $meta['thumbnail'] = 'reserved-thumbnail.png';
    }
    return $meta;
}, 10, 2);

// Bloccare wp_get_attachment_url per i file privati
add_filter('wp_get_attachment_url', function($url, $post_id) {
    if (!is_admin()) return $url;

    $is_private = get_post_meta($post_id, '_pma_private', true) === '1';
    if (!$is_private) return $url;

    $relative_path = get_post_meta($post_id, '_wp_attached_file', true);
    $filename = basename($relative_path);

    // Estraggo anno e mese
    $year = date('Y');
    $month = date('m');

    if (preg_match('#(?:^|/)protected-media/(\d{4})/(\d{2})/#', $relative_path, $m)) {
        $year = $m[1];
        $month = $m[2];
    } elseif (preg_match('#(\d{4})/(\d{2})/#', $relative_path, $m)) {
        $year = $m[1];
        $month = $m[2];
    }

    return site_url("/wp-content/plugins/private-media-access/download.php?file={$filename}&year={$year}&month={$month}");
}, 10, 2);


// Aggiungi il campo Visibilità e Cambia l'URL nella sidebar
add_filter('attachment_fields_to_edit', function($form_fields, $post) {
    $is_private = get_post_meta($post->ID, '_pma_private', true) === '1';

    // Percorso relativo al file
    $relative_path = get_post_meta($post->ID, '_wp_attached_file', true);
    $filename = basename($relative_path);

    // Default a valori sensati
    $year = date('Y', strtotime($post->post_date));
    $month = date('m', strtotime($post->post_date));

    // Se possibile, estrai anno e mese dal path
    if (preg_match('#(?:^|/)protected-media/(\d{4})/(\d{2})/#', $relative_path, $m)) {
        $year = $m[1];
        $month = $m[2];
    } elseif (preg_match('#(\d{4})/(\d{2})/#', $relative_path, $m)) {
        $year = $m[1];
        $month = $m[2];
    }

    $url = $is_private
        ? site_url("/wp-content/plugins/private-media-access/download.php?file={$filename}&year={$year}&month={$month}")
        : wp_get_attachment_url($post->ID);

    pma_log("URL attuale per l'allegato {$post->ID}: $url ".($is_private ? '(privato)' : '(pubblico)'), 'info');

    // CAMPO VISIBILITÀ con radio toggle + campo URL
    $style = $is_private ? '' : 'display:none;';
    $form_fields['pma_private'] = [
        'label' => 'Visibilità',
        'input' => 'html',
        'html' => '
            <div style="margin-top: 12px; margin-bottom: 6px; font-size: 13px;">
                Imposta il file come pubblico o privato:
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; max-width: 100%;">
                <label style="margin: 0 10px 0 0;">
                    <input type="radio" name="attachments['.$post->ID.'][pma_private]" value="0" '.(!$is_private ? 'checked' : '').'>
                    Pubblico
                </label>
                <label style="margin-right: 12px;">
                    <input type="radio" name="attachments['.$post->ID.'][pma_private]" value="1" '.($is_private ? 'checked' : '').'>
                    Privato
                </label>
                <div id="pma-url-field-'.$post->ID.'" style="'.$style.' display: flex; align-items: center; gap: 8px;">
                    <input id="pma-inline-url type="text" readonly value="'.esc_attr(wp_get_attachment_url($post->ID)).'" size="50" />
                    <button type="button" class="button copy-pma-url">Copia URL</button>
                </div>
            </div>'
    ];

    // CAMPO URL DELLA SIDEBAR che varia a seconda della visibilità impostata

    $form_fields['url']['label'] = 'URL del file';
    $form_fields['url']['input'] = 'html';
    $form_fields['url']['html'] = '<input id="pma-sidebar-url" type="text" readonly style="width:100%;" value="'.esc_attr($url).'">';
    $form_fields['url']['helps'] = $is_private
        ? 'URL sicuro per accedere al file privato.'
        : 'URL diretto al file pubblico.';

    return $form_fields;
}, 10, 2);



// Azioni AJAX
function pma_move_file($attach_id, $to_private = true) {

    $upload_dir = wp_upload_dir();
    $post = get_post($attach_id);
    $year = date('Y', strtotime($post->post_date));
    $month = date('m', strtotime($post->post_date));
    $filename = basename(get_attached_file($attach_id));
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $source_dir = $to_private
        ? $upload_dir['basedir'] . "/{$year}/{$month}" // se sto rendendo privato, i file sono pubblici
        : WP_CONTENT_DIR . "/protected-media/{$year}/{$month}"; // se sto rendendo pubblico, i file sono privati

    $source_path = $source_dir . '/' . $filename;

    $dest_dir = $to_private
        ? WP_CONTENT_DIR . "/protected-media/{$year}/{$month}"
        : $upload_dir['basedir'] . "/{$year}/{$month}";

    if (!file_exists($dest_dir)) {
        if (!wp_mkdir_p($dest_dir)) {
            pma_log("❌ Impossibile creare la cartella di destinazione: $dest_dir", 'error');
            return false;
        }
        pma_log("✅ Cartella $dest_dir creata con successo", 'success');
    }

    $pattern = $source_dir . '/' . $basename . '*';
    $files_to_move = glob($pattern);

    if (!$files_to_move || empty($files_to_move)) {
        pma_log("❌ Nessun file trovato con pattern $pattern", 'error');
        return false;
    }

    $moved_main_file = null;

    foreach ($files_to_move as $each_file_path) {
        $each_basename = basename($each_file_path);
        $each_new_path = $dest_dir . '/' . $each_basename;

        if (@rename($each_file_path, $each_new_path)) {
            pma_log("✅ Spostamento riuscito: $each_basename", 'success');
            if ($each_file_path === $source_path) {
                $moved_main_file = $each_new_path;
            }
        } else {
            pma_log("❌ Errore nello spostamento di $each_basename", 'error');
        }
    }

    if (!$moved_main_file) {
        pma_log("❌ Nessun file principale spostato. Source path: $source_path", 'error');
        return false;
    }

    $new_relative = str_replace($upload_dir['basedir'] . '/', '', $moved_main_file);
    update_post_meta($attach_id, '_wp_attached_file', $new_relative);
    update_attached_file($attach_id, $moved_main_file);
    update_post_meta($attach_id, '_pma_private', $to_private ? '1' : '0');

    if ($to_private) {
        update_post_meta($attach_id, '_wp_attachment_metadata', ['sizes' => [], 'thumbnail' => 'reserved-thumbnail.png']);
    }

    $url = $to_private
        ? site_url("/wp-content/plugins/private-media-access/download.php?file=" . basename($moved_main_file) . "&year={$year}&month={$month}")
        : wp_get_upload_dir()['baseurl'] . '/' . $year . '/' . $month . '/' . basename($moved_main_file);

    return $url;
}


function pma_log($message, $level = 'info') {
    $log_file = plugin_dir_path(__FILE__) . 'pma-log.txt';

    // Se il file non esiste, lo crea
    if (!file_exists($log_file)) {
        file_put_contents($log_file, "==== Private Media Access Log ====\n", FILE_APPEND);
    }

    // Controlla che sia scrivibile
    if (!is_writable($log_file)) {
        error_log("❌ Il file di log non è scrivibile: $log_file");
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $level = strtoupper($level);
    $color = match ($level) {
        'ERROR' => '❌',
        'SUCCESS' => '✅',
        'INFO' => 'ℹ️',
        default => ''
    };

    $log_line = "[$timestamp] [$level] $color $message" . PHP_EOL;
    file_put_contents($log_file, $log_line, FILE_APPEND);
}


add_action('wp_ajax_pma_set_private', function() {
    check_ajax_referer('pma_nonce');
    $attach_id = intval($_POST['attach_id']);
    pma_log("🔔 Ricevuta richiesta per rendere privato l'allegato $attach_id", 'info');

    $url = pma_move_file($attach_id, true);
    if ($url) {
        pma_log("✅ File $attach_id reso privato. URL: $url", 'success');
        wp_send_json_success(['url' => $url]);
    } else {
        wp_send_json_error('Impossibile rendere il file privato.');
    }
});

add_action('wp_ajax_pma_set_public', function() {

    check_ajax_referer('pma_nonce');
    $attach_id = intval($_POST['attach_id']);
    pma_log("Richiesta: impostare pubblico l'allegato $attach_id", 'info');
    $url = pma_move_file($attach_id, false);
    if ($url) {
        pma_log("✔️ File $attach_id reso pubblico. URL aggiornato: $url", 'success');
        wp_send_json_success(['url' => $url]);
    } else {
        pma_log("❌ Errore nel rendere pubblico il file $attach_id", 'error');
        wp_send_json_error('Impossibile rendere il file pubblico.');
    }
});

// Aggiungi il file di log
add_action('admin_menu', function() {
    add_menu_page(
        'Log PMA',
        'Log PMA',
        'manage_options',
        'pma-log',
        function() {
            include plugin_dir_path(__FILE__) . 'pma-logs.php';
        },
        'dashicons-media-document'
    );
});

function pma_clear_log() {
  $log_file = plugin_dir_path(__FILE__) . 'pma-log.txt';
  $timestamp = date('Y-m-d H:i:s');
  $log_line = "[$timestamp] [INFO] ℹ️ Log azzerato manualmente dall'amministratore\n";
  file_put_contents($log_file, $log_line);
}

// Per il caricamento corretto della Thumbnail
// 📸 Sostituisce la thumbnail nel frontend e nei template
add_filter('wp_get_attachment_image_src', function($image, $attachment_id, $size, $icon) {
    $is_private = get_post_meta($attachment_id, '_pma_private', true) === '1';
    if ($is_private) {
        $image[0] = plugin_dir_url(__FILE__) . 'reserved-thumbnail.png'; // path all'immagine riservata
        $image[1] = 150; // larghezza fittizia
        $image[2] = 150; // altezza fittizia
    }
    return $image;
}, 10, 4);

// 🧾 Sostituisce l'immagine anche nella Media Modal sidebar (non usa wp_get_attachment_image_src!)
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment) {
    $is_private = get_post_meta($attachment->ID, '_pma_private', true) === '1';
    if ($is_private) {
        $attr['src'] = plugin_dir_url(__FILE__) . 'reserved-thumbnail.png';
    }
    return $attr;
}, 10, 2);

/**
 * Eliminazione sicura dei file privati da protected-media quando si elimina l'allegato.
 * - Cancella originale + derivati (pattern basename*)
 * - Funziona sia se il meta _pma_private è '1' sia se il path è in protected-media
 * - Pulisce le directory vuote anno/mese
 */

// Quando WP sta cancellando fisicamente un file, rimuovi anche i "fratelli"
add_filter('wp_delete_file', function ($file) {
    if (!$file || !is_string($file)) return $file;

    $protected_root = WP_CONTENT_DIR . '/protected-media';
    $dir = dirname($file);

    // Considera "privato" se è marcato tale oppure se sta fisicamente in protected-media
    $is_in_protected = str_starts_with($dir, $protected_root);

    if ($is_in_protected) {
        $basename = pathinfo($file, PATHINFO_FILENAME);
        $pattern  = $dir . '/' . $basename . '*';

        $to_delete = glob($pattern) ?: [];
        $deleted = 0;

        foreach ($to_delete as $p) {
            if (is_file($p) && @unlink($p)) {
                $deleted++;
                pma_log("🗑️ Eliminato file privato correlato: " . basename($p), 'success');
            }
        }

        // Prova a ripulire le dir vuote (mese, poi anno)
        pma_try_rmdir_if_empty($dir);
        pma_try_rmdir_if_empty(dirname($dir));

        pma_log("Totale file rimossi da protected-media: {$deleted} (pattern: {$pattern})", 'info');

        // Restituisci comunque $file per lasciare che WP proceda normalmente (se esiste ancora)
        // (Se lo abbiamo già cancellato noi, WP troverà il path mancante e andrà avanti senza errori)
        return $file;
    }

    return $file;
});

/**
 * Fallback: se per qualche motivo l'hook sopra non scatta, intercetta la cancellazione dell'allegato.
 * Utile se l'allegato è marcato privato ma il path non è ancora stato aggiornato.
 */
add_action('delete_attachment', function ($post_id) {
    $file = get_attached_file($post_id);
    if (!$file) return;

    $is_private = get_post_meta($post_id, '_pma_private', true) === '1';
    $protected_root = WP_CONTENT_DIR . '/protected-media';
    $dir = dirname($file);
    $is_in_protected = str_starts_with($dir, $protected_root);

    if (!$is_private && !$is_in_protected) return;

    $basename = pathinfo($file, PATHINFO_FILENAME);
    $pattern  = $dir . '/' . $basename . '*';

    $to_delete = glob($pattern) ?: [];
    $deleted = 0;

    foreach ($to_delete as $p) {
        if (is_file($p) && @unlink($p)) {
            $deleted++;
            pma_log("🗑️ [fallback] Eliminato file privato correlato: " . basename($p), 'success');
        }
    }

    pma_try_rmdir_if_empty($dir);
    pma_try_rmdir_if_empty(dirname($dir));

    pma_log("✔️ [fallback] Allegato {$post_id}: rimossi {$deleted} file da protected-media (pattern: {$pattern})", 'info');
});

/**
 * Utility: elimina la directory se vuota (silenziosamente).
 */
if (!function_exists('pma_try_rmdir_if_empty')) {
    function pma_try_rmdir_if_empty(string $dir): void {
        $protected_root = WP_CONTENT_DIR . '/protected-media';
        // Non uscire dalla radice di protected-media
        if (!str_starts_with($dir, $protected_root)) return;
        if (!is_dir($dir)) return;

        $items = @scandir($dir);
        if ($items === false) return;

        // solo '.' e '..' => vuota
        if (count(array_diff($items, ['.', '..'])) === 0) {
            @rmdir($dir);
            pma_log("🧹 Rimossa cartella vuota: {$dir}", 'info');
        }
    }
}

/**
 * PHP 8.0 shim per str_starts_with se serve (su alcune installazioni vecchie).
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle): bool {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

