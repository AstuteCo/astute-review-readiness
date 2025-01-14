<?php
/*
Plugin Name: Astute Review Readiness
Description: Adds a "Ready for Review" checkbox to pages for dev team tracking. 
Provides a [page_review_tree] shortcode with options:
- show_review_status (yes/no): Display review status
- parent: Specify parent page ID for nested trees
Version: 1.0
Author: Astute Communications
*/

class AstuteReviewReadiness {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_review_meta_box']);
        add_action('save_post', [$this, 'save_review_status']);
        add_action('wp_head', [$this, 'add_custom_styles']);
        add_shortcode('page_review_tree', [$this, 'generate_page_tree']);
    }

    public function add_custom_styles() {
        echo '<style>.astute-page-tree, .astute-page-tree ul { margin: 0; padding-left: 20px; list-style-type: none; }</style>';
    }

    public function add_review_meta_box() {
        add_meta_box(
            'astute_review_status',
            'Review Status',
            [$this, 'render_review_meta_box'],
            'page',
            'side',
            'default'
        );
    }

    public function render_review_meta_box($post) {
        $review_status = get_post_meta($post->ID, '_astute_ready_for_review', true);
        wp_nonce_field('astute_review_status_nonce', 'astute_review_status_nonce');
        ?>
        <label>
            <input type="checkbox" name="astute_ready_for_review" 
                   value="1" <?php checked(1, $review_status, true); ?> />
            Ready for Client Review
        </label>
        <?php
    }

    public function save_review_status($post_id) {
        if (!isset($_POST['astute_review_status_nonce']) || 
            !wp_verify_nonce($_POST['astute_review_status_nonce'], 'astute_review_status_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_page', $post_id)) {
            return;
        }

        $review_status = isset($_POST['astute_ready_for_review']) ? 1 : 0;
        update_post_meta($post_id, '_astute_ready_for_review', $review_status);
    }

    public function generate_page_tree($atts) {
        $atts = shortcode_atts([
            'parent' => 0,
            'show_review_status' => 'no',
            'link_only_ready' => 'yes'
        ], $atts, 'page_review_tree');

        $query_args = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_parent' => $atts['parent']
        ];

        $pages_query = new WP_Query($query_args);

        ob_start();

        if ($pages_query->have_posts()) {
            echo '<ul class="astute-page-tree">';
            while ($pages_query->have_posts()) {
                $pages_query->the_post();
                
                $review_status = get_post_meta(get_the_ID(), '_astute_ready_for_review', true);
                $link_text = get_the_title();

                echo '<li style="margin: 0;">';
                
                // Check for page readiness and linking
                if ($review_status || $atts['link_only_ready'] === 'no') {
                    echo '<a target="_blank" href="' . get_permalink() . '">' . esc_html($link_text) . '</a>';
                } else {
                    echo esc_html($link_text);
                }

                // Recursively get child pages
                $child_atts = $atts;
                $child_atts['parent'] = get_the_ID();
                echo $this->generate_page_tree($child_atts);

                echo '</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        }

        return ob_get_clean();
    }
}

// Initialize the plugin
new AstuteReviewReadiness();