<?php
/**
 * Plugin Name: Custom Post Sections
 * Description: Displays posts in a section layout with titles, content, and tags, and supports category-based filtering and search functionality.
 * Version: 1.1
 * Author: Payal Sharma
 */

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Enqueue styles and scripts
function custom_section_assets() {
    wp_enqueue_style( 'custom-section-style', plugin_dir_url( __FILE__ ) . 'style.css' );
    wp_enqueue_script( 'custom-section-script', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), null, true );
}
add_action( 'wp_enqueue_scripts', 'custom_section_assets' );

function render_section_posts( $atts ) {
    $atts = shortcode_atts(
        array(
            'category' => '', // Category slug
            'title' => '',    // Default title
        ),
        $atts,
        'section_posts'
    );

    // Fetch search query and tag query parameters
    $search_query = isset( $_GET['search_term'] ) ? sanitize_text_field( $_GET['search_term'] ) : '';
    $selected_tag = isset( $_GET['tag'] ) ? sanitize_text_field( $_GET['tag'] ) : '';

    // Query posts based on category, search term, and optionally filter by tag
    $query_args = array(
        'category_name' => $atts['category'],
        'posts_per_page' => -1,
        'meta_key' => 'post_order_id',   // Custom field key for ordering
        'orderby' => 'meta_value_num',   // Order by numeric value of the custom field
        'order' => 'ASC',                // Ascending order
        'meta_query' => array(
            array(
                'key' => 'post_order_id',      // Custom field to check
                'value' => 0,                  // Exclude posts where post_order_id is 0
                'compare' => '!=',             // Not equal to 0
                'type' => 'NUMERIC',          // Ensure numeric comparison
            ),
        ),
    );

    // Include search query if present
    if ( ! empty( $search_query ) ) {
        $query_args['s'] = $search_query; // Add the search term to the query
    }

    // Include tag filtering if a tag is selected
    if ( ! empty( $selected_tag ) ) {
        $query_args['tag'] = $selected_tag; // Filter by tag if selected
    }

    $query = new WP_Query( $query_args );
    ob_start();
    ?>
    <div class="custom-section">
        <h1 class="heading-title-posts"><?php echo esc_html( $atts['title'] ); ?></h1>

        <!-- Search Form -->
        <form action="" method="get" class="search-form">
            <div class="search-form">
                <input type="text" name="search_term" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search here..." />
                <button type="submit">Search</button>
            </div>
        </form>

        <div class="section-content">
            <div class="post-list">
                <ul>
                    <?php if ( $query->have_posts() ) : ?>
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <li class="post-title" data-post-id="<?php the_ID(); ?>">
                                <a href="javascript:void(0);"><?php the_title(); ?></a>
                            </li>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <li class="no-posts-message">No posts available for the selected tag.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="post-details">
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <div class="post-content" data-post-id="<?php the_ID(); ?>">
                            <h4><?php the_title(); ?></h4>
                            <div class="post-content-details">
                                <?php
                                $content = get_the_content();

                                // Get the value of the custom field 'display_words'
                                $display_words = get_post_meta( get_the_ID(), 'display_words', true );

                                if ( $display_words && is_numeric( $display_words ) && $display_words > 0 ) {
                                    // Limit the content to the specified number of words
                                    $content_words = explode( ' ', $content );
                                    $excerpt = implode( ' ', array_slice( $content_words, 0, $display_words ) );
                                    $full_content = implode( ' ', $content_words );

                                    // Display the excerpt with --cont
                                    echo '<div class="excerpt">' . wp_kses_post( wpautop( $excerpt ) ) . ' <a href="javascript:void(0);" class="show-more">--cont</a></div>';

                                    // Full content is hidden initially
                                    echo '<div class="full-content" style="display: none;">' . wp_kses_post( wpautop( $full_content ) ) . ' <a href="javascript:void(0);" class="show-less">--show less</a></div>';
                                } else {
                                    // Display full content if display_words is not set or is 0
                                    echo wp_kses_post( wpautop( $content ) );
                                }
                                ?>
                            </div>
                            <?php if ( has_post_thumbnail() ) : ?>
                                <div class="post-image">
                                    <?php the_post_thumbnail( 'full' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-tags">
            <h4>Tags:</h4>
            <ul>
                <?php
                // Get tags for the posts in the selected category
                $tags = get_terms( array(
                    'taxonomy' => 'post_tag',
                    'hide_empty' => true,
                    'fields' => 'all',
                ) );

                foreach ( $tags as $tag ) :
                    // Check if the tag exists in the current category posts
                    $tag_in_category_query = new WP_Query( array(
                        'category_name' => $atts['category'],
                        'tag' => $tag->slug,
                        'posts_per_page' => 1, // Check if any post exists
                    ) );

                    if ( $tag_in_category_query->have_posts() ) : ?>
                        <li>
                            <a href="?tag=<?php echo esc_attr( $tag->slug ); ?>" class="<?php echo $selected_tag === $tag->slug ? 'active-tag' : ''; ?>">
                                <?php echo esc_html( $tag->name ); ?>
                            </a>
                        </li>
                    <?php endif;

                    wp_reset_postdata();
                endforeach;
                ?>
            </ul>
        </div>

        <?php if ( ! empty( $selected_tag ) ) : ?>
            <!-- Reset Button -->
            <div class="reset-section">
                <a href="<?php echo esc_url( remove_query_arg( 'tag' ) ); ?>" class="reset-button">All Posts</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}


add_shortcode( 'section_posts', 'render_section_posts' );










// Add Admin Menu and Settings Page
function custom_section_admin_menu() {
    add_menu_page(
        'Custom Post Sections', // Page title
        'Custom Post Sections', // Menu title
        'manage_options', // Capability
        'custom-section-settings', // Menu slug
        'custom_section_settings_page', // Callback function
        'dashicons-archive', // Icon
        90 // Position
    );
}
add_action( 'admin_menu', 'custom_section_admin_menu' );

// Settings Page Content
function custom_section_settings_page() {
    // Get all categories
    $categories = get_categories();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Custom Post Sections Settings', 'custom-post-sections' ); ?></h1>

        <p>Use the following shortcodes to display posts from specific categories:</p>

        <h3> If you want to change the title then just put title of your own choice in the title.</h3>
        <pre>[section_posts category="your-post-category-slug" title="Your Section Title"]</pre>
        
        <h3>Customization</h3>
        <p>The <code>category</code> parameter defines which category to display posts from. The <code>title</code> parameter lets you set a custom title for the section.</p>

        <?php if ( ! empty( $categories ) ) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Shortcode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $categories as $category ) : ?>
                        <tr>
                            <td><?php echo esc_html( $category->name ); ?></td>
                            <td>
                                <pre>[section_posts category="<?php echo esc_attr( $category->slug ); ?>" title="<?php echo esc_attr( $category->name ); ?>"]</pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No categories found.</p>
        <?php endif; ?>
    </div>
    <?php
}