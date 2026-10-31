<?php
/**
 * Spektrum e.V. — Theme-Funktionen.
 * Die Seiten liegen als fertiges HTML in content/ und werden bei der
 * Aktivierung als WordPress-Seiten angelegt.
 */

const SPEKTRUM_START = 'start';

function spektrum_meta(): array
{
    static $m = null;
    if ($m === null) {
        $m = json_decode((string)file_get_contents(__DIR__ . '/content/_meta.json'), true) ?: [];
    }
    return $m;
}

function spektrum_ersetzen(string $html): string
{
    return str_replace('@THEME@', get_template_directory_uri(), $html);
}

/** Seiten sind fertiges HTML: keine Automatik, die Absaetze, Anfuehrungszeichen oder Zeilen veraendert. */
add_action('init', function () {
    remove_filter('the_content', 'wpautop');
    remove_filter('the_content', 'wptexturize');
    remove_filter('the_content', 'convert_chars');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
});
add_filter('the_content', 'spektrum_ersetzen', 99);

/**
 * Sicherheitsfrage pruefen: Loesung muss zur signierten Aufgabe passen, frisch (3 Sekunden bis 2 Stunden)
 * und noch nie benutzt sein. $verbrauchen = true macht die Aufgabe danach ungueltig.
 */
function spektrum_rechenfrage_ok(string $antwort, string $token, bool $verbrauchen): bool
{
    $antwort = trim($antwort);
    [$zeit, $siegel] = array_pad(explode('.', $token, 2), 2, '');
    $zeit = (int)$zeit;
    $marke = 'spk_t_' . substr($siegel, 0, 24);
    if (!ctype_digit($antwort) || $zeit < time() - 2 * HOUR_IN_SECONDS || $zeit > time() - 3
        || !hash_equals(hash_hmac('sha256', (int)$antwort . '|' . $zeit, wp_salt('nonce')), $siegel)
        || get_transient($marke) !== false) {
        return false;
    }
    if ($verbrauchen) {
        set_transient($marke, 1, 2 * HOUR_IN_SECONDS);
    }
    return true;
}

/** Sicherheitsfrage fuer das Kontaktformular: Aufgabe und signierte Loesung, ohne Dienst von Dritten. */
function spektrum_rechenfrage(string $html): string
{
    if (!str_contains($html, '@RECHNEN@')) {
        return $html;
    }
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $zeit = time();
    $token = $zeit . '.' . hash_hmac('sha256', ($a + $b) . '|' . $zeit, wp_salt('nonce'));
    $feld = '<p class="feld"><label for="rechnen">Sicherheitsfrage: Was ergibt ' . $a . ' + ' . $b . '? <span class="pflicht">*</span></label>'
        . '<input type="text" id="rechnen" name="rechnen" inputmode="numeric" pattern="[0-9]*" maxlength="3" required autocomplete="off">'
        . '<input type="hidden" name="rtoken" value="' . esc_attr($token) . '"></p>';
    return str_replace('@RECHNEN@', $feld, $html);
}
add_filter('the_content', 'spektrum_rechenfrage', 99);

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('editor-styles');
    add_editor_style('assets/styles.css');
});

add_filter('pre_get_document_title', function ($titel) {
    $m = spektrum_meta();
    $slug = is_front_page() ? SPEKTRUM_START : (is_404() ? '404' : get_post_field('post_name', get_queried_object_id()));
    return $m[$slug]['titel'] ?? $titel;
});

add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
    wp_enqueue_style('spektrum', get_template_directory_uri() . '/assets/styles.css', [], filemtime(__DIR__ . '/assets/styles.css'));
    wp_enqueue_script('spektrum-vorab', get_template_directory_uri() . '/assets/vorab.js', [], filemtime(__DIR__ . '/assets/vorab.js'), false);
    wp_enqueue_script('spektrum', get_template_directory_uri() . '/assets/main.js', [], filemtime(__DIR__ . '/assets/main.js'), ['strategy' => 'defer', 'in_footer' => true]);
});

