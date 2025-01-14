<?php
/*
Plugin Name: Astute Review Readiness
Description: Adds a "Ready for Review" checkbox to published posts and provides a shortcode for listing pages in a nested tree.
Version: 1.0
Author: Astute Communications
*/

class AstuteReviewReadiness {
    public function __construct() {
        // Add meta box to post edit screen
        add_action('add_meta_boxes', [$this, 'add_review_meta_box']);
        
        // Save meta box data
        add_action('save_post', [$this, 'save_review_status']);
        
        // Register shortcode
        add_shortcode('page_review_tree', [$this, 'generate_page_tree']);
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
        // Retrieve existing review status
        $review_status = get_post_meta($post->ID, '_astute_ready_for_review', true);
        
        // Create nonce for security
        wp_nonce_field('astute_review_status_nonce', 'astute_review_status_nonce');
        ?>
        <label>
            <input type="checkbox" name="astute_ready_for_review" 
                   value="1" <?php checked(1, $review_status, true); ?> />
            Ready for Review
        </label>
        <?php
    }

    public function save_review_status($post_id) {
        // Check if nonce is set and valid
        if (!isset($_POST['astute_review_status_nonce']) || 
            !wp_verify_nonce($_POST['astute_review_status_nonce'], 'astute_review_status_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }

        // Save or delete review status
        $review_status = isset($_POST['astute_ready_for_review']) ? 1 : 0;
        update_post_meta($post_id, '_astute_ready_for_review', $review_status);
    }

    public function generate_page_tree($atts) {
        // Default arguments
        $atts = shortcode_atts([
            'parent' => 0,
            'show_review_status' => 'yes',
            'review_status' => 1
        ], $atts, 'page_review_tree');

        // Get pages with optional review status filter
        $query_args = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_parent' => $atts['parent']
        ];

        // Add review status filter if enabled
        if ($atts['show_review_status'] === 'yes') {
            $query_args['meta_query'] = [
                [
                    'key' => '_astute_ready_for_review',
                    'value' => $atts['review_status'],
                    'compare' => '='
                ]
            ];
        }

        $pages_query = new WP_Query($query_args);

        // Start output buffer
        ob_start();

        if ($pages_query->have_posts()) {
            echo '<ul class="astute-page-tree">';
            while ($pages_query->have_posts()) {
                $pages_query->the_post();
                
                // Get review status
                $review_status = get_post_meta(get_the_ID(), '_astute_ready_for_review', true);
                
                // Prepare link text
                $link_text = get_the_title();
                if ($atts['show_review_status'] === 'yes') {
                    $link_text .= $review_status ? ' ✓' : ' ✗';
                }

                echo '<li>';
                echo '<a href="' . get_permalink() . '">' . esc_html($link_text) . '</a>';

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