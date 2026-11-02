<?php
/**
 * Legt das Anmeldeformular "Fastenbrechen 2027" in Formidable Forms an.
 * Aufruf auf dem Server:  wp eval-file formidable_fasten.php --user=1 --allow-root --path=<wordpress>
 */
global $wpdb;
$titel = 'Fastenbrechen 2027 – Anmeldung';
foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}frm_forms WHERE name = %s", $titel)) as $alt) {
    FrmForm::destroy($alt);
}

$optionen = FrmFormsHelper::get_default_opts();
$optionen['submit_value'] = 'Verbindlich anmelden';
$optionen['success_action'] = 'message';
$optionen['success_msg'] = 'Vielen Dank für Ihre Anmeldung zum Fastenbrechen 2027! Wir melden uns nur, falls es Rückfragen gibt.';
$optionen['antispam'] = 1;
$formId = FrmForm::create([
    'form_key' => 'fastenbrechen-2027',
    'name' => $titel,
    'description' => '',
    'status' => 'published',
    'options' => $optionen,
]);

$feld = static function (string $typ, string $name, bool $pflicht, int $reihe, array $extra = [], $optionenListe = null) use ($formId): int {
    $w = FrmFieldsHelper::setup_new_vars($typ, $formId);
    $w['name'] = $name;
    $w['required'] = $pflicht ? 1 : 0;
    $w['description'] = $extra['beschreibung'] ?? '';
    $w['field_order'] = $reihe;
    $w['form_id'] = $formId;
    $w['field_options'] = array_merge($w['field_options'] ?? [], ['blank' => 'Bitte füllen Sie dieses Feld aus.', 'invalid' => 'Bitte prüfen Sie Ihre Eingabe.'], $extra);
    if ($optionenListe !== null) {
        $w['options'] = $optionenListe;
    }
    return (int)FrmField::create($w);
};
$vor    = $feld('text', 'Vorname', true, 1, ['classes' => 'frm_first frm_half']);
$nach   = $feld('text', 'Nachname', true, 2, ['classes' => 'frm_half']);
$tel    = $feld('phone', 'Telefon', true, 3, ['classes' => 'frm_first frm_half', 'invalid' => 'Bitte geben Sie eine gültige Telefonnummer ein.']);
$mail   = $feld('email', 'E-Mail', true, 4, ['classes' => 'frm_half', 'invalid' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.']);
$einl   = $feld('text', 'Einladende Person (Name)', false, 5);
$beg1   = $feld('text', 'Name der Begleitperson 1 (falls vorhanden)', false, 6, ['classes' => 'frm_first frm_half']);
$beg2   = $feld('text', 'Name der Begleitperson 2 (falls vorhanden)', false, 7, ['classes' => 'frm_half']);
$rechnen = $feld('html', 'Sicherheitsfrage', false, 8, ['beschreibung' => '@RECHNEN@']);
$dse    = $feld('checkbox', 'Datenschutzbelehrung', true, 9, ['blank' => 'Bitte stimmen Sie der Datenschutzbelehrung zu.'],
    ['Ich habe die <a href="/datenschutz/" target="_blank" rel="noopener">Datenschutzbelehrung</a> gelesen und bin mit der Verarbeitung meiner Angaben zur Anmeldung einverstanden.']);

$aktion = [
    'email_to' => 'info@spektrum-ev.de', 'cc' => '', 'bcc' => '',
    'reply_to' => '[' . $mail . ']',
    'from' => 'Website Spektrum <info@spektrum-ev.de>',
    'email_subject' => 'Anmeldung: Fastenbrechen 2027',
    'email_message' => "Neue Anmeldung zum Fastenbrechen 2027\n\n[default-message]",
    'event' => ['create'], 'conditions' => ['send_stop' => 'send', 'any_all' => 'any'],
];
wp_insert_post([
    'post_type' => 'frm_form_actions', 'post_status' => 'publish', 'post_excerpt' => 'email',
    'post_title' => 'Mail an die Geschäftsstelle', 'menu_order' => $formId,
    'post_content' => FrmAppHelper::prepare_and_encode($aktion),
]);
echo "Formular-ID: $formId\n";
