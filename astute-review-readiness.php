<?php
/*
Plugin Name: Astute Review Readiness
Description: Adds a "Ready for Review" checkbox to all public post types (except Posts) for dev team tracking. 
Provides a [page_review_tree] shortcode for listing reviewable content.
Version: 1.0
Author: Astute Communications
*/

class AstuteReviewReadiness {
    private $allowed_post_types = [];

    public function __construct() {
        add_action('init', [$this, 'determine_allowed_post_types']);
        add_action('add_meta_boxes', [$this, 'add_review_meta_box']);
        add_action('save_post', [$this, 'save_review_status']);
        add_action('wp_head', [$this, 'add_custom_styles']);
        add_shortcode('page_review_tree', [$this, 'generate_review_trees']);
    }

    public function determine_allowed_post_types() {
        $this->allowed_post_types = get_post_types(['public' => true], 'names');
        $this->allowed_post_types = array_diff(
            $this->allowed_post_types,
            ['post', 'attachment']
        );
    }

    public function add_custom_styles() {
        echo '<style>.astute-page-tree, .astute-page-tree ul { margin: 0; padding-left: 20px; list-style-type: none; }</style>';
    }

    public function add_review_meta_box() {
        foreach ($this->allowed_post_types as $post_type) {
            add_meta_box(
                'astute_review_status',
                'Review Status',
                [$this, 'render_review_meta_box'],
                $post_type,
                'side',
                'default'
            );
        }
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

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $review_status = isset($_POST['astute_ready_for_review']) ? 1 : 0;
        update_post_meta($post_id, '_astute_ready_for_review', $review_status);
    }

    public function generate_review_trees($atts) {
        $output = '';
        
        foreach ($this->allowed_post_types as $post_type) {
            $post_type_object = get_post_type_object($post_type);
            $output .= '<h2>' . esc_html($post_type_object->labels->name) . '</h2>';
            
            $tree = $this->generate_single_tree($post_type);
            $output .= $tree ? $tree : '<p>No items ready for review.</p>';
        }

        return $output;
    }

    private function generate_single_tree($post_type, $parent = 0) {
        $query_args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_parent' => $parent
        ];

        $pages_query = new WP_Query($query_args);

        ob_start();

        if ($pages_query->have_posts()) {
            echo '<ul class="astute-page-tree">';
            while ($pages_query->have_posts()) {
                $pages_query->the_post();
                
                $review_status = get_post_meta(get_the_ID(), '_astute_ready_for_review', true);
                $link_text = get_the_title();

                echo '<li>';
                
                if ($review_status) {
                    echo '<a href="' . get_permalink() . '">' . esc_html($link_text) . '</a>';
                } else {
                    echo esc_html($link_text);
                }

                // Recursively get child pages
                echo $this->generate_single_tree($post_type, get_the_ID());

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