<?php
/**
 * Legt die Fluent-Forms-Formulare aus tools/fluent-formulare.json an (oder ersetzt gleichnamige).
 * Aufruf auf dem Server:  wp eval-file fluent_einrichten.php <pfad-zur-json> --allow-root --path=<wordpress>
 * Gibt je Formular die ID aus; sie steht danach als [fluentform id="N"] auf der Seite.
 */
global $wpdb;
$pfad = $args[0] ?? '';
$alle = json_decode((string)file_get_contents($pfad), true);
if (!is_array($alle)) {
    WP_CLI::error('JSON nicht lesbar: ' . $pfad);
}
$formulare = $wpdb->prefix . 'fluentform_forms';
$metas = $wpdb->prefix . 'fluentform_form_meta';
foreach ($alle as $seite => $d) {
    foreach ((array)$wpdb->get_col($wpdb->prepare("SELECT id FROM $formulare WHERE title = %s", $d['title'])) as $alt) {
        $wpdb->delete($metas, ['form_id' => $alt]);
        $wpdb->delete($formulare, ['id' => $alt]);
    }
    $wpdb->insert($formulare, [
        'title' => $d['title'],
        'status' => 'published',
        'form_fields' => wp_json_encode($d['form'], JSON_UNESCAPED_UNICODE),
        'has_payment' => 0,
        'type' => 'form',
        'created_by' => 1,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]);
    $id = (int)$wpdb->insert_id;
    $meta = static function (string $schluessel, $wert) use ($wpdb, $metas, $id) {
        $wpdb->insert($metas, ['form_id' => $id, 'meta_key' => $schluessel, 'value' => is_scalar($wert) ? (string)$wert : wp_json_encode($wert, JSON_UNESCAPED_UNICODE)]);
    };
    $meta('formSettings', $d['formSettings']);
    $meta('notifications', $d['notifications']);
    $meta('auto_delete_days', (int)$d['formSettings']['auto_delete_days']);
    echo $seite . '=' . $id . "\n";
}
