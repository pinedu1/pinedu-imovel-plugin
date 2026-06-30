<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link	   https://www.pinedu.com.br
 * @since	  1.0.0
 *
 * @package	Pinedu_Imovel_Plugin
 * @subpackage Pinedu_Imovel_Plugin/includes
 */
class Pinedu_Imovel_Plugin {
    protected $loader;
    protected $plugin_name;
    protected $version;
    public function __construct() {
        if ( defined( 'PINEDU_IMOVEL_PLUGIN_VERSION' ) ) {
            $this->version = PINEDU_IMOVEL_PLUGIN_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'pinedu-imovel-plugin';
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    private function load_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'classes/Posttypes.php';
        require_once plugin_dir_path( __FILE__ ) . 'classes/Taxonomias.php';
        require_once plugin_dir_path( __FILE__ ) . 'classes/PaginasIniciais.php';
        require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';
        require_once plugin_dir_path( __FILE__ ) . 'classes/PrettyUrl.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pinedu-imovel-plugin-loader.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-pinedu-imovel-plugin-i18n.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-pinedu-imovel-plugin-admin.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-pinedu-imovel-plugin-public.php';
        $this->loader = new Pinedu_Imovel_Plugin_Loader();
    }
    /**
     * Intercepta o envio a nível de servidor/SMTP e força o HTML
     */
    public function config_wp_mail( $phpmailer ) {
        // Força globalmente o tipo de conteúdo como HTML
        $phpmailer->isHTML( true );
        require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';
        MailConfig::config_wp_mail( $phpmailer );
    }
    /**
     * Intercepta o conteúdo do e-mail antes de passar para o PHPMailer e aplica o Template
     */
    /**
     * Intercepta o conteúdo do e-mail antes de passar para o PHPMailer e busca o Template no Tema
     */
    public function aplicar_template_email( $args ) {

        // 1. Interceptação: Formulário de Contato Padrão
        if ( strpos( $args['subject'], 'Contato via Site' ) !== false ) {
            $mensagem_original = $args['message'];

            // Extrai as variáveis da string original gerada no formulário
            $nome     = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $email    = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $telefone = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';

            $texto_mensagem = $mensagem_original;
            if ( preg_match( '/Mensagem:\s*(.*)/is', $mensagem_original, $matches ) ) {
                $texto_mensagem = trim( $matches[1] );
            }

            // Prepara o array de dados para enviar ao arquivo do tema
            $dados_template = [
                'nome'         => $nome
                , 'email'      => $email
                , 'telefone'   => $telefone
                , 'mensagem'   => $texto_mensagem
            ];

            // Inicia o buffer para não imprimir o HTML na tela do usuário
            ob_start();
            get_template_part( 'template-parts/empresa/template-email', 'email', $dados_template );
            $corpo_email_html = ob_get_clean();

            // Fallback de segurança
            if ( ! empty( $corpo_email_html ) ) {
                $args['message'] = $corpo_email_html;
            }
        }

        // 2. Interceptação: Formulário Trabalhe Conosco
        elseif ( strpos( $args['subject'], 'Trabalhe Conosco' ) !== false ) {
            $mensagem_original = $args['message'];

            // Extrai as variáveis baseadas no padrão montado no arquivo trabalhe-conosco.php
            $nome     = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $email    = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $celular  = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $cidade   = preg_match( '/Cidade:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $cargo    = preg_match( '/Cargo:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $creci    = preg_match( '/Situação CRECI:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '';

            $texto_mensagem = '';
            if ( preg_match( '/Mensagem:\s*(.*)/is', $mensagem_original, $matches ) ) {
                $texto_mensagem = trim( $matches[1] );
            }

            // Empacota os dados extras para o RH
            $dados_template = [
                'nome'       => $nome
                , 'email'    => $email
                , 'celular'  => $celular
                , 'cidade'   => $cidade
                , 'cargo'    => $cargo
                , 'creci'    => $creci
                , 'mensagem' => $texto_mensagem
            ];

            // Inicia o buffer e chama a nova template de RH
            ob_start();
            // Nota: Certifique-se de que o arquivo criado se chama "template-email-trabalhe-conosco.php"
            // e está dentro da mesma pasta "template-parts/empresa/"
            get_template_part( 'template-parts/empresa/template-email', 'trabalhe-conosco', $dados_template );
            $corpo_email_html = ob_get_clean();

            if ( ! empty( $corpo_email_html ) ) {
                $args['message'] = $corpo_email_html;
            }
        }

        return $args;
    }
   private function set_locale() {
        $plugin_i18n = new Pinedu_Imovel_Plugin_i18n();
        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }
    private function define_admin_hooks() {
        $plugin_admin = new Pinedu_Imovel_Plugin_Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
    }
    private function define_public_hooks() {
        $plugin_public = new Pinedu_Imovel_Plugin_Public( $this->get_plugin_name(), $this->get_version() );
        $pretty_url = new PrettyUrl( true );
        // CORREÇÃO E INTERCEPTAÇÃO: Usando $this para referenciar os métodos da própria classe
        $this->loader->add_action( 'phpmailer_init', $this, 'config_wp_mail', 0 );
        $this->loader->add_filter( 'wp_mail', $this, 'aplicar_template_email', 10 );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 5 );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts', 10 );
        $this->loader->add_action( 'init', $plugin_public, 'register_posttypes', 6 );
        $this->loader->add_action( 'init', $plugin_public, 'register_taxonomies', 5 );
        $this->loader->add_action( 'init', $pretty_url, 'do', 10 );
        $this->loader->add_action( 'pre_get_posts', $plugin_public, 'register_search_posttype_imovel' );
        $this->loader->add_filter( 'template_include', $plugin_public, 'force_single_imovel_template' );
        $this->loader->add_action( 'rest_api_init', $plugin_public , 'register_rest_endpoint' );
        $this->loader->add_action( 'before_delete_post', $plugin_public, 'excluir_fotos_ao_apagar_post', 0 );
        $this->loader->add_action( 'template_redirect', $plugin_public, 'forcar_argumento_pesquisa_imovel', 10 );
        $this->loader->add_action( 'template_redirect', $plugin_public, 'forcar_404_se_vazio', 20 );
    }
    public function run() {
        $this->loader->run();
    }
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    public function get_loader() {
        return $this->loader;
    }
    public function get_version() {
        return $this->version;
    }
}