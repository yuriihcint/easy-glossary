<?php
/*
Plugin Name: Easy Glossary
Description: Full-featured glossary plugin with tooltips, auto-linking, index shortcode, and settings.
Version: 1.1
Author: GrayStudio, LLC
Author URI: https://graystud.io
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------
 * CONSTANTS (unique)
 * ------------------------------------------------------------ */
define('GSEASY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSEASY_PLUGIN_URL', plugin_dir_url(__FILE__));

/* ------------------------------------------------------------
 * REGISTER QUERY VARS FOR FILTERING
 * ------------------------------------------------------------ */
add_filter('query_vars', function($vars) {
    $vars[] = 'gseasy_letter';
    $vars[] = 'gseasy_search';
    return $vars;
});

/* ------------------------------------------------------------
 * SETTINGS PAGE
 * ------------------------------------------------------------ */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=gseasy_glossary',
        'Glossary Settings',
        'Settings',
        'manage_options',
        'gseasy-settings',
        'gseasy_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('gseasy_settings_group', 'gseasy_auto_link',      ['sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('gseasy_settings_group', 'gseasy_tooltip_enable', ['sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('gseasy_settings_group', 'gseasy_tooltip_style',  ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('gseasy_settings_group', 'gseasy_index_layout',   ['sanitize_callback' => 'gseasy_sanitize_index_layout']);
    register_setting('gseasy_settings_group', 'gseasy_custom_html',    ['sanitize_callback' => 'wp_kses_post']);
    register_setting('gseasy_settings_group', 'gseasy_permalink_slug', ['sanitize_callback' => 'sanitize_title']);
    register_setting('gseasy_settings_group', 'gseasy_enable_archive', ['sanitize_callback' => 'rest_sanitize_boolean']);
});

function gseasy_sanitize_index_layout($value) {
    $value = sanitize_key((string) $value);
    return in_array($value, ['list', 'grid'], true) ? $value : 'list';
}

function gseasy_get_index_layout() {
    $layout = gseasy_sanitize_index_layout(get_option('gseasy_index_layout', 'list'));

    // Self-heal invalid/stale values so subsequent requests are consistent.
    if (get_option('gseasy_index_layout', 'list') !== $layout) {
        update_option('gseasy_index_layout', $layout);
    }

    return $layout;
}

function gseasy_render_settings_page() { ?>
    <div class="wrap">
        <h1>Glossary Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('gseasy_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th>Auto-link Terms</th>
                    <td>
                        <input type="checkbox" name="gseasy_auto_link" value="1" <?php checked(get_option('gseasy_auto_link'), 1); ?> />
                        <label>Enable auto-linking of glossary terms in posts/pages</label>
                    </td>
                </tr>
                <tr>
                    <th>Enable Tooltip</th>
                    <td>
                        <input type="checkbox" name="gseasy_tooltip_enable" value="1" <?php checked(get_option('gseasy_tooltip_enable'), 1); ?> />
                        <label>Enable tooltip preview on glossary terms</label>
                    </td>
                </tr>
                <tr>
                    <th>Tooltip Style</th>
                    <td>
                        <?php $style = get_option('gseasy_tooltip_style', 'light'); ?>
                        <select name="gseasy_tooltip_style">
                            <option value="light"   <?php selected($style, 'light'); ?>>Light</option>
                            <option value="dark"    <?php selected($style, 'dark'); ?>>Dark</option>
                            <option value="minimal" <?php selected($style, 'minimal'); ?>>Minimal</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Index Layout</th>
                    <td>
                        <?php $layout = gseasy_get_index_layout(); ?>
                        <select name="gseasy_index_layout">
                            <option value="list" <?php selected($layout, 'list'); ?>>List</option>
                            <option value="grid" <?php selected($layout, 'grid'); ?>>Grid</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Custom HTML</th>
                    <td>
                        <textarea name="gseasy_custom_html" rows="6" class="large-text"><?php echo esc_textarea(get_option('gseasy_custom_html', '')); ?></textarea>
                        <p class="description">HTML added at the end of each glossary term page.</p>
                    </td>
                </tr>
                <tr>
                    <th>Permalink Slug</th>
                    <td>
                        <input type="text" name="gseasy_permalink_slug" value="<?php echo esc_attr(get_option('gseasy_permalink_slug', 'glossary')); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Enable Archive Page</th>
                    <td>
                        <input type="checkbox" name="gseasy_enable_archive" value="1" <?php checked(get_option('gseasy_enable_archive'), 1); ?> />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php }

/* ------------------------------------------------------------
 * REGISTER CPT
 * ------------------------------------------------------------ */
add_action('init', function () {
    $slug        = get_option('gseasy_permalink_slug', 'glossary');
    $has_archive = (bool) get_option('gseasy_enable_archive', 0);

    // Flush rewrite rules on slug change to ensure it works
    if (get_option('gseasy_permalink_slug_old') !== $slug) {
        flush_rewrite_rules();
        update_option('gseasy_permalink_slug_old', $slug);
    }

    register_post_type('gseasy_glossary', [
        'label'        => 'Glossary',
        'public'       => true,
        'has_archive'  => $has_archive,
        'rewrite'      => ['slug' => $slug, 'with_front' => false],
        'supports'     => ['title', 'editor', 'excerpt'],
        'show_in_rest' => true,
    ]);
});

/* ------------------------------------------------------------
 * GLOSSARY ITEM SCHEMA (PER-TERM)
 * ------------------------------------------------------------ */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'gseasy-term-schema',
        'Schema (JSON-LD)',
        'gseasy_render_schema_meta_box',
        'gseasy_glossary',
        'normal',
        'default'
    );
});

