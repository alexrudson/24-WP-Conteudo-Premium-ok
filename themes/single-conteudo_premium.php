<?php
/**
 * The template for displaying single Conteúdo Premium posts.
 * Este template deve estar no diretório raiz do seu tema ativo.
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
        while ( have_posts() ) :
            the_post();
            $post_id = get_the_ID(); // ID do Conteúdo Premium atual
        ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('wppc-single-premium-content'); ?>>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header><!-- .entry-header -->

                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="post-thumbnail-wrapper">
                        <?php
                        // Exibe a imagem destacada com o tamanho customizado 'premium-featured-image'
                        // O filtro no plugin removerá estilos inline.
                        the_post_thumbnail( 'premium-featured-image' );
                        ?>
                    </div>
                <?php endif; ?>

                <div class="entry-content">
                    <?php
                    // Conteúdo principal do post "Conteúdo Premium"
                    the_content();

                    wp_link_pages(
                        array(
                            'before' => '<div class="page-links">' . __( 'Pages:', 'wp-premium-content' ),
                            'after'  => '</div>',
                        )
                    );
                    ?>
                </div><!-- .entry-content -->

                <?php
                // Verifica se o usuário está logado
                if ( ! is_user_logged_in() ) :
                    echo '<div class="wppc-login-required">';
                    echo '<p>' . sprintf(
                        __( 'Você precisa estar <a href="%s">logado</a> para ver o conteúdo premium.', 'wp-premium-content' ),
                        esc_url( wp_login_url( get_permalink() ) )
                    ) . '</p>';
                    echo '</div>';
                else :
                    // Usuário está logado, vamos buscar e exibir os módulos e links
                    $current_user = wp_get_current_user();
                    $user_registered_date_str = $current_user->user_registered; // Formato: YYYY-MM-DD HH:MM:SS
                    $user_registration_timestamp = 0;

                    // Tenta converter a data de registro para timestamp
                    if ( !empty($user_registered_date_str) && ($reg_time = strtotime($user_registered_date_str)) !== false ) {
                        $user_registration_timestamp = $reg_time;
                    } else {
                        // Logar um erro se a data de registro for inválida, pois o drip-feed pode falhar
                        error_log("WP Premium Content: Data de registro inválida para o usuário ID " . $current_user->ID . ": " . $user_registered_date_str);
                    }

                    $current_timestamp = time(); // Timestamp atual (UTC)

                    // --- Exibir Links de Recursos Adicionais ---
                    $resource_links = get_post_meta( $post_id, '_wppc_resource_links', true );

                    if ( ! empty( $resource_links ) && is_array( $resource_links ) ) : ?>
                        <div class="wppc-resource-links-section">
                            <h2 class="wppc-section-title"><?php _e( 'Recursos Adicionais', 'wp-premium-content' ); ?></h2>
                            <ul class="wppc-resource-links-list">
                                <?php foreach ( $resource_links as $link_item ) : ?>
                                    <?php
                                    // Verifica se os campos 'url' e 'description' existem e não estão vazios
                                    if ( ! empty( $link_item['url'] ) && ! empty( $link_item['description'] ) ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( $link_item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( $link_item['description'] ); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; // Fim dos Links de Recursos


                    // --- Exibir Módulos do Curso (com lógica de gotejamento) ---
                    $modules_data = get_post_meta( $post_id, '_wppc_modules', true );

                    if ( ! empty( $modules_data ) && is_array( $modules_data ) ) : ?>
                        <div class="wppc-modules-section">
                            <h2 class="wppc-section-title"><?php _e( 'Módulos do Curso', 'wp-premium-content' ); ?></h2>
                            <ul class="wppc-modules-list">
                                <?php foreach ( $modules_data as $module_item ) :
                                    $module_id = isset( $module_item['module_id'] ) ? intval( $module_item['module_id'] ) : 0;
                                    $drip_days = isset( $module_item['drip_days'] ) ? intval( $module_item['drip_days'] ) : 0;

                                    // Pula se o ID do módulo for inválido
                                    if ( ! $module_id ) {
                                        continue;
                                    }

                                    // Busca o post do módulo
                                    $module_post = get_post( $module_id );

                                    // Pula se o post do módulo não existir ou não for do tipo 'premium_module'
                                    if ( ! $module_post || $module_post->post_type !== 'premium_module' ) {
                                        error_log("WP Premium Content: Módulo ID {$module_id} não encontrado ou tipo inválido para Conteúdo Premium ID {$post_id}.");
                                        continue;
                                    }

                                    // Calcula o timestamp de liberação do módulo
                                    $release_timestamp = 0;
                                    if ( $user_registration_timestamp > 0 ) { // Apenas se a data de registro for válida
                                        $release_timestamp = $user_registration_timestamp + ( $drip_days * DAY_IN_SECONDS );
                                    }

                                    $is_unlocked = false;
                                    if ( $drip_days == 0 ) { // Se drip_days é 0, está sempre desbloqueado
                                        $is_unlocked = true;
                                    } elseif ( $user_registration_timestamp > 0 && $current_timestamp >= $release_timestamp ) {
                                        $is_unlocked = true;
                                    }
                                ?>
                                    <li class="wppc-module <?php echo $is_unlocked ? 'wppc-module-unlocked' : 'wppc-module-locked'; ?>">
                                        <h3 class="wppc-module-title"><?php echo esc_html( $module_post->post_title ); ?></h3>
                                        <?php
                                        if ( $is_unlocked ) :
                                            // Módulo está desbloqueado
                                            ?>
                                            <div class="wppc-module-content">
                                                <?php
                                                // Aplica filtros como oEmbed para vídeos, shortcodes, etc.
                                                echo apply_filters( 'the_content', $module_post->post_content );
                                                ?>
                                            </div>
                                            <?php
                                        else :
                                            // Módulo está bloqueado
                                            if ($user_registration_timestamp === 0 && $drip_days > 0) { // Problema com data de registro e é um módulo gotejado
                                                echo '<p class="wppc-module-lock-message">' . __( 'Não foi possível determinar a data de liberação deste módulo devido a um problema com a data de registro do usuário.', 'wp-premium-content' ) . '</p>';
                                            } else { 
                                                // **** INÍCIO DA ALTERAÇÃO ****
                                                // Calcula os dias restantes para a liberação
                                                $seconds_remaining = $release_timestamp - $current_timestamp;
                                                $days_remaining = ceil( $seconds_remaining / DAY_IN_SECONDS );

                                                // Garante que o número de dias seja no mínimo 1 para exibir a mensagem corretamente
                                                if ($days_remaining <= 0) {
                                                    $days_remaining = 1;
                                                }

                                                // Cria a mensagem de espera com a contagem de dias, tratando singular e plural
                                                $wait_message = sprintf(
                                                    /* translators: %d is number of days */
                                                    _n(
                                                        'Falta %d dia para você ter acesso a este conteúdo. Por favor, aguarde o tempo de liberação.',
                                                        'Faltam %d dias para você ter acesso a este conteúdo. Por favor, aguarde o tempo de liberação.',
                                                        $days_remaining,
                                                        'wp-premium-content'
                                                    ),
                                                    $days_remaining
                                                );
                                                echo '<p class="wppc-module-lock-message">' . esc_html( $wait_message ) . '</p>';
                                                // **** FIM DA ALTERAÇÃO ****
                                            }
                                        endif;
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; // Fim dos Módulos
                endif; // Fim da verificação is_user_logged_in()
                ?>

            </article><!-- #post-<?php the_ID(); ?> -->

        <?php
        endwhile; // Fim do loop while ( have_posts() )
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_sidebar();
get_footer();