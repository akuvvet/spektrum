<?php
/**
 * Legt das Anmeldeformular "Alle in einem Boot" in Formidable Forms an.
 * Aufruf auf dem Server:  wp eval-file formidable_boot.php --user=1 --allow-root --path=<wordpress>
 */
global $wpdb;
$titel = 'Alle in einem Boot – Anmeldung';
foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}frm_forms WHERE name = %s", $titel)) as $alt) {
    FrmForm::destroy($alt);
}

$optionen = FrmFormsHelper::get_default_opts();
$optionen['submit_value'] = 'Verbindlich anmelden';
$optionen['success_action'] = 'message';
$optionen['success_msg'] = 'Vielen Dank für Ihre Anmeldung! Wir melden uns nur, falls es Rückfragen gibt. Wir freuen uns auf Sie am 22. November 2026 im Zentrum Frieden.';
$optionen['antispam'] = 1;
$formId = FrmForm::create([
    'form_key' => 'alle-in-einem-boot',
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
$name   = $feld('text', 'Vor- und Nachname', true, 1, ['classes' => 'frm_first frm_half']);
$mail   = $feld('email', 'E-Mail-Adresse', true, 2, ['classes' => 'frm_half', 'invalid' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.']);
$tel    = $feld('text', 'Telefonnummer (freiwillig)', false, 3, ['classes' => 'frm_first frm_half']);
$anzahl = $feld('select', 'Anzahl der Personen (mit Ihnen)', true, 4, ['classes' => 'frm_half'], array_merge([''], array_map('strval', range(1, 10))));
$hinweis = $feld('textarea', 'Anmerkungen (freiwillig)', false, 5, ['max' => 4, 'beschreibung' => 'Zum Beispiel die Namen weiterer Personen. Bitte keine Angaben zu Gesundheit oder Religion.']);
$rechnen = $feld('html', 'Sicherheitsfrage', false, 6, ['beschreibung' => '@RECHNEN@']);
$dse    = $feld('checkbox', 'Datenschutz', true, 7, ['blank' => 'Bitte stimmen Sie der Datenschutzerklärung zu.'],
    ['Ich habe die <a href="/datenschutz/" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und bin mit der Verarbeitung meiner Angaben zur Anmeldung einverstanden.']);

$aktion = [
    'email_to' => 'info@spektrum-ev.de', 'cc' => '', 'bcc' => '',
    'reply_to' => '[' . $mail . ']',
    'from' => 'Website Spektrum <info@spektrum-ev.de>',
    'email_subject' => 'Anmeldung: Alle in einem Boot – Begegnungsfest am 22.11.2026',
    'email_message' => "Neue Anmeldung zum Begegnungsfest „Die Arche Noahs und die Aschure“\n\n[default-message]",
    'event' => ['create'], 'conditions' => ['send_stop' => 'send', 'any_all' => 'any'],
];
wp_insert_post([
    'post_type' => 'frm_form_actions', 'post_status' => 'publish', 'post_excerpt' => 'email',
    'post_title' => 'Mail an die Geschäftsstelle', 'menu_order' => $formId,
    'post_content' => FrmAppHelper::prepare_and_encode($aktion),
]);
echo "Formular-ID: $formId\nFelder: $name, $mail, $tel, $anzahl, $hinweis, $dse\n";