add_action('wp_head', function () {
    $m = spektrum_meta();
    $slug = is_front_page() ? SPEKTRUM_START : (is_404() ? '404' : get_post_field('post_name', get_queried_object_id()));
    $e = $m[$slug] ?? null;
    echo '<meta name="theme-color" content="#101018">' . "\n";
    echo '<link rel="icon" href="' . esc_url(get_template_directory_uri()) . '/favicon.ico" sizes="any">' . "\n";
    echo '<link rel="icon" href="' . esc_url(get_template_directory_uri()) . '/assets/logo.svg" type="image/svg+xml">' . "\n";
    if ($e) {
        if ($e['beschreibung']) {
            echo '<meta name="description" content="' . esc_attr($e['beschreibung']) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($e['beschreibung']) . '">' . "\n";
        }
        echo '<meta property="og:title" content="' . esc_attr($e['titel']) . '">' . "\n";
        if ($e['jsonld']) {
            echo '<script type="application/ld+json">' . $e['jsonld'] . '</script>' . "\n";
        }
    }
}, 1);

add_filter('wp_robots', function ($r) {
    $m = spektrum_meta();
    $slug = is_404() ? '404' : (is_front_page() ? SPEKTRUM_START : get_post_field('post_name', get_queried_object_id()));
    if (!empty($m[$slug]['noindex'])) {
        $r['noindex'] = true;
    }
    return $r;
});

/** Benutzernamen nicht preisgeben: keine Autorenseiten, nicht in der Sitemap, keine Versionsangabe. */
add_action('template_redirect', function () {
    if (is_author() || (!is_admin() && isset($_GET['author']))) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 1);
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return $name === 'users' ? false : $provider;
}, 10, 2);
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wp_print_auto_sizes_contain_css_fix', 1);
remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
add_filter('wp_speculation_rules_configuration', '__return_null');
remove_action('wp_footer', 'wp_print_speculation_rules');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

/** Kopf/Fuss aus der Vorlage, mit markiertem aktuellem Menuepunkt. */
function spektrum_teil(string $name): void
{
    $html = spektrum_ersetzen((string)file_get_contents(__DIR__ . '/content/' . $name . '.html'));
    if ($name === '_kopf') {
        $slug = is_front_page() ? '/' : '/' . get_post_field('post_name', get_queried_object_id()) . '/';
        $html = preg_replace('~(<a href="' . preg_quote($slug, '~') . '")~', '$1 aria-current="page"', $html, 1);
    }
    echo $html;
}

/** Bei Aktivierung: Seiten anlegen, Startseite und Permalinks setzen. */
add_action('after_switch_theme', function () {
    foreach (spektrum_meta() as $slug => $e) {
        $slug = (string)$slug;
        if ($slug === '404') {
            continue;
        }
        if (get_page_by_path($slug)) {
            continue;
        }
        $id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_name'    => $slug,
            'post_parent'  => !empty($e['parent']) ? (int)(get_page_by_path($e['parent'])->ID ?? 0) : 0,
            'post_title'   => $e['ueberschrift'],
            'post_content' => spektrum_ersetzen((string)file_get_contents(__DIR__ . '/content/' . $slug . '.html')),
        ]);
        if ($slug === SPEKTRUM_START && $id && !is_wp_error($id)) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $id);
        }
    }
    update_option('permalink_structure', '/%postname%/');
    update_option('blogname', 'Spektrum Bildungszentrum Solingen e.V.');
    flush_rewrite_rules();
});

/** Kontaktformular: Mail ueber den SMTP-Zugang aus ../mail-zugang.php (ausserhalb von httpdocs). */
const SPEKTRUM_EMPFAENGER = 'info@spektrum-ev.de';
const SPEKTRUM_ABSENDER   = 'info@spektrum-ev.de';

