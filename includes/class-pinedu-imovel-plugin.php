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
        $this->loader->add_action( 'pinedu_trigger_action', 'PineduReceiverRest', 'pinedu_trigger_action_callback', 10, 1 );
        $this->loader->add_filter( 'wp_mail', $this, 'aplicar_template_email', 10 );
        $this->loader->add_filter( 'pre_get_document_title', $this, 'controlar_titulo_seo' );
        $this->loader->add_action( 'wp_head', $this, 'injetar_metas_seo', 1 );
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
        //
        $this->loader->add_filter( 'option_medium_size_w', $plugin_public, 'forcar_largura_medium' );
        $this->loader->add_filter( 'option_medium_size_h', $plugin_public, 'forcar_altura_medium' );
        $this->loader->add_filter( 'option_medium_crop', $plugin_public, 'forcar_crop_medium' );
        $this->loader->add_filter( 'option_large_size_w', $plugin_public, 'forcar_largura_large' );
        $this->loader->add_filter( 'option_large_size_h', $plugin_public, 'forcar_altura_large' );
        $this->loader->add_filter( 'option_large_crop', $plugin_public, 'forcar_crop_large' );
        $this->loader->add_filter( 'wp_editor_set_quality', $plugin_public, 'ajustar_qualidade_imagem' );
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
            //
        } else if (( strpos( $args['subject'], 'Solicitação de Visita no Imóvel Ref' ) !== false ) || ( strpos( $args['subject'], 'Opinião da Visita - Ref' ) !== false )) {
            $mensagem_original = $args['message'];

            // Extrai as variáveis da string original gerada no formulário
            $nome     = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $email    = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $telefone = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $referencia = preg_match( '/Referência:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $corretor = preg_match( '/Corretor Associado:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';

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
                , 'referencia'   => $referencia
                , 'corretor'   => $corretor
            ];

            // Inicia o buffer para não imprimir o HTML na tela do usuário
            ob_start();
            get_template_part( 'template-parts/empresa/template-email', 'imovel', $dados_template );
            $corpo_email_html = ob_get_clean();

            // Fallback de segurança
            if ( ! empty( $corpo_email_html ) ) {
                $args['message'] = $corpo_email_html;
            }
        } else if ( strpos( $args['subject'], 'Interesse no Imóvel Ref' ) !== false ) {
            $mensagem_original = $args['message'];

            // Extrai as variáveis da string original gerada no formulário
            $nome     = preg_match( '/Nome:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $email    = preg_match( '/Email:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $telefone = preg_match( '/Telefone:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $referencia = preg_match( '/Referência:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';
            $corretor = preg_match( '/Corretor Associado:\s*(.*)/i', $mensagem_original, $matches ) ? trim( $matches[1] ) : '-';

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
                , 'referencia'   => $referencia
                , 'corretor'   => $corretor
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

        return $args;
    }
    private function set_locale() {
        $plugin_i18n = new Pinedu_Imovel_Plugin_i18n();
        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }
    /**
     * PASSO B: Intercepta e controla a tag <title> de forma dinâmica.
     * Arquitetura baseada em Closures e Roteamento de Condições.
     */
    function controlar_titulo_seo( $title ) {
        $gerar_titulo_home = function() {
            $desc = get_bloginfo( 'description' );
            if ( empty( $desc ) ) {
                $options = get_option( 'pinedu_imovel_options', [] );
                $cidade_padrao = isset( $options['cidade'] ) ? $options['cidade'] : '';
                $cidade = 'Birigui';
                if ( !empty( $cidade_padrao ) ) {
                    $cid = get_term_by( 'slug', $cidade_padrao, 'cidade' );
                    if ( $cid ) {
                        $cidade = mb_convert_case( $cid->name, MB_CASE_TITLE, 'UTF-8' );
                    }
                }
                $desc = 'Especializada em imóveis de ' . $cidade . ' e região.';
            }
            return get_bloginfo( 'name' ) . ' | ' . $desc;
        };
        $gerar_titulo_imovel = function() {
            global $post;
            $obter_finalidade = function() use ( $post ) {
                $finalidades = array();
                $venda = isset($post->ativarVenda) ? $post->ativarVenda : get_post_meta( $post->ID, 'ativarVenda', true );
                $locacao = isset($post->ativarLocacao) ? $post->ativarLocacao : get_post_meta( $post->ID, 'ativarLocacao', true );
                $lancamento = isset($post->ativarLancamento) ? $post->ativarLancamento : get_post_meta( $post->ID, 'ativarLancamento', true );
                if ( '1' == $venda ) $finalidades[] = 'Venda';
                if ( '1' == $locacao ) $finalidades[] = 'Locação';
                if ( '1' == $lancamento ) $finalidades[] = 'Lançamento';
                if ( empty( $finalidades ) ) return '';
                if ( count( $finalidades ) > 1 ) {
                    $ultimo = array_pop( $finalidades );
                    return ' para ' . implode( ', ', $finalidades ) . ' e ' . $ultimo;
                }
                return ' para ' . $finalidades[0];
            };
            $referencia = get_post_meta( $post->ID, 'referencia', true );
            $tipo_bruto = get_post_meta( $post->ID, 'tipoImovelNome', true );
            $tipo = mb_convert_case( $tipo_bruto, MB_CASE_TITLE, 'UTF-8' );
            $bairro_bruto = get_post_meta( $post->ID, 'bairro', true );
            $bairro_limpo = preg_replace( '/^\d+\s*/', '', $bairro_bruto );
            $bairro = mb_convert_case( trim( $bairro_limpo ), MB_CASE_TITLE, 'UTF-8' );
            $cidade = mb_convert_case( get_post_meta( $post->ID, 'cidade', true ), MB_CASE_TITLE, 'UTF-8' );
            $finalidade_texto = $obter_finalidade();
            return "{$tipo}{$finalidade_texto} no {$bairro}, {$cidade} - Ref: {$referencia} | " . get_bloginfo( 'name' );
        };
        $gerar_titulo_pesquisa = function() {
            $termo = get_search_query();
            if ( empty( $termo ) ) {
                $filtros = array();
                $obter_finalidade = function( $contrato ) {
                    if ( '1' == $contrato ) return 'Venda';
                    if ( '2' == $contrato ) return 'Locação';
                    if ( '3' == $contrato ) return 'Lançamento';
                    return '';
                };
                $obter_nome_termo = function( $slug, $taxonomia ) {
                    $term = get_term_by( 'slug', sanitize_text_field( $slug ), $taxonomia );
                    $nome = $term ? $term->name : sanitize_text_field( $slug );
                    return mb_convert_case( $nome, MB_CASE_TITLE, 'UTF-8' );
                };
                $tax_atual = '';
                $nome_tax_atual = '';
                if ( is_tax() || is_category() ) {
                    $term = get_queried_object();
                    if ( isset( $term->taxonomy ) && isset( $term->name ) ) {
                        $tax_atual = strtolower( $term->taxonomy );
                        $nome_tax_atual = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
                    }
                }
                if ( !empty($_GET['contrato']) ) {
                    $filtros[] = $obter_finalidade( sanitize_text_field( $_GET['contrato'] ) );
                }
                if ( !empty($_GET['tipo-imovel']) ) {
                    $filtros[] = $obter_nome_termo( $_GET['tipo-imovel'], 'tipo-imovel' );
                } elseif ( strpos( $tax_atual, 'tipo' ) !== false ) {
                    $filtros[] = $nome_tax_atual;
                }
                if ( !empty($_GET['cidade']) ) {
                    $filtros[] = 'em ' . $obter_nome_termo( $_GET['cidade'], 'cidade' );
                } elseif ( strpos( $tax_atual, 'cidade' ) !== false ) {
                    $filtros[] = 'em ' . $nome_tax_atual;
                }
                if ( !empty($_GET['regiao']) ) {
                    $filtros[] = 'de ' . $obter_nome_termo( $_GET['regiao'], 'regiao' );
                } elseif ( strpos( $tax_atual, 'regiao' ) !== false ) {
                    $filtros[] = 'de ' . $nome_tax_atual;
                }
                $val_inicial = isset( $_GET['valor-inicial'] ) ? (float) $_GET['valor-inicial'] : 0;
                $val_final = isset( $_GET['valor-final'] ) ? (float) $_GET['valor-final'] : 0;
                if ( $val_inicial > 0 && $val_final > 0 && $val_inicial > $val_final ) {
                    $temp = $val_inicial;
                    $val_inicial = $val_final;
                    $val_final = $temp;
                }
                if ( $val_inicial > 0 || $val_final > 0 ) {
                    $str_inicial = 'R$ ' . number_format( $val_inicial, 2, ',', '.' );
                    $str_final = 'R$ ' . number_format( $val_final, 2, ',', '.' );
                    if ( $val_inicial > 0 && $val_final > 0 ) {
                        $filtros[] = "com valores a partir de {$str_inicial} até {$str_final}";
                    } elseif ( $val_inicial > 0 ) {
                        $filtros[] = "com valores a partir de {$str_inicial}";
                    } elseif ( $val_final > 0 ) {
                        $filtros[] = "com valores até {$str_final}";
                    }
                }
                $termo = !empty($filtros) ? implode( ' ', $filtros ) : "Avançada";
            }
            return "Pesquisa: " . ucfirst( $termo ) . " | " . get_bloginfo( 'name' );
        };
        $gerar_titulo_post = function() {
            return get_the_title() . ' | ' . get_bloginfo( 'name' );
        };
        $gerar_titulo_pagina = function() {
            global $post;
            $slug = $post->post_name;
            switch ( $slug ) {
                case 'deixe-seu-imovel':
                    return "Deixe seu Imóvel com a Haddad | Avaliação e Administração Segura";
                case 'trabalhe-conosco':
                    return "Trabalhe Conosco | Vagas na " . get_bloginfo( 'name' );
                case 'contato':
                    return "Fale Conosco | Atendimento " . get_bloginfo( 'name' );
                default:
                    return get_the_title() . ' | ' . get_bloginfo( 'name' );
            }
        };
        $gerar_titulo_taxonomia = function() use ( $title ) {
            $termo_atual = get_queried_object();
            if ( isset( $termo_atual->taxonomy ) && isset( $termo_atual->name ) && !empty( $termo_atual->name ) ) {
                $nome_formatado = mb_convert_case( $termo_atual->name, MB_CASE_TITLE, 'UTF-8' );
                switch ( $termo_atual->taxonomy ) {
                    case 'tipo-imovel':
                        return $nome_formatado . " | " . get_bloginfo( 'name' );
                    case 'cidade':
                        return "Imóveis em " . $nome_formatado . " | " . get_bloginfo( 'name' );
                    case 'regiao':
                        $parent_slug = get_term_meta( $termo_atual->term_id, 'parent_id', true );
                        if ( !empty( $parent_slug ) ) {
                            $cidade_term = get_term_by( 'slug', $parent_slug, 'cidade' );
                            if ( $cidade_term && !is_wp_error( $cidade_term ) ) {
                                $cidade_nome = mb_convert_case( $cidade_term->name, MB_CASE_TITLE, 'UTF-8' );
                                return "Imóveis em {$cidade_nome} • {$nome_formatado} | " . get_bloginfo( 'name' );
                            }
                        }
                        return "Imóveis em " . $nome_formatado . " | " . get_bloginfo( 'name' );
                    default:
                        return "Imóveis em " . $nome_formatado . " | " . get_bloginfo( 'name' );
                }
            }
            return $title;
        };
/*
        $is_development_mode = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || wp_get_environment_type() === 'development';
        if ( $is_development_mode ) {
            $debug = "DEBUG | ";
            $debug .= "is_front_page: " . ( is_front_page() ? 'true' : 'false' ) . " | ";
            $debug .= "is_home: " . ( is_home() ? 'true' : 'false' ) . " | ";
            $debug .= "is_singular('imovel'): " . ( is_singular( 'imovel' ) ? 'true' : 'false' ) . " | ";
            $debug .= "is_search: " . ( is_search() ? 'true' : 'false' ) . " | ";
            $debug .= "is_page('pesquisa'): " . ( is_page( 'pesquisa' ) ? 'true' : 'false' ) . " | ";
            $debug .= "is_singular('post'): " . ( is_singular( 'post' ) ? 'true' : 'false' ) . " | ";
            $debug .= "is_page: " . ( is_page() ? 'true' : 'false' ) . " | ";
            $debug .= "is_tax: " . ( is_tax() ? 'true' : 'false' ) . " | ";
            $debug .= "is_category: " . ( is_category() ? 'true' : 'false' ) . " | ";
            $debug .= "is_archive: " . ( is_archive() ? 'true' : 'false' ) . " | ";
            $debug .= "is_post_type_archive('imovel'): " . ( is_post_type_archive( 'imovel' ) ? 'true' : 'false' );
            var_dump( $debug );
        }
 */
        // ==============================================================
        // 2. ROTEAMENTO (Avaliação e Invocação)
        // ==============================================================
        $eh_pesquisa = is_search() || is_page( 'pesquisa' ) || ( ( is_home() || is_front_page() ) && isset( $_GET['tipo_pesquisa_submit'] ) );
        if ( $eh_pesquisa ) {
            return $gerar_titulo_pesquisa();
        }
        if ( is_tax() || is_category() ) {
            return $gerar_titulo_taxonomia();
        }
        if ( is_front_page() || is_home() ) {
            return $gerar_titulo_home();
        }
        if ( is_singular( 'imovel' ) ) {
            return $gerar_titulo_imovel();
        }
        if ( is_singular( 'post' ) ) {
            return $gerar_titulo_post();
        }
        if ( is_page() ) {
            return $gerar_titulo_pagina();
        }
        return $title;
    }

    /**
     * PASSO C: Injeção de Meta Description e Open Graph
     * Arquitetura baseada em Closures e Roteamento de Condições.
     */
    function injetar_metas_seo() {
        $renderizar_og_imovel = function() {
            global $post;
            $og_title = get_the_title();
            $tipo_imovel_fmt = isset( $post->tipoImovelNome ) ? mb_convert_case( $post->tipoImovelNome, MB_CASE_TITLE, 'UTF-8' ) : '';
            $cidade_fmt = ( isset( $post->cidade ) && !empty( $post->cidade ) ) ? mb_convert_case( $post->cidade, MB_CASE_TITLE, 'UTF-8' ) : '';
            $regiao_fmt = '';
            if ( isset( $post->regiao ) && !empty( $post->regiao ) ) {
                $regiao_limpa = preg_replace( '/^\d+\s*/', '', $post->regiao );
                $regiao_fmt = mb_convert_case( trim( $regiao_limpa ), MB_CASE_TITLE, 'UTF-8' );
                $excecoes = array( ' Ll ' => ' II ', ' Ii ' => ' II ', ' Sp ' => ' SP ' );
                $regiao_fmt = trim( strtr( ' ' . $regiao_fmt . ' ', $excecoes ) );
            }
            $texto_base_manual = has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : wp_trim_words( strip_tags( get_the_content() ), 40, '' );
            $gerador_descricao_inteligente = function( $texto_manual ) use ( $post, $tipo_imovel_fmt, $regiao_fmt, $cidade_fmt ) {
                $quartos = ''; $vagas = ''; $suites = '';
                if ( function_exists('get_meta_value') ) {
                    $q = get_meta_value( $post, 'quartos' );
                    if ( empty($q['valor']) ) $q = get_meta_value( $post, 'dormitorio' );
                    $quartos = !empty($q['valor']) ? $q['valor'] : '';
                    $s = get_meta_value( $post, 'suites' );
                    $suites = !empty($s['valor']) ? $s['valor'] : '';
                    $v = get_meta_value( $post, 'garage' );
                    $vagas = !empty($v['valor']) ? $v['valor'] : '';
                }
                $valor = 0;
                $prefixo_preco = 'por';
                if ( isset( $post->ativarVenda ) && '1' == $post->ativarVenda && !empty( $post->vendaValor ) ) {
                    $valor = floatval( $post->vendaValor );
                } elseif ( isset( $post->ativarLancamento ) && '1' == $post->ativarLancamento && !empty( $post->lancamentoValor ) ) {
                    $valor = floatval( $post->lancamentoValor );
                } elseif ( isset( $post->ativarLocacao ) && '1' == $post->ativarLocacao && !empty( $post->locacaoValor ) ) {
                    $valor = floatval( $post->locacaoValor );
                    $prefixo_preco = 'para locação por';
                }
                $preco_fmt = '';
                if ( $valor > 0 ) {
                    $preco_fmt = $prefixo_preco . ' R$ ' . number_format( $valor, 2, ',', '.' );
                    $preco_fmt = str_replace( ',00', '', $preco_fmt ); // Encurta valores redondos
                }
                $mecanica = $tipo_imovel_fmt;
                if ( !empty($regiao_fmt) ) $mecanica .= " em {$regiao_fmt}";
                if ( !empty($cidade_fmt) ) $mecanica .= ", {$cidade_fmt}";
                $atributos = [];
                if ( !empty($quartos) ) {
                    $attr_q = "{$quartos} quarto(s)";
                    if ( !empty($suites) ) $attr_q .= " ({$suites} suíte)";
                    $atributos[] = $attr_q;
                }
                if ( !empty($vagas) ) {
                    $atributos[] = "{$vagas} vaga(s)";
                }
                if ( !empty($atributos) ) {
                    $mecanica .= " com " . implode( ' e ', $atributos );
                }
                if ( !empty($preco_fmt) ) {
                    $mecanica .= " " . $preco_fmt;
                }
                $mecanica .= ".";
                $texto_limpo = trim( str_replace( ['[&hellip;]', '[...]'], '', $texto_manual ) );
                $is_contraprodutivo = false;
                if ( mb_strlen( $texto_limpo, 'UTF-8' ) < 30 ) $is_contraprodutivo = true;
                if ( preg_match( '/[A-Z]{15,}/', $texto_limpo ) ) $is_contraprodutivo = true;
                $options = get_option( 'pinedu_imovel_options', [] );
                $forcar_mecanica = !isset( $options['usar_descricao_do_imovel'] ) || $is_contraprodutivo;
                if ( $forcar_mecanica ) {
                    $descricao_final = $mecanica . " Confira os detalhes completos e agende sua visita na " . get_bloginfo('name') . ".";
                } else {
                    $descricao_final = $mecanica . " " . $texto_limpo;
                }
                if ( mb_strlen( $descricao_final, 'UTF-8' ) > 155 ) {
                    $descricao_final = mb_substr( $descricao_final, 0, 152, 'UTF-8' ) . '...';
                }
                return $descricao_final;
            };
            $og_description = $gerador_descricao_inteligente( $texto_base_manual );
            $og_url = get_permalink();
            $og_type = 'product.item';
            $og_site_name = get_bloginfo( 'name' );
            $og_image = '';
            $image_id = null;
            if ( has_post_thumbnail() ) {
                $og_image = get_the_post_thumbnail_url( $post->ID, 'full' );
                $image_id = get_post_thumbnail_id( $post->ID );
            } else {
                $gallery = get_post_gallery( $post->ID, false );
                if ( !empty( $gallery['src'] ) && is_array( $gallery['src'] ) ) {
                    $og_image = $gallery['src'][0];
                    if ( !empty( $gallery['ids'] ) ) {
                        $gallery_ids = explode( ',', $gallery['ids'] );
                        $image_id = $gallery_ids[0];
                    }
                }
            }
            echo "\n<!-- Meta Tags SEO e Open Graph (Facebook/Instagram/LinkedIn) -->\n";
            echo '<meta name="description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            echo '<meta name="author" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
            echo '<meta property="og:locale" content="pt_BR" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
            echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            if ( !empty( $og_image ) ) {
                echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
                if ( $image_id ) {
                    $image_meta = wp_get_attachment_image_src( $image_id, 'large' );
                    if ( $image_meta ) {
                        echo '<meta property="og:image:width" content="' . esc_attr( $image_meta[1] ) . '" />' . "\n";
                        echo '<meta property="og:image:height" content="' . esc_attr( $image_meta[2] ) . '" />' . "\n";
                    }
                } else {
                    echo '<meta property="og:image:width" content="1280" />' . "\n";
                    echo '<meta property="og:image:height" content="720" />' . "\n";
                }
            }
            $options = get_option( 'pinedu_imovel_options', [] );
            if ( isset( $options['facebook_app_id'] ) && !empty( $options['facebook_app_id'] ) ) {
                echo '<meta property="fb:app_id" content="' . esc_attr( $options['facebook_app_id'] ) . '" />' . "\n";
            }
            echo "\n<!-- Twitter Card Meta Tags -->\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            if ( !empty( $og_image ) ) {
                echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
            }
            echo '<meta property="product:retailer_item_id" content="' . esc_attr( $post->referencia ) . '" />' . "\n";
            if ( isset( $post->novo ) && !empty( $post->novo ) && '1' == $post->novo ) {
                echo '<meta property="product:condition" content="new" />' . "\n";
            }
            if ( function_exists('getEmpresa') ) {
                $empresa = getEmpresa( 1 );
                if ( !empty( $empresa ) ) echo '<meta property="product:brand" content="' . esc_attr( $empresa->codNome ) . '" />' . "\n";
            }
            $preco = '';
            if ( isset( $post->ativarVenda ) && '1' == $post->ativarVenda && !empty( $post->vendaValor ) ) {
                $preco = $post->vendaValor;
                echo '<meta property="og:availability" content="for_sale" />' . "\n";
            } elseif ( isset( $post->ativarLancamento ) && '1' == $post->ativarLancamento && !empty( $post->lancamentoValor ) ) {
                echo '<meta property="og:availability" content="for_sale" />' . "\n";
                $preco = $post->lancamentoValor;
            } elseif ( isset( $post->ativarLocacao ) && '1' == $post->ativarLocacao && !empty( $post->locacaoValor ) ) {
                echo '<meta property="og:availability" content="for_rent" />' . "\n";
                $preco = $post->locacaoValor;
            }
            echo '<meta property="og:property:status" content="Disponível" />' . "\n";
            echo '<meta property="product:availability" content="in stock" />' . "\n";
            $matriz = [
                [ 'tag' => 'og:property:bedrooms', 'label' => 'dormitorio' ],
                [ 'tag' => 'og:property:bedrooms', 'label' => 'quartos' ],
                [ 'tag' => 'og:property:bathrooms', 'label' => 'banheiros' ],
                [ 'tag' => 'og:property:suites', 'label' => 'suites' ],
                [ 'tag' => 'og:property:parking_spaces', 'label' => 'garage' ],
                [ 'tag' => 'og:property:furnished', 'label' => 'mobiliado' ],
                [ 'tag' => 'og:property:pets_allowed', 'label' => 'aceita pets' ],
                [ 'tag' => 'og:property:floor', 'label' => 'numero do andar' ],
                [ 'tag' => 'og:property:total_floors', 'label' => 'andares' ]
            ];
            if ( function_exists('get_meta_value') ) {
                foreach ( $matriz as $m ) {
                    $area_struct = get_meta_value( $post, $m['label'] );
                    if ( $area_struct && isset( $area_struct['valor'] ) && !empty( $area_struct['valor'] ) ) {
                        echo '<meta property="' . $m['tag'] . '" content="' . esc_attr( $area_struct['valor'] ) . '" />' . "\n";
                    }
                }
                $area_struct = get_meta_value( $post, 'area util' );
                if ( !$area_struct || empty( $area_struct['valor'] ) ) {
                    $area_struct = get_meta_value( $post, 'area total' );
                }
                if ( $area_struct && isset( $area_struct['valor'] ) && !empty( $area_struct['valor'] ) && is_numeric( $area_struct['valor'] ) ) {
                    echo '<meta property="product:size" content="' . esc_attr( $area_struct['valor'] ) . ' m²" />' . "\n";
                    echo '<meta property="og:product:size" content="' . esc_attr( $area_struct['valor'] ) . '" />' . "\n";
                    if ( floatval( $area_struct['valor'] ) > 0 && !empty( $preco ) ) {
                        echo '<meta property="og:property:price_per_sqm" content="' . intval( floatval( $preco ) / floatval( $area_struct['valor'] ) ) . '" />' . "\n";
                    }
                }
            }
            if ( !empty( $preco ) ) {
                echo '<meta property="og:price:amount" content="' . esc_attr( $preco ) . '" />' . "\n";
                echo '<meta property="og:price:currency" content="BRL" />' . "\n";
            }
            $ano = isset($post->anoConstrucao) ? $post->anoConstrucao : '';
            if ( !empty( $ano ) ) echo '<meta property="og:property:year_built" content="' . esc_attr( $ano ) . '" />' . "\n";
            $condominio = isset($post->valorCondominio) ? $post->valorCondominio : '';
            if ( !empty( $condominio ) ) echo '<meta property="og:property:condo_fee" content="' . esc_attr( $condominio ) . '" />' . "\n";
            $iptu = isset($post->valorIptu) ? $post->valorIptu : '';
            if ( !empty( $iptu ) ) echo '<meta property="og:property:property_tax" content="' . esc_attr( $iptu ) . '" />' . "\n";
            if ( !empty( $cidade_fmt ) ) echo '<meta property="og:locality" content="' . esc_attr( $cidade_fmt ) . '" />' . "\n";
            if ( !empty( $post->estado ) ) echo '<meta property="og:region" content="' . esc_attr( $post->estado ) . '" />' . "\n";
            if ( !empty( $post->cep ) && function_exists('formata_cep') ) echo '<meta property="og:postal-code" content="' . esc_attr( formata_cep( $post->cep ) ) . '" />' . "\n";
            echo '<meta property="og:country-name" content="Brasil" />' . "\n";
            if ( !empty( $post->latitude ) && !empty( $post->longitude ) && ( floatval( $post->latitude ) != 0 ) && ( floatval( $post->longitude ) != 0 ) ) {
                echo '<meta property="og:latitude" content="' . esc_attr( $post->latitude ) . '" />' . "\n";
                echo '<meta property="og:longitude" content="' . esc_attr( $post->longitude ) . '" />' . "\n";
            }
            if ( !empty( $tipo_imovel_fmt ) ) echo '<meta property="product:category" content="' . esc_attr( $tipo_imovel_fmt ) . '" />' . "\n";
            if ( isset( $post->referencia ) ) echo '<meta property="product:item_group_id" content="' . esc_attr( $post->referencia ) . '" />' . "\n";
            echo "<!-- End Open Graph Meta Tags -->\n\n";
        };
        $renderizar_og_post_padrao = function() {
            global $post;
            $og_title = get_the_title();
            $og_description = has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : wp_trim_words( strip_tags( get_the_content() ), 30, '...' );
            $og_url = get_permalink();
            $og_site_name = get_bloginfo( 'name' );
            $og_image = has_post_thumbnail() ? get_the_post_thumbnail_url( $post->ID, 'full' ) : '';
            echo "\n<!-- Meta Tags SEO e Open Graph -->\n";
            echo '<meta name="description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            echo '<meta name="author" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
            echo '<meta property="og:locale" content="pt_BR" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
            echo '<meta property="og:type" content="article" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $og_site_name ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
            if ( !empty( $og_image ) ) echo '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
            echo "<!-- End Open Graph Meta Tags -->\n\n";
        };
        $renderizar_og_home = function() {
            $titulo = wp_get_document_title();
            $options = get_option( 'pinedu_imovel_options', [] );
            $cidade_padrao = isset( $options['cidade'] ) ? $options['cidade'] : '';
            $cidade = 'Birigui';
            if ( !empty( $cidade_padrao ) ) {
                $cid = get_term_by( 'slug', $cidade_padrao, 'cidade' );
                if ( $cid ) {
                    $cidade = mb_convert_case( $cid->name, MB_CASE_TITLE, 'UTF-8' );
                }
            }
            $descricao = "Especializada em imóveis de {$cidade} e região. Encontre a casa, apartamento ou imóvel comercial ideal com a segurança e transparência que a sua família merece.";
            $url = home_url( '/' );
            $site_name = get_bloginfo( 'name' );
            $imagem = get_template_directory_uri() . '/images/logofinal.png'; // Caminho da sua logo

            echo "\n<!-- Meta Tags SEO e Open Graph (Home) -->\n";
            echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:locale" content="pt_BR" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta property="og:image" content="' . esc_url( $imagem ) . '" />' . "\n";
            if ( isset( $options['facebook_app_id'] ) && !empty( $options['facebook_app_id'] ) ) {
                echo '<meta property="fb:app_id" content="' . esc_attr( $options['facebook_app_id'] ) . '" />' . "\n";
            }
            echo "\n<!-- Twitter Card Meta Tags -->\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
            echo "<!-- End Open Graph Meta Tags -->\n\n";
        };
        $renderizar_og_pesquisa = function() {
            $titulo = wp_get_document_title();
            // Remove o nome da empresa do título para criar uma descrição limpa
            $termo_pesquisa = trim( str_replace( ' | ' . get_bloginfo( 'name' ), '', $titulo ) );
            $descricao = "Confira as melhores opções para " . $termo_pesquisa . ". A Haddad Imóveis tem o imóvel perfeito para você com segurança e transparência.";

            $url = home_url( $_SERVER['REQUEST_URI'] );
            $site_name = get_bloginfo( 'name' );
            $imagem = get_template_directory_uri() . '/images/logofinal.png';

            echo "\n<!-- Meta Tags SEO e Open Graph (Pesquisa Dinâmica) -->\n";
            echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:locale" content="pt_BR" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta property="og:image" content="' . esc_url( $imagem ) . '" />' . "\n";

            $options = get_option( 'pinedu_imovel_options', [] );
            if ( isset( $options['facebook_app_id'] ) && !empty( $options['facebook_app_id'] ) ) {
                echo '<meta property="fb:app_id" content="' . esc_attr( $options['facebook_app_id'] ) . '" />' . "\n";
            }

            echo "\n<!-- Twitter Card Meta Tags -->\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
            echo "<!-- End Open Graph Meta Tags -->\n\n";
        };
        $renderizar_og_taxonomia = function() {
            $termo_atual = get_queried_object();
            $titulo = wp_get_document_title();
            $site_name = get_bloginfo( 'name' );
            $url = get_term_link( $termo_atual );
            $imagem = get_template_directory_uri() . '/images/logofinal.png';

            $descricao = "Confira as melhores opções de imóveis disponíveis na " . $site_name . ".";

            if ( isset( $termo_atual->taxonomy ) && isset( $termo_atual->name ) && !empty( $termo_atual->name ) ) {
                $nome_formatado = mb_convert_case( $termo_atual->name, MB_CASE_TITLE, 'UTF-8' );

                switch ( $termo_atual->taxonomy ) {
                    case 'tipo-imovel':
                        $descricao = "Buscando por {$nome_formatado}? Confira nossa seleção de imóveis exclusivos na {$site_name}.";
                        break;
                    case 'cidade':
                        $descricao = "Encontre seu imóvel ideal em {$nome_formatado}. As melhores opções de compra e locação na {$site_name}.";
                        break;
                    case 'regiao':
                        // Usa o cruzamento para descobrir a cidade, igual fizemos no título
                        $parent_slug = get_term_meta( $termo_atual->term_id, 'parent_id', true );
                        if ( !empty( $parent_slug ) ) {
                            $cidade_term = get_term_by( 'slug', $parent_slug, 'cidade' );
                            if ( $cidade_term && !is_wp_error( $cidade_term ) ) {
                                $cidade_nome = mb_convert_case( $cidade_term->name, MB_CASE_TITLE, 'UTF-8' );
                                $descricao = "Imóveis exclusivos em {$cidade_nome}, no bairro {$nome_formatado}. Agende uma visita com a {$site_name}.";
                            } else {
                                $descricao = "Imóveis no bairro {$nome_formatado}. Agende uma visita com a {$site_name}.";
                            }
                        } else {
                            $descricao = "Imóveis no bairro {$nome_formatado}. Agende uma visita com a {$site_name}.";
                        }
                        break;
                }
            }

            echo "\n<!-- Meta Tags SEO e Open Graph (Taxonomia) -->\n";
            echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:locale" content="pt_BR" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta property="og:image" content="' . esc_url( $imagem ) . '" />' . "\n";

            $options = get_option( 'pinedu_imovel_options', [] );
            if ( isset( $options['facebook_app_id'] ) && !empty( $options['facebook_app_id'] ) ) {
                echo '<meta property="fb:app_id" content="' . esc_attr( $options['facebook_app_id'] ) . '" />' . "\n";
            }

            echo "\n<!-- Twitter Card Meta Tags -->\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
            echo "<!-- End Open Graph Meta Tags -->\n\n";
        };
        $renderizar_og_pagina = function() {
            global $post;
            $titulo = wp_get_document_title();
            $site_name = get_bloginfo( 'name' );
            $url = get_permalink();
            $imagem = get_template_directory_uri() . '/images/logofinal.png';

            // Gatilhos comerciais específicos para as páginas institucionais
            $slug = isset( $post->post_name ) ? $post->post_name : '';
            if ( $slug === 'contato' ) {
                $descricao = "Fale com a equipe da {$site_name}. Tire suas dúvidas, solicite atendimento e encontre as melhores oportunidades no mercado imobiliário.";
            } elseif ( $slug === 'deixe-seu-imovel' ) {
                $descricao = "Quer vender ou alugar seu imóvel com segurança e rapidez? Anuncie com a {$site_name} e conte com nossa equipe de especialistas.";
            } elseif ( $slug === 'trabalhe-conosco' ) {
                $descricao = "Faça parte da equipe {$site_name}! Confira nossas oportunidades e venha construir uma carreira de sucesso no mercado imobiliário.";
            } else {
                // Fallback para outras páginas (ex: Sobre Nós, Política de Privacidade)
                $descricao = has_excerpt() ? wp_strip_all_tags( get_the_excerpt() ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' );
                if ( empty( $descricao ) ) {
                    $descricao = "Saiba mais informações sobre a {$site_name}, sua parceira de confiança em negócios imobiliários.";
                }
            }

            echo "\n<!-- Meta Tags SEO e Open Graph (Páginas Institucionais) -->\n";
            echo '<meta name="description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:locale" content="pt_BR" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta property="og:image" content="' . esc_url( $imagem ) . '" />' . "\n";

            $options = get_option( 'pinedu_imovel_options', [] );
            if ( isset( $options['facebook_app_id'] ) && !empty( $options['facebook_app_id'] ) ) {
                echo '<meta property="fb:app_id" content="' . esc_attr( $options['facebook_app_id'] ) . '" />' . "\n";
            }

            echo "\n<!-- Twitter Card Meta Tags -->\n";
            echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr( $descricao ) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '" />' . "\n";
            echo "<!-- End Open Graph Meta Tags -->\n\n";
        };
        // ==============================================================
        // 2. ROTEAMENTO (Avaliação e Invocação)
        // ==============================================================
        $eh_pesquisa = is_search() || is_page( 'pesquisa' ) || ( ( is_home() || is_front_page() ) && isset( $_GET['tipo_pesquisa_submit'] ) );
        if ( $eh_pesquisa ) {
            return $renderizar_og_pesquisa();
        }
        if ( is_tax() || is_category() ) {
            return $renderizar_og_taxonomia();
        }
        if ( is_front_page() || is_home() ) {
            return $renderizar_og_home();
        }
        if ( is_singular( 'imovel' ) ) {
            return $renderizar_og_imovel();
        }
        if ( is_singular( 'post' ) ) {
            return $renderizar_og_post_padrao();
        }
        if ( is_page() ) {
            return $renderizar_og_pagina();
        }
    }


}