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

    $name      = $sauber((string)($_POST['name'] ?? ''), 80);
    $email     = $sauber((string)($_POST['email'] ?? ''), 120);
    $betreff   = $sauber((string)($_POST['betreff'] ?? ''), 120);
    $nachricht = $sauber((string)($_POST['nachricht'] ?? ''), 3000, true);

    if (($_POST['datenschutz'] ?? '') !== 'ja' || $name === '' || $nachricht === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $weiter('/kontakt/?fehler=1');
    }

    $titel  = 'Kontaktanfrage über die Website';
    $koerper = $titel . "\n" . str_repeat('=', mb_strlen($titel)) . "\n\n"
        . 'Eingegangen: ' . wp_date('d.m.Y H:i') . "\n\n"
        . str_pad('Name:', 32) . $name . "\n"
        . str_pad('E-Mail:', 32) . $email . "\n"
        . ($betreff !== '' ? str_pad('Betreff:', 32) . $betreff . "\n" : '')
        . str_pad('Nachricht:', 32) . $nachricht . "\n"
        . "\n" . str_repeat('-', 60) . "\nGesendet über das Formular auf spektrum-ev.de\n";

    define('SPEKTRUM_VERSAND', true);
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