function spektrum_kontakt_senden(): void
{
    $weiter = static function (string $ziel): void {
        wp_safe_redirect(home_url($ziel), 303);
        exit;
    };
    $sauber = static function (string $wert, int $max, bool $mehrzeilig = false): string {
        $wert = wp_unslash($wert);
        $wert = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $wert) ?? '';
        if (!$mehrzeilig) {
            $wert = str_replace(["\r", "\n"], ' ', $wert);
        }
        return mb_substr(trim($wert), 0, $max);
    };

    // Honigtopf und Zeitfalle: Bots bekommen Erfolg vorgespielt.
    if (trim((string)($_POST['webseite'] ?? '')) !== '') {
        $weiter('/danke/');
    }
    $start = (int)($_POST['ts'] ?? 0);
    if ($start > 0 && (time() - $start) < 3) {
        $weiter('/danke/');
    }

    if (!spektrum_rechenfrage_ok((string)($_POST['rechnen'] ?? ''), (string)($_POST['rtoken'] ?? ''), true)) {
        $weiter('/kontakt/?fehler=4');
    }

    $name      = $sauber((string)($_POST['name'] ?? ''), 80);
    $email     = $sauber((string)($_POST['email'] ?? ''), 120);
    $betreff   = $sauber((string)($_POST['betreff'] ?? ''), 120);
    $nachricht = $sauber((string)($_POST['nachricht'] ?? ''), 3000, true);

    if (($_POST['datenschutz'] ?? '') !== 'ja' || $name === '' || $nachricht === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $weiter('/kontakt/?fehler=1');
    }

    // Nachrichten voller Links sind fast immer Werbung.
    if (preg_match_all('~https?://|www\.~i', $nachricht . ' ' . $betreff) > 2) {
        $weiter('/kontakt/?fehler=1');
    }

    // Bremse je Besucher (nicht rueckrechenbarer Pruefwert, eine Stunde) und insgesamt je Stunde.
    $jetzt = time();
    $bremse = static function (string $schluessel, int $max) use ($jetzt): bool {
        $zeiten = array_values(array_filter((array)get_transient($schluessel), static fn($z) => (int)$z > $jetzt - HOUR_IN_SECONDS));
        if (count($zeiten) >= $max) {
            return false;
        }
        $zeiten[] = $jetzt;
        set_transient($schluessel, $zeiten, HOUR_IN_SECONDS);
        return true;
    };
    $ip = 'spk_ip_' . substr(hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt()), 0, 32);
    if (!$bremse($ip, 5) || !$bremse('spk_alle', 30)) {
        $weiter('/kontakt/?fehler=2');
    }

    $titel  = 'Kontaktanfrage über die Website';
    $koerper = $titel . "\n" . str_repeat('=', mb_strlen($titel)) . "\n\n"
        . 'Eingegangen: ' . wp_date('d.m.Y H:i') . "\n\n"
        . str_pad('Name:', 32) . $name . "\n"
        . str_pad('E-Mail:', 32) . $email . "\n"
        . ($betreff !== '' ? str_pad('Betreff:', 32) . $betreff . "\n" : '')
        . str_pad('Nachricht:', 32) . $nachricht . "\n"
        . "\n" . str_repeat('-', 60) . "\nGesendet über das Formular auf spektrum-ev.de\n";

    if (!defined('SPEKTRUM_VERSAND')) {
        define('SPEKTRUM_VERSAND', true);
    }
    require_once __DIR__ . '/versand.php';
    [$ok, $weg, $meldung] = versenden(SPEKTRUM_EMPFAENGER, SPEKTRUM_ABSENDER, $titel, $koerper, $email);

    // Protokoll ohne Namen, Adressen und Inhalte: nur, ob der Server die Mail angenommen hat.
    $notiz = wp_date('d.m.Y H:i') . ' kontakt ' . $weg . ' ' . ($ok ? 'ok' : 'FEHLER');
    if (!$ok) {
        $notiz .= ' (' . mb_substr(str_replace(["\r", "\n"], ' ', $meldung), 0, 200) . ')';
    }
    @file_put_contents(dirname(rtrim(ABSPATH, '/')) . '/formular-protokoll.log', $notiz . "\n", FILE_APPEND | LOCK_EX);

    $weiter($ok ? '/danke/' : '/kontakt/?fehler=3');
}
add_action('admin_post_nopriv_spektrum_kontakt', 'spektrum_kontakt_senden');
add_action('admin_post_spektrum_kontakt', 'spektrum_kontakt_senden');

