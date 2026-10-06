<?php
namespace HoseinMomeni\MahexWoo\Update;

use stdClass;
use WP_Error;

final class GitHubUpdater {
    private const SLUG = 'mahex-shipping-for-woocommerce';
    private const REPOSITORY = 'anonyset/mahex-shipping-for-woocommerce';
    private const MANIFEST_URL = 'https://raw.githubusercontent.com/anonyset/mahex-shipping-for-woocommerce/dist/update.json';
    private const REPOSITORY_URL = 'https://github.com/anonyset/mahex-shipping-for-woocommerce';
    private const DOCS_URL = 'https://anonyset.github.io/mahex-shipping-for-woocommerce/';
    private const CACHE_KEY = 'hm_mahex_github_update_manifest_v1';
    private const CACHE_TTL = 21600; // 6 hours.

    public static function register(): void {
        add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'filterUpdateTransient' ) );
        add_filter( 'plugins_api', array( self::class, 'filterPluginInformation' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( self::class, 'normalizeUpgradeSource' ), 10, 4 );
        add_filter( 'plugin_row_meta', array( self::class, 'pluginRowMeta' ), 10, 2 );
        add_action( 'upgrader_process_complete', array( self::class, 'afterUpgrade' ), 10, 2 );
    }

    public static function filterUpdateTransient( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        $manifest = self::manifest();
        if ( null === $manifest ) {
            return $transient;
        }

        $plugin = plugin_basename( HM_MAHEX_FILE );
        $item   = self::updateObject( $manifest, $plugin );

        if ( version_compare( (string) $manifest['version'], HM_MAHEX_VERSION, '>' ) ) {
            if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                $transient->response = array();
            }
            $transient->response[ $plugin ] = $item;
            if ( isset( $transient->no_update[ $plugin ] ) ) {
                unset( $transient->no_update[ $plugin ] );
            }
        } else {
            if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
                $transient->no_update = array();
            }
            $transient->no_update[ $plugin ] = $item;
        }

        return $transient;
    }

    public static function filterPluginInformation( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
            return $result;
        }

        $manifest = self::manifest();
        if ( null === $manifest ) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = (string) $manifest['name'];
        $info->slug          = self::SLUG;
        $info->version       = (string) $manifest['version'];
        $info->author        = '<a href="https://postyekrooz.ir/plugins">Hosein Momeni</a>';
        $info->homepage      = self::safeUrl( $manifest['homepage'] ?? self::REPOSITORY_URL, self::REPOSITORY_URL );
        $info->download_link = self::safePackageUrl( $manifest['download_url'] ?? '' );
        $info->requires      = (string) ( $manifest['requires'] ?? '7.1' );
        $info->tested        = (string) ( $manifest['tested'] ?? '7.1' );
        $info->requires_php  = (string) ( $manifest['requires_php'] ?? '8.1' );
        $info->last_updated  = (string) ( $manifest['last_updated'] ?? '' );
        $info->sections      = is_array( $manifest['sections'] ?? null ) ? $manifest['sections'] : array();
        $info->banners       = is_array( $manifest['banners'] ?? null ) ? $manifest['banners'] : array();
        $info->icons         = is_array( $manifest['icons'] ?? null ) ? $manifest['icons'] : array();

        return $info;
    }

    public static function normalizeUpgradeSource( $source, string $remoteSource, $upgrader, array $hookExtra ) {
        if ( is_wp_error( $source ) || empty( $hookExtra['plugin'] ) || plugin_basename( HM_MAHEX_FILE ) !== $hookExtra['plugin'] ) {
            return $source;
        }

        $sourcePath = untrailingslashit( (string) $source );
        if ( self::SLUG === basename( $sourcePath ) ) {
            return $source;
        }

        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            return new WP_Error( 'hm_mahex_updater_fs', __( 'سیستم فایل وردپرس برای آماده‌سازی بروزرسانی ماهکس در دسترس نیست.', 'mahex-shipping-for-woocommerce' ) );
        }

        $target = trailingslashit( $remoteSource ) . self::SLUG;
        if ( $wp_filesystem->exists( $target ) ) {
            $wp_filesystem->delete( $target, true );
        }

        if ( ! $wp_filesystem->move( $sourcePath, $target, true ) ) {
            return new WP_Error( 'hm_mahex_updater_move', __( 'پوشه بسته بروزرسانی ماهکس قابل آماده‌سازی نبود.', 'mahex-shipping-for-woocommerce' ) );
        }

        return trailingslashit( $target );
    }

    public static function pluginRowMeta( array $links, string $file ): array {
        if ( plugin_basename( HM_MAHEX_FILE ) !== $file ) {
            return $links;
        }

        $links[] = '<a href="' . esc_url( self::REPOSITORY_URL ) . '" target="_blank" rel="noopener noreferrer">GitHub</a>';
        $links[] = '<a href="' . esc_url( self::DOCS_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'مستندات', 'mahex-shipping-for-woocommerce' ) . '</a>';
        return $links;
    }

    public static function afterUpgrade( $upgrader, array $options ): void {
        if ( 'plugin' !== ( $options['type'] ?? '' ) ) {
            return;
        }
        $plugins = array_map( 'plugin_basename', (array) ( $options['plugins'] ?? array() ) );
        if ( in_array( plugin_basename( HM_MAHEX_FILE ), $plugins, true ) ) {
            delete_site_transient( self::CACHE_KEY );
            delete_site_transient( 'update_plugins' );
        }
    }

    private static function updateObject( array $manifest, string $plugin ): stdClass {
        $item = new stdClass();
        $item->id           = self::REPOSITORY_URL;
        $item->slug         = self::SLUG;
        $item->plugin       = $plugin;
        $item->new_version  = (string) $manifest['version'];
        $item->url          = self::safeUrl( $manifest['homepage'] ?? self::REPOSITORY_URL, self::REPOSITORY_URL );
        $item->package      = self::safePackageUrl( $manifest['download_url'] ?? '' );
        $item->requires     = (string) ( $manifest['requires'] ?? '7.1' );
        $item->tested       = (string) ( $manifest['tested'] ?? '7.1' );
        $item->requires_php = (string) ( $manifest['requires_php'] ?? '8.1' );
        $item->icons        = is_array( $manifest['icons'] ?? null ) ? $manifest['icons'] : array();
        $item->banners      = is_array( $manifest['banners'] ?? null ) ? $manifest['banners'] : array();
        return $item;
    }

    private static function manifest(): ?array {
        if ( false === apply_filters( 'hm_mahex_github_updates_enabled', true ) ) {
            return null;
        }

        $cached = get_site_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && ! empty( $cached['_failed'] ) ) {
            return null;
        }
        if ( self::validManifest( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            self::MANIFEST_URL,
            array(
                'timeout'     => 8,
                'redirection' => 2,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'User-Agent' => 'Mahex-WooCommerce-Updater/' . HM_MAHEX_VERSION . '; ' . home_url( '/' ),
                ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_site_transient( self::CACHE_KEY, array( '_failed' => true ), 30 * MINUTE_IN_SECONDS );
            return null;
        }

        $decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! self::validManifest( $decoded ) ) {
            set_site_transient( self::CACHE_KEY, array( '_failed' => true ), 30 * MINUTE_IN_SECONDS );
            return null;
        }

        $decoded['download_url'] = self::safePackageUrl( $decoded['download_url'] );
        if ( '' === $decoded['download_url'] ) {
            set_site_transient( self::CACHE_KEY, array( '_failed' => true ), 30 * MINUTE_IN_SECONDS );
            return null;
        }

        set_site_transient( self::CACHE_KEY, $decoded, self::CACHE_TTL );
        return $decoded;
    }

    private static function validManifest( $manifest ): bool {
        if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
            return false;
        }
        return (bool) preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $manifest['version'] );
    }

    private static function safePackageUrl( string $url ): string {
        $url  = esc_url_raw( $url );
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( ! in_array( $host, array( 'github.com', 'codeload.github.com', 'raw.githubusercontent.com' ), true ) ) {
            return '';
        }
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        if ( false === strpos( $path, '/anonyset/mahex-shipping-for-woocommerce/' ) ) {
            return '';
        }
        return $url;
    }

    private static function safeUrl( string $url, string $fallback ): string {
        $url = esc_url_raw( $url );
        return '' !== $url ? $url : $fallback;
    }
}
