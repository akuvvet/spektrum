<?php
/**
 * Legt die Formulare aus tools/fluent-formulare.json (neutrale Felddefinition) in Formidable Forms an.
 * Aufruf auf dem Server:  wp eval-file formidable_formulare.php <pfad-zur-json> --user=1 --allow-root --path=<wordpress>
 * Gibt je Seite die Formular-ID aus; sie steht danach als [formidable id=N] auf der Seite.
 */
global $wpdb;
$alle = json_decode((string)file_get_contents($args[0] ?? ''), true);
if (!is_array($alle)) {
    WP_CLI::error('JSON nicht lesbar');
}
$beschriftung = ['sorgeberechtigt' => 'Sorgeberechtigt', 'datenschutz' => 'Datenschutz', 'fuehrungszeugnis' => 'Führungszeugnis', 'infomail' => 'Infomails', 'satzung' => 'Satzung'];

foreach ($alle as $seite => $d) {
    foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}frm_forms WHERE name = %s", $d['title'])) as $alt) {
        FrmForm::destroy($alt);
    }
    $felderDef = $d['form']['fields'];
    $hatRechnen = false;
    foreach ($felderDef as $f) {
        if (($f['settings']['html_codes'] ?? '') === '@RECHNEN@') {
            $hatRechnen = true;
        }
    }
    $o = FrmFormsHelper::get_default_opts();
    $o['submit_value'] = $d['form']['submitButton']['settings']['button_ui']['text'];
    $o['success_action'] = 'redirect';
    $o['success_url'] = $d['formSettings']['confirmation']['customUrl'];
    $o['antispam'] = 1;
    $formId = FrmForm::create(['form_key' => sanitize_title($seite), 'name' => $d['title'], 'description' => '', 'status' => 'published', 'options' => $o]);

    $reihe = 0;
    $mailFeld = 0;
    foreach ($felderDef as $f) {
        $el = $f['element'];
        $s = $f['settings'];
        $a = is_array($f['attributes']) ? $f['attributes'] : [];
        $pflicht = !empty($s['validation_rules']['required']['value']);
        $typ = ['input_text' => 'text', 'input_email' => 'email', 'textarea' => 'textarea', 'select' => 'select', 'input_radio' => 'radio', 'input_checkbox' => 'checkbox', 'terms_and_condition' => 'checkbox', 'custom_html' => 'html'][$el] ?? null;
        if ($typ === null) {
            continue;
        }
        $w = FrmFieldsHelper::setup_new_vars($typ, $formId);
        $w['form_id'] = $formId;
        $w['field_order'] = ++$reihe;
        $w['required'] = $pflicht ? 1 : 0;
        $extra = ['blank' => 'Bitte füllen Sie dieses Feld aus.', 'invalid' => 'Bitte prüfen Sie Ihre Eingabe.'];
        if ($el === 'custom_html') {
            $w['name'] = 'Hinweis';
            $w['description'] = $s['html_codes'];   // das HTML-Feld gibt die Beschreibung aus
            $w['required'] = 0;
        } elseif ($el === 'terms_and_condition') {
            $w['name'] = $beschriftung[$a['name']] ?? ucfirst($a['name']);
            $w['options'] = [$s['tnc_html']];
            $extra['blank'] = 'Bitte bestätigen Sie dieses Feld.';
        } else {
            $w['name'] = $s['label'];
            if (!empty($s['help_message'])) {
                $w['description'] = $s['help_message'];
            }
            if (!empty($a['maxlength'])) {
                $extra['max'] = $a['maxlength'];
            }
            if ($el === 'select') {
                $w['options'] = array_merge([''], array_values($f['options']));
            } elseif ($el === 'input_radio' || $el === 'input_checkbox') {
                $w['options'] = array_values($f['options']);
            }
            if ($el === 'input_email') {
                $extra['invalid'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
            }
        }
        $w['field_options'] = array_merge($w['field_options'] ?? [], $extra);
        $id = (int)FrmField::create($w);
        if (($a['name'] ?? '') === 'email') {
            $mailFeld = $id;
        }
    }

    $n = $d['notifications'];
    wp_insert_post([
        'post_type' => 'frm_form_actions', 'post_status' => 'publish', 'post_excerpt' => 'email',
        'post_title' => $n['name'], 'menu_order' => $formId,
        'post_content' => FrmAppHelper::prepare_and_encode([
            'email_to' => $n['sendTo']['email'], 'cc' => '', 'bcc' => '',
            'reply_to' => $mailFeld ? '[' . $mailFeld . ']' : '',
            'from' => 'Website Spektrum <' . $n['fromEmail'] . '>',
            'email_subject' => $n['subject'],
            'email_message' => '[default-message]',
            'event' => ['create'], 'conditions' => ['send_stop' => 'send', 'any_all' => 'any'],
        ]),
    ]);
    echo $seite . '=' . $formId . ($hatRechnen ? ' (Rechenfrage)' : '') . "\n";
}