/** Mails (Formulare, WordPress) ueber den STRATO-Postausgang, sonst werden sie wegen DMARC abgelehnt. */
add_action('phpmailer_init', function ($mail) {
    if (defined('WPMS_PLUGIN_VER')) {   // WP Mail SMTP uebernimmt den Versand
        return;
    }
    if (!defined('SPEKTRUM_VERSAND')) {
        define('SPEKTRUM_VERSAND', true);
    }
    require_once __DIR__ . '/versand.php';
    $z = zugang_lesen();
    if ($z === []) {
        return;
    }
    $mail->isSMTP();
    $mail->Host = (string)$z['host'];
    $mail->Port = (int)$z['port'];
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = ((string)$z['sicher'] === 'ssl') ? 'ssl' : 'tls';
    $mail->Username = (string)$z['benutzer'];
    $mail->Password = (string)$z['passwort'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string)$z['absender'], (string)$z['name'], false);
    $mail->Sender = (string)$z['absender'];
});
add_filter('wp_mail_from', fn() => SPEKTRUM_ABSENDER);
add_filter('wp_mail_from_name', fn() => 'Website Spektrum');

/** Formidable Forms: Formulare mit Sicherheitsfrage (HTML-Feld mit @RECHNEN@) pruefen; verbraucht wird die Aufgabe erst nach dem Speichern. */
function spektrum_formidable_hat_rechenfrage(int $formId): bool
{
    foreach (FrmField::get_all_for_form($formId) as $feld) {
        if ($feld->type === 'html' && str_contains((string)$feld->description, '@RECHNEN@')) {
            return true;
        }
    }
    return false;
}
add_filter('frm_validate_entry', function ($fehler, $werte) {
    if (!spektrum_formidable_hat_rechenfrage((int)($werte['form_id'] ?? 0))) {
        return $fehler;
    }
    if (!spektrum_rechenfrage_ok((string)($_POST['rechnen'] ?? ''), (string)($_POST['rtoken'] ?? ''), false)) {
        $fehler['rechnen'] = 'Die Sicherheitsfrage wurde falsch beantwortet oder ist abgelaufen. Bitte versuchen Sie es noch einmal.';
    }
    return $fehler;
}, 10, 2);
add_action('frm_after_create_entry', function ($eintrag, $formId) {
    if (!spektrum_formidable_hat_rechenfrage((int)$formId)) {
        return;
    }
    $siegel = explode('.', (string)($_POST['rtoken'] ?? ''), 2)[1] ?? '';
    if ($siegel !== '') {
        set_transient('spk_t_' . substr($siegel, 0, 24), 1, 2 * HOUR_IN_SECONDS);
    }
}, 10, 2);

/** Datenschutz: Eintraege der Formulare werden nach 180 Tagen automatisch geloescht (taeglich). */
add_action('init', function () {
    if (!wp_next_scheduled('spektrum_eintraege_loeschen')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'spektrum_eintraege_loeschen');
    }
});
add_action('spektrum_eintraege_loeschen', function () {
    if (!class_exists('FrmEntry')) {
        return;
    }
    global $wpdb;
    $alt = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}frm_items WHERE created_at < %s",
        gmdate('Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS)
    ));
    foreach ($alt as $id) {
        FrmEntry::destroy((int)$id);
    }
});
