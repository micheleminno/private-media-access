<?php

// Azzera log se richiesto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_log'])) {
    pma_clear_log();
    wp_redirect(admin_url('admin.php?page=pma-log'));
    exit;
}

// Sicurezza
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

$log_path = plugin_dir_path(__FILE__) . 'pma-log.txt';
$log_content = file_exists($log_path) ? file_get_contents($log_path) : '';
$lines = explode("\n", $log_content);
?>

<div class="wrap">
    <h1>Log Private Media Access</h1>
    <div style="display: flex; gap: 10px; margin-bottom: 12px;">
        <!-- Bottone Azzera Log -->
        <form method="post" style="margin-bottom: 12px;">
            <input type="hidden" name="page" value="pma-log">
            <input type="hidden" name="reset_log" value="1">
            <button type="submit" class="button button-secondary">🧹 Azzera Log</button>
        </form>

        <form method="get" style="display: inline-block; margin-left: 8px;">
            <input type="hidden" name="page" value="pma-log">
            <button type="submit" class="button button-secondary">🔄 Aggiorna Log</button>
        </form>
    </div>

    <input type="text" id="log-search" placeholder="Filtra il log..." style="width: 100%; padding: 8px; font-size: 16px; margin-bottom: 12px;">

    <div id="log-container" style="background:#fff; padding:1em; border:1px solid #ccc; font-family:monospace; white-space:pre-wrap; max-height:500px; overflow-y:auto;">
        <?php foreach ($lines as $line): ?>
            <?php
            $escaped_line = esc_html($line);
            $class = '';

            if (preg_match('/\[ERROR\]|\berrore\b|fail/i', $line)) {
                $class = 'log-error';
            } elseif (preg_match('/\[SUCCESS\]|\briuscito\b|✅/i', $line)) {
                $class = 'log-success';
            } elseif (preg_match('/\[INFO\]|\bspostamento\b|🔄|ℹ️/i', $line)) {
                $class = 'log-info';
            }
            ?>
            <div class="log-line <?= $class ?>"><?= $escaped_line ?></div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.log-error {
    color: #b30000;
    font-weight: bold;
}
.log-success {
    color: #006600;
    font-weight: bold;
}
.log-info {
    color: #005580;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('log-search');
    const lines = document.querySelectorAll('.log-line');
    const logBox = document.getElementById('log-container');

    // Scrolla in fondo al log
    logBox.scrollTop = logBox.scrollHeight;

    searchInput.addEventListener('input', function () {
        const filter = this.value.toLowerCase();
        let visibleCount = 0;

        lines.forEach(line => {
            if (line.textContent.toLowerCase().includes(filter)) {
                line.style.display = 'block';
                visibleCount++;
            } else {
                line.style.display = 'none';
            }
        });

        // Optional: show a "no results" message
        if (visibleCount === 0 && !document.getElementById('no-results')) {
            const noResults = document.createElement('div');
            noResults.id = 'no-results';
            noResults.textContent = 'Nessun risultato trovato.';
            noResults.style.color = 'gray';
            noResults.style.marginTop = '10px';
            document.getElementById('log-container').appendChild(noResults);
        } else if (visibleCount > 0 && document.getElementById('no-results')) {
            document.getElementById('no-results').remove();
        }
    });
});
</script>
