<?php
namespace Y2KWebs\Lafise;

/** Regional WordPress translations take precedence; every Spanish locale has a bundled fallback. */
final class Languages
{
    private const DOMAIN = 'y2kwebs-lafise';
    private static array $catalogs = [];

    public static function register(): void
    {
        add_filter('gettext_y2kwebs-lafise', [self::class, 'translate'], 10, 3);
    }

    public static function locale(): string
    {
        // Checkout AJAX is served through wp-admin, but must use the store language, not the buyer's admin preference.
        if (is_locale_switched() || doing_action('wp_ajax_y2k_lafise_key') || doing_action('wp_ajax_nopriv_y2k_lafise_key')) {
            return get_locale();
        }
        return determine_locale();
    }

    public static function translate(string $translation, string $text, string $domain): string
    {
        if ($domain !== self::DOMAIN) { return $translation; }
        $locale = self::locale();
        // Core can cache an absent domain before a locale switch. Also select the store catalogue for checkout AJAX.
        $regional = preg_match('/^[A-Za-z0-9_-]+$/D', $locale)
            ? self::catalog(WP_LANG_DIR . '/plugins/' . self::DOMAIN . '-' . $locale . '.mo') : null;
        if ($locale !== determine_locale() || $translation === $text) {
            $translation = $regional ? $regional->translate($text) : $text;
        }
        if ($translation !== $text || !preg_match('/^es(?:[_-]|$)/i', $locale)) {
            return $translation;
        }
        $spanish = self::catalog(dirname(Y2K_LAFISE_FILE) . '/languages/' . self::DOMAIN . '-es_ES.mo');
        return $spanish ? $spanish->translate($text) : $translation;
    }

    private static function catalog(string $file): ?\MO
    {
        if (!array_key_exists($file, self::$catalogs)) {
            require_once ABSPATH . WPINC . '/pomo/mo.php';
            $catalog = new \MO();
            self::$catalogs[$file] = is_readable($file) && $catalog->import_from_file($file) ? $catalog : null;
        }
        return self::$catalogs[$file];
    }

    public static function verification(): array
    {
        return [
            'authenticationIncomplete' => __('Authentication was not completed. You can try the payment again.', 'y2kwebs-lafise'),
            'declined' => __('The bank has declined the payment.', 'y2kwebs-lafise'),
            'pending' => __('The payment is pending review. Contact the store before paying again.', 'y2kwebs-lafise'),
            'retry' => __('Try the payment again', 'y2kwebs-lafise'),
            'challenge' => __('Complete verification with your bank.', 'y2kwebs-lafise'),
            'checking' => __('We are verifying the payment…', 'y2kwebs-lafise'),
            'unavailable' => __('We cannot check the status. Do not repeat the payment; we are checking it.', 'y2kwebs-lafise'),
        ];
    }
}
