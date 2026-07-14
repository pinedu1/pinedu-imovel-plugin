<?php
require_once plugin_dir_path( __FILE__ ) . '../rest/PineduReceiverRest.php';
require_once plugin_dir_path( __FILE__ ) . './ImovelSearchService.php';

/**
 * The public-facing functionality of the plugin.
 *
 * @package    Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/public
 * @author     Eduardo Pinheiro da Silva <eduardopinhe@gmail.com>
 */
class Pinedu_Imovel_Plugin_Public {
    const PAGINAS_FILTRO_PESQUISA = array( 'front-page.php', 'pesquisa', 'imovel' );

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles( ) {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/pinedu-imovel-plugin-public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts( ) {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/pinedu-imovel-plugin-public.js', array('jquery'), $this->version, false);
    }

    public function register_taxonomies( ) {
        TaxonomiaFactory::criar( 'Contrato' )->registrar( );
        TaxonomiaFactory::criar( 'TipoImovel' )->registrar( );
        TaxonomiaFactory::criar( 'Cidade' )->registrar( );
        TaxonomiaFactory::criar( 'Regiao' )->registrar( );
        TaxonomiaFactory::criar( 'Bairro' )->registrar( );
        TaxonomiaFactory::criar( 'Captador' )->registrar( );
        TaxonomiaFactory::criar( 'FaixaValor' )->registrar( );
        TaxonomiaFactory::criar( 'TipoDependencia' )->registrar( );
    }

    public function register_posttypes( ) {
        PosttypeFactory::criar( 'Empresa' )->registrar( );
        PosttypeFactory::criar( 'Loja' )->registrar( );
        PosttypeFactory::criar( 'Corretor' )->registrar( );
        PosttypeFactory::criar( 'Imovel' )->registrar( );
        PosttypeFactory::criar( 'Financeira' )->registrar( );
    }

    // Função auxiliar com fallback (valor default)
    function get_request_param($key, $default = '') {
        return isset($_REQUEST[$key]) ? sanitize_text_field($_REQUEST[$key]) : $default;
    }

    /**
     * CORE: Motor principal que injeta os filtros de Busca.
     * Compartilhável entre AJAX (se necessário futuramente) e carregamento nativo.
     */
    public function aplicar_filtros_pesquisa( $query ) {
        $tipo_pesquisa = $this->get_request_param( 'tipo_pesquisa_submit' );
        if ( ! is_admin() && $query->is_main_query() && ( $query->is_search() || $query->is_archive() ) ) {
            $query->set( 'post_type', 'imovel' );
        }
        if ( is_page( 'pesquisa' ) ) {
            if ( ! isset( $_GET['tipo_pesquisa_submit'] ) ) {
                $nova_url = add_query_arg( 'tipo_pesquisa_submit', 'imovel' );
                wp_safe_redirect( $nova_url );
                exit;
            }
        }
        if ( empty($tipo_pesquisa) ) return;

        if ( $tipo_pesquisa == 'imovel' ) {
            ImovelSearchService::apply($query);
        } else if ( $tipo_pesquisa == 'consulta' ) {
            $query->set( 'post_type', 'imovel' );
            $query->set( 'posts_per_page', 1 );
            $query->set( 'meta_query', [
                'relation' => 'AND',
                [ 'key' => 'statusImovel', 'value' => 'D', 'compare' => '=' ],
                [ 'key' => 'referencia', 'value' => $this->get_request_param( 'referencia' ), 'compare' => '=' ]
            ]);
        }
    }

    /**
     * Hook nativo do WP que dispara a filtragem principal.
     */
    public function register_search_posttype_imovel( $query ) {
        if ( ! is_admin() && $query->is_main_query() ) {
            $this->aplicar_filtros_pesquisa( $query );
        }
    }

    public function force_single_imovel_template( $template ) {
        global $wp_query;

        $this->handle_referencia_redirects();

        if ( !empty($this->get_request_param('tipo_pesquisa_submit')) && $this->get_request_param('tipo_pesquisa_submit') === 'consulta' ) {
            if ( ($wp_query->get('post_type') === 'imovel') && ($wp_query->post_count === 1) ) {
                $new_template = locate_template(array('single-imovel.php', 'single.php') );
                if ($new_template) {
                    return $new_template;
                }
            }
        }
        return $template;
    }

    private function find_imovel_by_referencia($referencia) {
        $args = array(
            'post_type' => 'imovel',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => array(
                array( 'key' => 'referencia', 'value' => $referencia, 'compare' => '=' )
            )
        );
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }

    private function redirect_to_imovel_by_referencia($referencia) {
        $imovel_id = $this->find_imovel_by_referencia($referencia);

        if ($imovel_id) {
            wp_redirect(get_permalink($imovel_id), 301);
            exit;
        } else {
            wp_redirect(get_post_type_archive_link('imovel'), 302);
            exit;
        }
    }

    private function handle_referencia_redirects() {
        if (did_action('template_redirect') > 1) return;

        $referencia = $this->get_referencia_from_request();
        if ($referencia) {
            $this->redirect_to_imovel_by_referencia($referencia);
        }
    }

    private function get_referencia_from_request() {
        global $wp;
        $path = trim($wp->request, '/');

        if (preg_match('#^imoveis/(\d+)/?$#', $path, $matches)) return $matches[1];
        if (isset($_GET['referencia']) && is_numeric($_GET['referencia'])) return $_GET['referencia'];
        if (isset($_GET['ref']) && is_numeric($_GET['ref'])) return $_GET['ref'];

        return null;
    }

    public function config_wp_mail( $phpmailer ) {
        require_once plugin_dir_path( __FILE__ ) . '../includes/classes/MailConfig.php';
        MailConfig::config_wp_mail( $phpmailer );
    }

    public function register_rest_endpoint() {
        PineduReceiverRest::instala_rest_end_point();
    }

    public function excluir_fotos_ao_apagar_post($post_id) {
        if (get_post_type( $post_id ) !== 'imovel') return;

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_parent'    => $post_id,
            'fields'         => 'ids',
        ]);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
        }
    }
    public function forcar_argumento_pesquisa_imovel() {
        if ( !isset( $_REQUEST['tipo_pesquisa_submit'] ) ) {
            $url_atual = $_SERVER['REQUEST_URI'];
            // 1. Defina aqui todas as rotas que precisam do parâmetro
            $rotas_monitoradas = [
                '/pesquisa',
                '/tipo-imovel',
                '/cidade',
                '/regiao'
            ];
            // 2. Verifica se a URL atual contém QUALQUER UMA das rotas definidas
            $deve_redirecionar = false;
            foreach ( $rotas_monitoradas as $rota ) {
                if ( strpos( $url_atual, $rota ) !== false ) {
                    $deve_redirecionar = true;
                    break; // Achou uma rota, pode parar a busca
                }
            }
            // 3. Executa a lógica apenas se necessário
            if ( $deve_redirecionar ) {
                $nova_url = add_query_arg( 'tipo_pesquisa_submit', 'imovel' );
                wp_safe_redirect( $nova_url );
                exit;
            }
        }
    }
    public function forcar_404_se_vazio() {
        global $wp_query;
        $tipo_pesquisa = $this->get_request_param('tipo_pesquisa_submit');
        if ( !empty($tipo_pesquisa) && $tipo_pesquisa === 'consulta' && $wp_query->is_main_query() ) {
            if ( ! have_posts() ) {
                $wp_query->set_404();
                status_header( 404 );
                nocache_headers();
                $template_404 = locate_template( array( '404.php' ) );
                if ( $template_404 ) {
                    include( $template_404 );
                    exit;
                }
            }
        }
    }
    /**
     * Força a configuração do tamanho médio de imagens na memória
     */
    public function forcar_largura_medium() {
        return 640;
    }
    public function forcar_altura_medium() {
        return 480;
    }
    public function forcar_crop_medium() {
        return 0;
    }
    public function forcar_largura_large() {
        return 1366;
    }

    public function forcar_altura_large() {
        return 900;
    }

    public function forcar_crop_large() {
        return 0;
    }
    public function ajustar_qualidade_imagem() {
        return 75;
    }
}