function gseasy_render_schema_meta_box($post) {
    wp_nonce_field('gseasy_save_term_schema', 'gseasy_term_schema_nonce');
    $schema = get_post_meta($post->ID, '_gseasy_term_schema', true);
    ?>
    <p>
        <label for="gseasy-term-schema-field">Enter custom JSON-LD schema for this glossary term. It will be printed in the page header.</label>
    </p>
    <textarea
        id="gseasy-term-schema-field"
        name="gseasy_term_schema"
        class="widefat"
        rows="10"
        placeholder='{ "@context": "https://schema.org", "@type": "DefinedTerm", "name": "Example" }'
    ><?php echo esc_textarea($schema); ?></textarea>
    <?php
}

add_action('save_post_gseasy_glossary', function ($post_id) {
    if (!isset($_POST['gseasy_term_schema_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['gseasy_term_schema_nonce']));
    if (!wp_verify_nonce($nonce, 'gseasy_save_term_schema')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $schema = isset($_POST['gseasy_term_schema'])
        ? trim(wp_unslash($_POST['gseasy_term_schema']))
        : '';

    if ($schema === '') {
        delete_post_meta($post_id, '_gseasy_term_schema');
        return;
    }

    update_post_meta($post_id, '_gseasy_term_schema', $schema);
});

add_action('wp_head', function () {
    if (!is_singular('gseasy_glossary')) {
        return;
    }

    $schema = get_post_meta(get_queried_object_id(), '_gseasy_term_schema', true);
    if (!is_string($schema) || trim($schema) === '') {
        return;
    }

    echo "\n<script type=\"application/ld+json\">\n";
    echo trim($schema); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "\n</script>\n";
}, 20);

/* ------------------------------------------------------------
 * SINGLE TERM: TITLE + CUSTOM HTML
 * ------------------------------------------------------------ */
add_filter('the_content', function ($content) {
    if (!is_singular('gseasy_glossary')) {
        return $content;
    }

    // Defaults
    $search_query   = '';
    $current_letter = '';

    // Nonce validation
    if (
        isset($_GET['gseasy_nonce']) &&
        wp_verify_nonce(
            sanitize_text_field(wp_unslash($_GET['gseasy_nonce'])),
            'gseasy_search_action'
        )
    ) {
        if (isset($_GET['gseasy_search'])) {
            $search_query = sanitize_text_field(wp_unslash($_GET['gseasy_search']));
        }
        if (isset($_GET['gseasy_letter'])) {
            $current_letter = strtoupper(
                sanitize_text_field(wp_unslash($_GET['gseasy_letter']))
            );
        }
    }

    // Base URL for links = archive (or fallback to custom slug)
    $archive_link = get_post_type_archive_link('gseasy_glossary');
    $slug         = sanitize_title(get_option('gseasy_permalink_slug', 'glossary'));
    $base_url     = $archive_link ? $archive_link : trailingslashit(home_url('/' . $slug . '/'));

    // Build Alphabet Filter
    $alphabet_links = [];

    // "All" link (clear letter, keep search)
    $all_url = add_query_arg(
        array_filter([
            'gseasy_search' => ($search_query !== '' ? $search_query : null),
        ]),
        $base_url
    );
    $alphabet_links[] = '<a href="' . esc_url($all_url) . '" class="gseasy-filter-all' . ($current_letter === '' ? ' active' : '') . '">All</a>';

    foreach (range('A', 'Z') as $letter) {
        $url = add_query_arg(
            array_filter([
                'gseasy_letter' => $letter,
                'gseasy_search' => ($search_query !== '' ? $search_query : null),
            ]),
            $base_url
        );
        $class = ($letter === $current_letter) ? 'active' : '';
        $alphabet_links[] = '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($letter) . '</a>';
    }

    $alphabet_html = '<div class="gseasy-alphabet-filter">' . implode('', $alphabet_links) . '</div>';

    // Build Search Form (GET) – keep current letter and include nonce
    $form_action = $base_url;
    $nonce       = wp_create_nonce('gseasy_search_action');
    $search_html  = '<form class="gseasy-search-form" action="' . esc_url($form_action) . '" method="get">' .
        '<input type="hidden" name="gseasy_nonce" value="' . esc_attr($nonce) . '">';
    if ($current_letter !== '') {
        $search_html .= '<input type="hidden" name="gseasy_letter" value="' . esc_attr($current_letter) . '">';
    }
    $search_html .= '<input type="text" name="gseasy_search" placeholder="Search terms..." value="' . esc_attr($search_query) . '">';
    $search_html .= '<button type="submit">Search</button>';
    $search_html .= '</form>';

    // Title + Custom HTML (if any)
    $title       = '<h1 class="gseasy-term-title">' . esc_html(get_the_title()) . '</h1>';
    $custom      = get_option('gseasy_custom_html', '');
    $custom_html = $custom ? '<div class="gseasy-custom-html">' . wp_kses_post($custom) . '</div>' : '';

    // Assemble final output
    return $alphabet_html . $search_html . $title . $content . $custom_html;
}, 10);

/* ------------------------------------------------------------
 * AUTO-LINK TERMS
 * ------------------------------------------------------------ */
add_filter('the_content', 'gseasy_auto_link_terms', 9);

function gseasy_auto_link_terms($content) {
    if (is_admin() || ! (bool) get_option('gseasy_auto_link', 0)) {
        return $content;
    }

    if (is_post_type_archive('gseasy_glossary')) {
        return $content;
    }

    // Stop the infinite loop.
    remove_filter('the_content', 'gseasy_auto_link_terms', 9);

    $terms = get_posts([
        'post_type'        => 'gseasy_glossary',
        'post_status'      => 'publish',
        'numberposts'      => -1,
        'orderby'          => 'title',
        'order'            => 'DESC', // Process longest titles first
        'suppress_filters' => false,
    ]);

    if (empty($terms)) {
        add_filter('the_content', 'gseasy_auto_link_terms', 9);
        return $content;
    }

    $current_post_id = is_singular('gseasy_glossary') ? get_the_ID() : 0;
    $tooltip_enabled = (bool) get_option('gseasy_tooltip_enable', 0);

    // This is the key: split content by links AND shortcodes.
    $parts = preg_split('/(<a\b[^>]*>.*?<\/a>|\[.*?\])/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        add_filter('the_content', 'gseasy_auto_link_terms', 9);
        return $content;
    }

    $patterns = [];
    foreach ($terms as $t) {
        if ($current_post_id && $current_post_id === $t->ID) {
            continue;
        }

        $title = trim(get_the_title($t));
        if ($title === '') continue;
        
        $quoted  = preg_quote($title, '/');
        $pattern = '/\b(' . $quoted . ')\b/i';

        $preview = gseasy_build_preview_text($t);
        $href    = get_permalink($t);

        $patterns[] = [
            'pattern' => $pattern,
            'replace' => function ($m) use ($href, $preview, $t, $tooltip_enabled) {
                $text  = $m[1];
                $class = $tooltip_enabled ? ' class="gseasy-term"' : '';
                return sprintf(
                    '<a%s href="%s" data-tooltip-preview="%s" data-term-id="%d" aria-describedby="gseasy-tooltip">%s</a>',
                    $class,
                    esc_url($href),
                    $preview,
                    (int) $t->ID,
                    esc_html($text)
                );
            }
        ];
    }

    if (empty($patterns)) {
        add_filter('the_content', 'gseasy_auto_link_terms', 9);
        return $content;
    }

    $new_parts = [];
    foreach ($parts as $seg) {
        // If the segment is a link or shortcode, skip it.
        if (preg_match('/^<a\b/i', $seg) || preg_match('/^\[.*?\]$/', $seg)) {
            $new_parts[] = $seg;
            continue;
        }

        // Apply all regex patterns to the non-shortcode/link segment.
        foreach ($patterns as $p) {
            $seg = preg_replace_callback($p['pattern'], $p['replace'], $seg, 1);
        }
        $new_parts[] = $seg;
    }

    // Reassemble the content and re-add the filter.
    add_filter('the_content', 'gseasy_auto_link_terms', 9);
    return implode('', $new_parts);
}

/* ------------------------------------------------------------
 * SHORTCODE: [gseasy_glossary]
 * ------------------------------------------------------------ */
function gseasy_render_index_shortcode() {
    $layout = gseasy_get_index_layout();
    $current_letter = get_query_var('gseasy_letter');
    $search_query   = get_query_var('gseasy_search');

    $args = [
        'post_type'      => 'gseasy_glossary',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    if ($search_query) {
        $args['s'] = $search_query;
    } elseif ($current_letter && ctype_alpha($current_letter) && strlen($current_letter) === 1) {
        add_filter('posts_where', 'gseasy_filter_by_first_letter');
    }

    $terms_query = new WP_Query($args);
    $terms = $terms_query->posts;

    if ($current_letter) {
        remove_filter('posts_where', 'gseasy_filter_by_first_letter');
    }

    ob_start();

    $base_url = get_permalink();
    echo '<div class="gseasy-alphabet-filter">';
    $all_url = esc_url( add_query_arg( array( 'gseasy_search' => $search_query ), $base_url ) );
    echo '<a href="' . esc_url( $all_url ) . '" class="gseasy-filter-all">All</a>';
    foreach (range('A', 'Z') as $letter) {
        $url = esc_url( add_query_arg( array( 'gseasy_letter' => $letter, 'gseasy_search' => $search_query ), $base_url ) );
        $class = ($letter === $current_letter) ? 'active' : '';
        echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr($class) . '">' . esc_html($letter) . '</a>';
    }
    echo '</div>';

    $form_action = esc_url($base_url);
    $nonce = wp_create_nonce('gseasy_search_action');
    echo '<form class="gseasy-search-form" action="' . esc_url( $form_action ) . '" method="get">';
    if ($current_letter !== '') {
        echo '<input type="hidden" name="gseasy_letter" value="' . esc_attr($current_letter) . '">';
    }
    echo '<input type="hidden" name="gseasy_nonce" value="' . esc_attr($nonce) . '">';
    echo '<input type="text" name="gseasy_search" placeholder="Search terms..." value="' . esc_attr($search_query) . '">';
    echo '<button type="submit">Search</button>';
    echo '</form>';

    echo '<div class="gseasy-index ' . esc_attr($layout) . '">';

    if (empty($terms)) {
        echo '<p>No glossary terms found.</p>';
    } else {
        $grouped_terms = [];
        foreach ($terms as $term) {
            $first_letter = strtoupper(mb_substr($term->post_title, 0, 1));
            $grouped_terms[$first_letter][] = $term;
        }
        ksort($grouped_terms);

        foreach ($grouped_terms as $letter => $group) {
            echo '<h2 class="gseasy-group-letter">' . esc_html($letter) . '</h2>';
            foreach ($group as $term) {
                $excerpt = gseasy_get_excerpt($term, 30);
                echo '<div class="gseasy-item">';
                echo '<h3><a href="' . esc_url( get_permalink($term)) . '">' . esc_html($term->post_title) . '</a></h3>';
                echo '<p class="gseasy-item-excerpt">' . esc_html($excerpt) . '</p>';
                echo '</div>';
            }
        }
    }

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('gseasy_glossary', 'gseasy_render_index_shortcode');

/* Helper: excerpt */
function gseasy_get_excerpt(WP_Post $post, $length = 30) {
    if (!empty($post->post_excerpt)) return $post->post_excerpt;
    $raw = wp_strip_all_tags($post->post_content, true);
    return wp_trim_words($raw, $length, '...');
}

/* Helper: query by first letter */
function gseasy_filter_by_first_letter($where) {
    global $wpdb;
    $current_letter = get_query_var('gseasy_letter');
    if ($current_letter && ctype_alpha($current_letter) && strlen($current_letter) === 1) {
        $where .= $wpdb->prepare(" AND $wpdb->posts.post_title LIKE %s", $wpdb->esc_like($current_letter) . '%');
    }
    return $where;
}

/* ------------------------------------------------------------
 * TOOLTIP PREVIEW ENDPOINT
 * ------------------------------------------------------------ */
add_action('template_redirect', function() {
    $nonce_field = 'gseasy_preview_nonce';
    $nonce_action = 'gseasy_preview_action';

    if (isset($_GET['gseasy_preview']) && is_singular('gseasy_glossary')) {
        // Fix: Use the hardcoded key to access the $_GET array
        $nonce_val = isset($_GET[$nonce_field]) ? sanitize_text_field(wp_unslash($_GET[$nonce_field])) : '';

        if (!wp_verify_nonce($nonce_val, $nonce_action)) {
            wp_die('Security check failed. Please try again.', 'Error', array('response' => 403));
        }

        $content = get_the_excerpt() ?: wp_trim_words(get_the_content(), 20);
        echo wp_kses_post($content);
        exit;
    }
});

/* ------------------------------------------------------------
 * ENQUEUE CSS & JS
 * ------------------------------------------------------------ */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;

    $css_file = 'assets/tooltips.css';
    $js_file  = 'assets/tooltips.js';

    if (file_exists(GSEASY_PLUGIN_DIR . $css_file)) {
        wp_enqueue_style(
            'gseasy-tooltips',
            GSEASY_PLUGIN_URL . $css_file,
            [],
            filemtime(GSEASY_PLUGIN_DIR . $css_file)
        );
    }

    if (! (bool) get_option('gseasy_tooltip_enable', 0)) return;

    if (file_exists(GSEASY_PLUGIN_DIR . $js_file)) {
        wp_enqueue_script(
            'gseasy-tooltips',
            GSEASY_PLUGIN_URL . $js_file,
            [],
            filemtime(GSEASY_PLUGIN_DIR . $js_file),
            true
        );
        wp_localize_script('gseasy-tooltips', 'GSEASYSettings', [
            'tooltipEnable' => true,
            'tooltipStyle'  => get_option('gseasy_tooltip_style', 'light'),
        ]);
    }
});

/* -------------------------------------------------------------------------
 * SERVER-SIDE PREVIEW BUILDER (excerpt or first N characters of content)
 * ------------------------------------------------------------------------- */
function gseasy_build_preview_text(WP_Post $post, $len = null) {
    $len = $len ?? (int) apply_filters('gseasy_preview_length', 20);
    if ($len < 1) $len = 20;

    // First, try to get the raw, unfiltered excerpt directly from the post object.
    $raw_excerpt = isset($post->post_excerpt) ? $post->post_excerpt : '';

    // If the excerpt exists, use it.
    if (!empty($raw_excerpt)) {
        $text = $raw_excerpt;
    } else {
        // If there is no manual excerpt, trim the raw content.
        $raw_content = get_post_field('post_content', $post->ID, 'raw');
        $text = wp_trim_words($raw_content, $len, '...');
    }

    $text = preg_replace('/\s+/u', ' ', $text);
    return esc_attr($text);
}
