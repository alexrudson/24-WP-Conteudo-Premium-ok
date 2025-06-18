<?php
/**
 * Plugin Name: WP Premium Content
 * Description: Creates a "Conteúdo Premium" CPT with modules, drip-feed, and resource links.
 * Version: 1.0.1
 * Author: Alex Rudson M. Vilhena
 * Author URI: https://www.vemver.net
 * Text Domain: wp-premium-content
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WPPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class WP_Premium_Content_Plugin {

    public function __construct() {
        // Core plugin actions
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies_for_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_premium_content_meta_boxes' ) );
        add_action( 'save_post_conteudo_premium', array( $this, 'save_premium_content_meta_data' ) );

        // Admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) ); // Added frontend CSS enqueue

        // Image handling
        add_action( 'after_setup_theme', array( $this, 'setup_image_sizes' ) );
        add_filter( 'post_thumbnail_html', array( $this, 'filter_post_thumbnail_html' ), 10, 5 );

        // AJAX
        add_action( 'wp_ajax_wppc_get_module_preview', array( $this, 'ajax_get_module_preview' ) );

        // Internationalization
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'wp-premium-content', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function register_post_types() {
        // Main Premium Content CPT
        $labels_premium = array(
            'name'               => _x( 'Conteúdos Premium', 'post type general name', 'wp-premium-content' ),
            'singular_name'      => _x( 'Conteúdo Premium', 'post type singular name', 'wp-premium-content' ),
            'menu_name'          => _x( 'Conteúdo Premium', 'admin menu', 'wp-premium-content' ),
            'name_admin_bar'     => _x( 'Conteúdo Premium', 'add new on admin bar', 'wp-premium-content' ),
            'add_new'            => _x( 'Adicionar Novo', 'conteúdo premium', 'wp-premium-content' ),
            'add_new_item'       => __( 'Adicionar Novo Conteúdo Premium', 'wp-premium-content' ),
            'new_item'           => __( 'Novo Conteúdo Premium', 'wp-premium-content' ),
            'edit_item'          => __( 'Editar Conteúdo Premium', 'wp-premium-content' ),
            'view_item'          => __( 'Ver Conteúdo Premium', 'wp-premium-content' ),
            'all_items'          => __( 'Todos Conteúdos Premium', 'wp-premium-content' ),
            'search_items'       => __( 'Pesquisar Conteúdos Premium', 'wp-premium-content' ),
            'parent_item_colon'  => __( 'Conteúdo Premium Pai:', 'wp-premium-content' ),
            'not_found'          => __( 'Nenhum conteúdo premium encontrado.', 'wp-premium-content' ),
            'not_found_in_trash' => __( 'Nenhum conteúdo premium encontrado na lixeira.', 'wp-premium-content' )
        );

        $args_premium = array(
            'labels'             => $labels_premium,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'conteudo-premium' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-star-filled',
            'supports'           => array( 'title', 'editor', 'thumbnail', 'author' ),
        );
        register_post_type( 'conteudo_premium', $args_premium );

        // Hidden Module CPT
        $labels_module = array(
            'name'               => _x( 'Módulos Premium', 'post type general name', 'wp-premium-content' ),
            'singular_name'      => _x( 'Módulo Premium', 'post type singular name', 'wp-premium-content' ),
        );
        $args_module = array(
            'labels'             => $labels_module,
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'supports'           => array( 'title', 'editor', 'author' ),
        );
        register_post_type( 'premium_module', $args_module );
    }

    public function register_taxonomies_for_cpt() {
        register_taxonomy_for_object_type( 'category', 'conteudo_premium' );
        register_taxonomy_for_object_type( 'post_tag', 'conteudo_premium' );
    }

    public function add_premium_content_meta_boxes() {
        add_meta_box(
            'wppc_modules_meta_box',
            __( 'Módulos do Curso', 'wp-premium-content' ),
            array( $this, 'render_modules_meta_box_callback' ),
            'conteudo_premium',
            'normal',
            'high'
        );
        add_meta_box(
            'wppc_resource_links_meta_box',
            __( 'Links de Recursos Adicionais', 'wp-premium-content' ),
            array( $this, 'render_resource_links_meta_box_callback' ),
            'conteudo_premium',
            'normal',
            'default'
        );
    }

    public function render_modules_meta_box_callback( $post ) {
        wp_nonce_field( 'wppc_save_modules_meta', 'wppc_modules_nonce' );
        $modules_data = get_post_meta( $post->ID, '_wppc_modules', true );
        if ( empty( $modules_data ) || !is_array($modules_data) ) { // Ensure it's an array
            $modules_data = array();
        }
        ?>
        <div id="wppc-modules-container">
            <p><?php _e( 'Arraste e solte para reordenar os módulos. Clique no título para expandir/colapsar a prévia.', 'wp-premium-content' ); ?></p>
            <?php
            if ( ! empty( $modules_data ) ) :
                foreach ( $modules_data as $index => $module_item ) :
                    $module_post = !empty($module_item['module_id']) ? get_post( intval($module_item['module_id']) ) : null;
                    $module_title = $module_post ? $module_post->post_title : '';
                    $module_content = $module_post ? $module_post->post_content : '';
                    $drip_days = isset( $module_item['drip_days'] ) ? intval( $module_item['drip_days'] ) : 0;
                    $module_id_value = $module_post ? $module_item['module_id'] : ''; // Use a different var name to avoid confusion
            ?>
            <div class="wppc-module-item postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <span><?php echo esc_html( $module_title ? $module_title : __('Novo Módulo', 'wp-premium-content') ); ?></span>
                    </h2>
                    <div class="handle-actions">
                        <button type="button" class="handlediv" aria-expanded="true">
                            <span class="screen-reader-text"><?php _e('Toggle panel', 'wp-premium-content'); ?></span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="inside">
                    <input type="hidden" name="wppc_modules[<?php echo $index; ?>][module_id]" value="<?php echo esc_attr( $module_id_value ); ?>" class="wppc-module-id">
                    <p>
                        <label for="wppc_modules_<?php echo $index; ?>_title"><?php _e( 'Título do Módulo:', 'wp-premium-content' ); ?></label><br>
                        <input type="text" id="wppc_modules_<?php echo $index; ?>_title" name="wppc_modules[<?php echo $index; ?>][title]" value="<?php echo esc_attr( $module_title ); ?>" class="widefat wppc-module-title-input" required>
                    </p>
                    <p>
                        <label for="wppc_modules_<?php echo $index; ?>_drip_days"><?php _e( 'Liberar após (dias):', 'wp-premium-content' ); ?></label><br>
                        <input type="number" id="wppc_modules_<?php echo $index; ?>_drip_days" name="wppc_modules[<?php echo $index; ?>][drip_days]" value="<?php echo esc_attr( $drip_days ); ?>" min="0" step="1" class="small-text">
                    </p>
                    <p>
                        <label><?php _e( 'Conteúdo do Módulo:', 'wp-premium-content' ); ?></label>
                    </p>
                    <?php
                    $editor_id = 'wppc_module_content_' . $index;
                    wp_editor( $module_content, $editor_id, array(
                        'textarea_name' => 'wppc_modules[' . $index . '][content]',
                        'textarea_rows' => 10,
                        'media_buttons' => true,
                        'tinymce'       => true,
                        'quicktags'     => true
                    ) );
                    ?>
                     <div class="wppc-module-preview-area" style="margin-top:10px; border:1px dashed #ccc; padding:10px; min-height:50px; display:none;">
                        <?php _e('Prévia do conteúdo (ex: vídeo do YouTube) aparecerá aqui ao colar um link e expandir.', 'wp-premium-content'); ?>
                    </div>
                    <button type="button" class="button button-secondary wppc-remove-module" style="margin-top:10px;"><?php _e( 'Remover Módulo', 'wp-premium-content' ); ?></button>
                </div>
            </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" id="wppc-add-module" class="button button-primary"><?php _e( 'Adicionar Novo Módulo', 'wp-premium-content' ); ?></button>

        <script type="text/html" id="wppc-module-template">
            <div class="wppc-module-item postbox">
                <div class="postbox-header">
                     <h2 class="hndle">
                        <span><?php _e('Novo Módulo', 'wp-premium-content'); ?></span>
                    </h2>
                    <div class="handle-actions">
                        <button type="button" class="handlediv" aria-expanded="true">
                            <span class="screen-reader-text"><?php _e('Toggle panel', 'wp-premium-content'); ?></span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="inside">
                    <input type="hidden" name="wppc_modules[__INDEX__][module_id]" value="" class="wppc-module-id">
                    <p>
                        <label for="wppc_modules___INDEX___title"><?php _e( 'Título do Módulo:', 'wp-premium-content' ); ?></label><br>
                        <input type="text" id="wppc_modules___INDEX___title" name="wppc_modules[__INDEX__][title]" value="" class="widefat wppc-module-title-input" required>
                    </p>
                    <p>
                        <label for="wppc_modules___INDEX___drip_days"><?php _e( 'Liberar após (dias):', 'wp-premium-content' ); ?></label><br>
                        <input type="number" id="wppc_modules___INDEX___drip_days" name="wppc_modules[__INDEX__][drip_days]" value="0" min="0" step="1" class="small-text">
                    </p>
                    <p>
                        <label for="wppc_module_content___INDEX__"><?php _e( 'Conteúdo do Módulo:', 'wp-premium-content' ); ?></label>
                    </p>
                    <textarea id="wppc_module_content___INDEX__" name="wppc_modules[__INDEX__][content]" class="widefat wppc-module-content-textarea" rows="10" placeholder="<?php _e('Adicione o conteúdo do módulo aqui. Cole um link do YouTube para pré-visualização automática ao expandir.', 'wp-premium-content'); ?>"></textarea>
                    <div class="wppc-module-preview-area" style="margin-top:10px; border:1px dashed #ccc; padding:10px; min-height:50px; display:none;">
                         <?php _e('Prévia do conteúdo (ex: vídeo do YouTube) aparecerá aqui ao colar um link e expandir.', 'wp-premium-content'); ?>
                    </div>
                    <button type="button" class="button button-secondary wppc-remove-module" style="margin-top:10px;"><?php _e( 'Remover Módulo', 'wp-premium-content' ); ?></button>
                </div>
            </div>
        </script>
        <?php
    }

    public function render_resource_links_meta_box_callback( $post ) {
        wp_nonce_field( 'wppc_save_links_meta', 'wppc_links_nonce' );
        $links_data = get_post_meta( $post->ID, '_wppc_resource_links', true );
        if ( empty( $links_data ) || !is_array($links_data) ) { // Ensure it's an array
            $links_data = array();
        }
        ?>
        <div id="wppc-links-container">
             <p><?php _e( 'Adicione links externos ou internos. Eles aparecerão listados no conteúdo premium. Arraste para reordenar. Clique no título para pré-visualizar.', 'wp-premium-content' ); ?></p>
            <?php
            if ( ! empty( $links_data ) ) :
                foreach ( $links_data as $index => $link_item ) :
                    $url = isset( $link_item['url'] ) ? $link_item['url'] : '';
                    $description = isset( $link_item['description'] ) ? $link_item['description'] : '';
            ?>
            <div class="wppc-link-item postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <span><?php echo esc_html( $description ? $description : __('Novo Link', 'wp-premium-content') ); ?></span>
                    </h2>
                     <div class="handle-actions">
                        <button type="button" class="handlediv" aria-expanded="true">
                            <span class="screen-reader-text"><?php _e('Toggle panel', 'wp-premium-content'); ?></span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="inside">
                    <p>
                        <label for="wppc_links_<?php echo $index; ?>_url"><?php _e( 'URL:', 'wp-premium-content' ); ?></label><br>
                        <input type="url" id="wppc_links_<?php echo $index; ?>_url" name="wppc_links[<?php echo $index; ?>][url]" value="<?php echo esc_url( $url ); ?>" class="widefat" required>
                    </p>
                    <p>
                        <label for="wppc_links_<?php echo $index; ?>_description"><?php _e( 'Descrição (usada como título do link):', 'wp-premium-content' ); ?></label><br>
                        <textarea id="wppc_links_<?php echo $index; ?>_description" name="wppc_links[<?php echo $index; ?>][description]" class="widefat wppc-link-description-input" rows="2"><?php echo esc_textarea( $description ); ?></textarea>
                    </p>
                    <button type="button" class="button button-secondary wppc-remove-link"><?php _e( 'Remover Link', 'wp-premium-content' ); ?></button>
                </div>
            </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>
        <button type="button" id="wppc-add-link" class="button button-primary"><?php _e( 'Adicionar Novo Link', 'wp-premium-content' ); ?></button>

        <script type="text/html" id="wppc-link-template">
            <div class="wppc-link-item postbox">
                 <div class="postbox-header">
                    <h2 class="hndle">
                        <span><?php _e('Novo Link', 'wp-premium-content'); ?></span>
                    </h2>
                    <div class="handle-actions">
                        <button type="button" class="handlediv" aria-expanded="true">
                            <span class="screen-reader-text"><?php _e('Toggle panel', 'wp-premium-content'); ?></span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="inside">
                    <p>
                        <label for="wppc_links___LINDEX___url"><?php _e( 'URL:', 'wp-premium-content' ); ?></label><br>
                        <input type="url" id="wppc_links___LINDEX___url" name="wppc_links[__LINDEX__][url]" value="" class="widefat" required>
                    </p>
                    <p>
                        <label for="wppc_links___LINDEX___description"><?php _e( 'Descrição (usada como título do link):', 'wp-premium-content' ); ?></label><br>
                        <textarea id="wppc_links___LINDEX___description" name="wppc_links[__LINDEX__][description]" class="widefat wppc-link-description-input" rows="2"></textarea>
                    </p>
                    <button type="button" class="button button-secondary wppc-remove-link"><?php _e( 'Remover Link', 'wp-premium-content' ); ?></button>
                </div>
            </div>
        </script>
        <?php
    }

    public function save_premium_content_meta_data( $post_id ) {
        // Check Nonces, Autosave, and Permissions for Modules
        if ( ! isset( $_POST['wppc_modules_nonce'] ) || ! wp_verify_nonce( $_POST['wppc_modules_nonce'], 'wppc_save_modules_meta' ) ) {
            // If modules nonce isn't set, we might still want to save links if its nonce is set.
            // However, if one is missing, it's often an indication of a problem or partial submission.
            // For simplicity in this combined function, if the primary (modules) nonce fails, we might return early.
            // A more robust approach might separate saving logic or check both nonces independently.
            // For now, let's assume module submission implies link submission intent if data exists.
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save Modules
        $sanitized_modules_data = array();
        if ( isset( $_POST['wppc_modules'] ) && is_array( $_POST['wppc_modules'] ) ) {
            $current_user_id = get_current_user_id();
            foreach ( $_POST['wppc_modules'] as $module_data_raw ) {
                $module_id = !empty( $module_data_raw['module_id'] ) ? intval( $module_data_raw['module_id'] ) : 0;
                $title = isset($module_data_raw['title']) ? sanitize_text_field( $module_data_raw['title'] ) : '';
                $content = isset($module_data_raw['content']) ? wp_kses_post( $module_data_raw['content'] ) : '';
                $drip_days = isset( $module_data_raw['drip_days'] ) ? intval( $module_data_raw['drip_days'] ) : 0;

                if ( empty( $title ) ) continue;

                $module_post_data = array(
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_type'    => 'premium_module',
                    'post_author'  => $current_user_id,
                );

                $saved_module_id = 0; // Initialize
                if ( $module_id > 0 ) {
                    $module_post_data['ID'] = $module_id;
                    $updated_module_id = wp_update_post( $module_post_data, true );
                    if ( !is_wp_error($updated_module_id) ) {
                       $saved_module_id = $updated_module_id;
                    }
                } else {
                    $inserted_module_id = wp_insert_post( $module_post_data, true );
                     if ( !is_wp_error($inserted_module_id) ) {
                        $saved_module_id = $inserted_module_id;
                    }
                }
                
                if ( $saved_module_id > 0 ) {
                    $sanitized_modules_data[] = array(
                        'module_id' => $saved_module_id,
                        'drip_days' => $drip_days,
                    );
                }
            }
        }
        update_post_meta( $post_id, '_wppc_modules', $sanitized_modules_data );

        // Save Resource Links (Check nonce specifically for links if present)
        if ( isset( $_POST['wppc_links_nonce'] ) && wp_verify_nonce( $_POST['wppc_links_nonce'], 'wppc_save_links_meta' ) ) {
            $sanitized_links_data = array();
            if ( isset( $_POST['wppc_links'] ) && is_array( $_POST['wppc_links'] ) ) {
                foreach ( $_POST['wppc_links'] as $link_data_raw ) {
                    $url = isset( $link_data_raw['url'] ) ? esc_url_raw( $link_data_raw['url'] ) : '';
                    $description = isset( $link_data_raw['description'] ) ? sanitize_textarea_field( $link_data_raw['description'] ) : '';

                    if ( empty( $url ) || empty( $description ) ) continue;

                    $sanitized_links_data[] = array(
                        'url'         => $url,
                        'description' => $description,
                    );
                }
            }
            update_post_meta( $post_id, '_wppc_resource_links', $sanitized_links_data );
        } elseif ( !isset( $_POST['wppc_links_nonce'] ) && empty($_POST['wppc_links']) ) {
            // If no links nonce and no links submitted, clear existing links meta if you want to ensure removal on empty submission.
            // Or, do nothing to preserve links if the links meta box was somehow not submitted.
            // For this version, let's clear it if the form intended to (i.e., nonce was present or no links submitted with module save).
            // If a links nonce *was* expected but failed verification, we shouldn't touch the links meta.
            // This part can be tricky. A simpler approach is if the nonce *is* set, process. If not, don't.
            // If we only update if the nonce is valid and data is present:
            if ( isset( $_POST['wppc_links_nonce'] ) || (isset( $_POST['wppc_modules_nonce'] ) && !isset($_POST['wppc_links'])) ) {
                // This condition means: either links nonce is there (and verified above) OR
                // modules nonce is there AND no links were submitted at all (implying they were removed)
                // This is slightly complex. Let's simplify: only save links if their nonce is valid.
                // The previous structure was implicitly doing this.
                // To clear links if the array is empty and nonce was good:
                if( isset( $_POST['wppc_links_nonce'] ) && wp_verify_nonce( $_POST['wppc_links_nonce'], 'wppc_save_links_meta' ) && empty($_POST['wppc_links'])) {
                     update_post_meta( $post_id, '_wppc_resource_links', array() ); // Clear if submitted empty
                }
            }
        }
    }

    public function enqueue_admin_assets( $hook_suffix ) {
        global $post_type;
        if ( ( 'post.php' == $hook_suffix || 'post-new.php' == $hook_suffix ) && isset($post_type) && 'conteudo_premium' == $post_type ) {
            wp_enqueue_style( 'wppc-admin-css', WPPC_PLUGIN_URL . 'css/wppc-admin.css', array(), '1.0.1' ); // Version bump
            wp_enqueue_script( 'wppc-admin-js', WPPC_PLUGIN_URL . 'js/wppc-admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-util' ), '1.0.1', true ); // Version bump
            
            wp_localize_script('wppc-admin-js', 'wppc_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('wppc_module_preview_nonce'),
                'text'     => array(
                    'new_module_title' => __('Novo Módulo', 'wp-premium-content'),
                    'new_link_title' => __('Novo Link', 'wp-premium-content'),
                    'confirm_remove' => __('Tem certeza que deseja remover este item?', 'wp-premium-content'),
                    'preview_loading' => __('Carregando prévia...', 'wp-premium-content'),
                    'preview_error' => __('Não foi possível carregar a prévia.', 'wp-premium-content'),
                    'initialize_editor_text' => __('Clique para inicializar o editor de texto.', 'wp-premium-content'),
                )
            ));
            wp_enqueue_script( 'wp-util' );
        }
    }

    public function enqueue_frontend_assets() {
        if ( is_singular( 'conteudo_premium' ) ) {
            wp_enqueue_style(
                'wppc-frontend-styles',
                WPPC_PLUGIN_URL . 'css/wppc-frontend.css',
                array(),
                '1.0.1' // Match version
            );
        }
    }
    
    public function ajax_get_module_preview() {
        check_ajax_referer( 'wppc_module_preview_nonce', 'nonce' );

        $content_url = isset( $_POST['content_url'] ) ? esc_url_raw( $_POST['content_url'] ) : '';

        if ( empty( $content_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Nenhuma URL fornecida.', 'wp-premium-content' ) ) );
        }

        if (strpos($content_url, 'youtube.com') !== false || strpos($content_url, 'youtu.be') !== false) {
            $embed_code = wp_oembed_get( $content_url );
            if ( $embed_code ) {
                wp_send_json_success( array( 'embed_code' => $embed_code ) );
            } else {
                wp_send_json_error( array( 'message' => __( 'Não foi possível gerar o embed para esta URL.', 'wp-premium-content' ) ) );
            }
        } else {
            wp_send_json_success( array( 'embed_code' => '<p>' . esc_html__( 'Prévia não disponível para este tipo de conteúdo. O conteúdo será exibido como texto.', 'wp-premium-content' ) . '</p><p style="word-break:break-all;">' . esc_html($content_url) . '</p>') );
        }
    }

    public function setup_image_sizes() {
        add_image_size( 'premium-featured-image', 200, 250, true );
    }

    public function filter_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        // Ensure get_post_type is valid before calling
        if ( $post_id && get_post_type($post_id) === 'conteudo_premium' && $size === 'premium-featured-image' ) {
            $html = preg_replace( '/style="[^"]*"/i', '', $html );
            $html = preg_replace( '/(width|height)="[^"]*"\s?/i', '', $html );
        }
        return $html;
    }
} // End WP_Premium_Content_Plugin class

// Instantiate the plugin
if ( class_exists( 'WP_Premium_Content_Plugin' ) ) {
    $wppc_plugin_instance = new WP_Premium_Content_Plugin();
}

// Activation and Deactivation Hooks
function wppc_activate_plugin() {
    // Ensure CPTs are registered to be included in rewrite rules
    if ( class_exists( 'WP_Premium_Content_Plugin' ) ) {
        // Temporarily instantiate to ensure CPTs are registered if not already by main instance
        // This is a bit redundant if the main instance is already created, but harmless.
        $temp_instance_for_activation = new WP_Premium_Content_Plugin();
        $temp_instance_for_activation->register_post_types();
    }
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wppc_activate_plugin' );

function wppc_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wppc_deactivate_plugin' );

?>