<?php

use JetBrains\PhpStorm\NoReturn;

require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-testar-server.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importar-basicos.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importar-imoveis.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-imovel.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-empresa.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-loja.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-corretor.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-contrato.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-tipo-imovel.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-cidade.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-faixa-valor.php';
require_once plugin_dir_path( __FILE__ ) . './class-pinedu-imovel-importa-tipo-dependencia.php';
require_once plugin_dir_path( __FILE__ ) . './PineduRequest.php';
class PineduImportarFrontEnd {
    private static ?Pinedu_Imovel_Importar $instancia_importar = null;
    const PREFIXO_ADMIN = 'wp_ajax_';
    const PREFIXO = 'wp_ajax_nopriv_';
    const HOOK_IMPORTACAO = 'PINEDU_EXECUTAR_IMPORTACAO';
    const HOOK_IMPORTACAO_PRELOGIN = 'IMPORTACAO_PRELOGIN';
    const HOOK_IMPORTACAO_POSLOGIN = 'IMPORTACAO_POSLOGIN';
    const HOOK_INICIALIZAR = 'IMPORTA_FRONTEND_INICIALIZAR';
    const HOOK_PREPARAR_BASICOS = 'IMPORTA_FRONTEND_PREPARAR_BASICOS';
    const HOOK_PREPARAR_IMOVEIS = 'IMPORTA_FRONTEND_PREPARAR_IMOVEIS';
    const HOOK_IMPORTAR_IMOVEIS = 'IMPORTA_FRONTEND_IMPORTAR_IMOVEIS';
    const HOOK_IMPORTAR_IMOVEIS_JSON = 'IMPORTA_FRONTEND_IMPORTAR_IMOVEIS_JSON';
    const HOOK_PREPARAR_EXCLUIR_IMOVEIS = 'PREPARAR_EXCLUIR_IMOVEIS';
    const HOOK_RECUPERA_EXCLUIDOS_FROM_JSON = 'RECUPERA_EXCLUIDOS_FROM_JSON';
    const HOOK_EXCLUIR_IMOVEIS = 'IMPORTA_FRONTEND_EXCLUIR_IMOVEIS';
    const HOOK_PREPARAR_IMAGEM_DESTAQUE = 'PREPARA_IMAGEM_DESTAQUE';
    const HOOK_FINALIZAR_IMAGEM_DESTAQUE = 'FINALIZA_IMAGEM_DESTAQUE';
    const HOOK_IMPORTAR_IMAGEM_DESTAQUE = 'IMPORTA_IMAGEM_DESTAQUE';
    const HOOK_RETIFICAR_IMAGEM_DESTAQUE = 'RETIFICA_IMAGEM_DESTAQUE';
    const HOOK_FINALIZAR_IMPORTACAO = 'FINALIZA_IMPORTACAO';
    const HOOK_IMPORTAR_CIDADE = 'IMPORTA_FRONTEND_IMPORTAR_CIDADE';
    const HOOK_IMPORTAR_EMPRESA = 'IMPORTA_FRONTEND_IMPORTAR_EMPRESA';
    CONST HOOK_IMPORTAR_PARAMETRO_EMPRESA = 'IMPORTA_PARAMETRO_EMPRESA';
    const HOOK_IMPORTAR_LOJA = 'IMPORTA_FRONTEND_IMPORTAR_LOJA';
    const HOOK_IMPORTAR_CORRETOR = 'IMPORTA_FRONTEND_IMPORTAR_CORRETOR';
    const HOOK_IMPORTAR_CONTRATO = 'IMPORTA_FRONTEND_IMPORTAR_CONTRATO';
    const HOOK_IMPORTAR_TIPO_IMOVEL = 'IMPORTA_FRONTEND_IMPORTAR_TIPO_IMOVEL';
    const HOOK_IMPORTAR_FAIXA_VALOR = 'IMPORTA_FRONTEND_IMPORTAR_FAIXA_VALOR';
    const HOOK_IMPORTAR_TIPO_DEPENDENCIA = 'IMPORTA_FRONTEND_IMPORTAR_TIPO_DEPENDENCIA';
    const HOOK_IMPORTAR_APAGAR_TODOS_IMOVEIS = 'IMPORTA_FRONTEND_APAGAR_TODOS_IMOVEIS';
    public static function init( ) {
        if ( !is_admin( ) ) return;
        add_action( self::PREFIXO_ADMIN . self::HOOK_INICIALIZAR, [ __CLASS__, 'testar_server' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTACAO_PRELOGIN, [ __CLASS__, 'pre_login' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTACAO_POSLOGIN, [ __CLASS__, 'pos_login' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_INICIALIZAR, [ __CLASS__, 'testar_server' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_BASICOS, [ __CLASS__, 'importar_basicos' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_IMOVEIS, [ __CLASS__, 'preparar_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_IMOVEIS, [ __CLASS__, 'importar_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_IMOVEIS_JSON, [ __CLASS__, 'importar_imoveis_json' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_EXCLUIR_IMOVEIS, [ __CLASS__, 'excluir_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_EXCLUIR_IMOVEIS, [ __CLASS__, 'preparar_excluir_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_RECUPERA_EXCLUIDOS_FROM_JSON, [ __CLASS__, 'recupera_post_id_from_referencias_json' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_IMAGEM_DESTAQUE, [ __CLASS__, 'preparar_imagem_destaque' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_FINALIZAR_IMAGEM_DESTAQUE, [ __CLASS__, 'finalizar_imagem_destaque' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_IMAGEM_DESTAQUE, [ __CLASS__, 'importar_imagem_destaque' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_RETIFICAR_IMAGEM_DESTAQUE, [ __CLASS__, 'retificar_imagem_destaque' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_FINALIZAR_IMPORTACAO, [ __CLASS__, 'finalizar_importacao' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_CIDADE, [ __CLASS__, 'importar_cidade' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_EMPRESA, [ __CLASS__, 'importar_empresa' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_PARAMETRO_EMPRESA, [ __CLASS__, 'importar_parametros_empresa' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_LOJA, [ __CLASS__, 'importar_lojas'], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_CORRETOR, [ __CLASS__, 'importar_corretor' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_CONTRATO, [ __CLASS__, 'importar_contrato' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_TIPO_IMOVEL, [ __CLASS__, 'importar_tipo_imovel' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_FAIXA_VALOR, [ __CLASS__, 'importar_faixa_valor' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_TIPO_DEPENDENCIA, [ __CLASS__, 'importar_tipo_dependencia' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_APAGAR_TODOS_IMOVEIS, [ __CLASS__, 'apagar_todos_imoveis' ], 10 );

        add_filter( 'heartbeat_settings', [ __CLASS__, 'custom_heartbeat_settings'] );
    }
    public static function apagar_todos_imoveis( ) {

        // 1. Configuração da WP_Query para obter APENAS os IDs
        $args = array(
            'post_type' => 'imovel',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'any'
        );

        $query_imoveis = new WP_Query($args);
        $imovel_ids = $query_imoveis->posts;
        $deleted_count = 0;
        $deleted_error_count = 0;
        $qtde = count( $query_imoveis->posts );
        $deleted_ids = [];

        if (!empty($imovel_ids)) {
            foreach ($imovel_ids as $post_id) {
                $thumbnail_id = get_post_thumbnail_id( $post_id );
                if ( $thumbnail_id ) {
                    wp_delete_attachment( $thumbnail_id, true );
                }
                $fotografias_imovel = get_post_meta( $post_id, 'fotografias', false );
                if ( ! empty( $fotografias_imovel ) ) {
                    foreach ( $fotografias_imovel as $fotografia ) {
                        if ( is_development_mode( ) ) {
                            error_log('PineduImportarFrontEnd:apagar_todos_imoveis:fotografia: ' . print_r($fotografia, true));
                        }
                        if ( isset( $fotografia['id'] ) ) {
                            $attachment_id = (int) $fotografia['id'];
                            if ( $attachment_id ) {
                                wp_delete_attachment( $attachment_id, true );
                            }
                        }
                    }
                }
                $result = wp_delete_post($post_id, true);
                if ($result !== false) {
                    $deleted_count++;
                    $deleted_ids[] = $post_id;
                } else {
                    $deleted_error_count++;
                }
            }
        }
        wp_reset_postdata();
        wp_send_json( [
            'success' => true,
            'total' => $qtde,
            'excluidos' => $deleted_count,
            'erro_excluidos' => $deleted_error_count,
        ] );
    }
    public static function custom_heartbeat_settings( $settings ) {
        $settings['interval'] = 60;
        return $settings;
    }

    public static function getImportador( ): Pinedu_Imovel_Importar {
        if ( self::$instancia_importar === null ) {
            self::$instancia_importar = new Pinedu_Imovel_Importar( );
        }
        return self::$instancia_importar;
    }

    public static function importar_empresa( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_empresa' );
        }
        $empresa_json = $_POST['empresa'] ?? null;
        $empresa_json = stripslashes( $empresa_json );
        if ( $empresa_json ) {
            $empresa = json_decode( $empresa_json, true ); // agora é array associativo
        } else {
            $empresa = null;
        }
        if ( $empresa === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Empresa não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $empresa_importa = new Pinedu_Imovel_Importa_Empresa( );
        $empresa_importa->importa( $empresa );

        wp_send_json( [
            'success' => true,
            'message' => 'Dados Empresa importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_parametros_empresa( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_parametros_empresa' );
        }
        $parametros_json = $_POST['parametros'] ?? null;
        $parametros_json = stripslashes( $parametros_json );
        if ( $parametros_json ) {
            $parametros = json_decode( $parametros_json, true ); // agora é array associativo
        } else {
            $parametros = null;
        }
        if ( $parametros === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Empresa não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_parametros_empresa: ' .print_r( $parametros, true ) );
        }
        $parametros_importa = new Pinedu_Imovel_Importar_Basicos( );
        $parametros_importa->importa_parametros_data( $parametros );
        $options = get_option( 'pinedu_imovel_options', [ ] );
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_parametros_empresa_1' .print_r( $options, true ) );
        }
        wp_send_json( [
            'success' => true,
            'message' => 'Parametros da Empresa importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_cidade( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_cidade' );
        }
        $cidades_json = $_POST['cidades'] ?? null;
        $cidades_json = stripslashes( $cidades_json );
        if ( $cidades_json ) {
            $cidades = json_decode( $cidades_json, true ); // agora é array associativo
        } else {
            $cidades = null;
        }
        if ( $cidades === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Cidade não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $importa_cidades = new Pinedu_Imovel_Importa_Cidade( );
        $importa_cidades->importa_cidades( $cidades );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados das Cidades importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_lojas( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_lojas' );
        }
        $lojas_json = $_POST['lojas'] ?? null;
        $lojas_json = stripslashes( $lojas_json );
        if ( $lojas_json ) {
            $lojas = json_decode( $lojas_json, true ); // agora é array associativo
        } else {
            $lojas = null;
        }
        if ( $lojas === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Loja não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $importa_loja = new Pinedu_Imovel_Importa_Loja( );
        $importa_loja->importar( $lojas );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados das Lojas importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_corretor( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_corretor' );
        }
        $corretores_json = $_POST['corretores'] ?? null;
        $corretores_json = stripslashes( $corretores_json );
        if ( $corretores_json ) {
            $corretores = json_decode( $corretores_json, true ); // agora é array associativo
        } else {
            $corretores = null;
        }
        if ( $corretores === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó corretores não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $importa_corretor = new Pinedu_Imovel_Importa_Corretor( );
        $importa_corretor->importar( $corretores );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos corretores importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_contrato( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_contrato' );
        }
        $tipo_contratos_json = $_POST['tipo_contratos'] ?? null;
        $tipo_contratos_json = stripslashes( $tipo_contratos_json );
        if ( $tipo_contratos_json ) {
            $tipo_contratos = json_decode( $tipo_contratos_json, true ); // agora é array associativo
        } else {
            $tipo_contratos = null;
        }
        if ( $tipo_contratos === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó tipo_contratos não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $importa_contrato = new Pinedu_Imovel_Importa_Contrato( );
        $importa_contrato->importar( $tipo_contratos );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos tipo de Contratos importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_tipo_imovel( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_tipo_imovel' );
        }
        $tipo_imoveis_json = $_POST['tipo_imoveis'] ?? null;
        $tipo_imoveis_json = stripslashes( $tipo_imoveis_json );
        if ( $tipo_imoveis_json ) {
            $tipo_imoveis = json_decode( $tipo_imoveis_json, true ); // agora é array associativo
        } else {
            $tipo_imoveis = null;
        }
        if ( $tipo_imoveis === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó tipo_imoveis não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $importa_tipo_imovel = new Pinedu_Imovel_Importa_Tipo_Imovel( );
        $importa_tipo_imovel->importa_tipo_imoveis( $tipo_imoveis );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos tipo de imoveis importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_faixa_valor( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_faixa_valor' );
        }
        $faixa_valores_json = $_POST['faixa_valores'] ?? null;
        $faixa_valores_json = stripslashes( $faixa_valores_json );
        if ( $faixa_valores_json ) {
            $faixa_valores = json_decode( $faixa_valores_json, true ); // agora é array associativo
        } else {
            $faixa_valores = null;
        }
        if ( $faixa_valores === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó faixa de valores não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_faixa_valor_1' . print_r( $faixa_valores, true ) );
        }
        $importa_Faixa_valor = new Pinedu_Imovel_Importa_Faixa_Valor( );
        foreach ( $faixa_valores as $faixa ) {
            $importa_Faixa_valor->importa( $faixa );
        }
        wp_send_json( [
            'success' => true,
            'message' => 'Dados das faixas de valores importados com Sucesso!'
        ] );
        wp_die( );
    }
    public static function importar_tipo_dependencia( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_tipo_dependencias' );
        }
        $tipo_dependencias_json = $_POST['tipo_dependencias'] ?? null;
        $tipo_dependencias_json = stripslashes( $tipo_dependencias_json );
        if ( $tipo_dependencias_json ) {
            $tipo_dependencias = json_decode( $tipo_dependencias_json, true ); // agora é array associativo
        } else {
            $tipo_dependencias = null;
        }
        if ( $tipo_dependencias === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó faixa de valores não encontrado ou vazio no JSON'] );
            wp_die( );
        }
        $importa_tipo_dependencias = new Pinedu_Imovel_Importa_Tipo_Dependencia( );
        $importa_tipo_dependencias->importa( $tipo_dependencias );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos tipo de dependências importados com Sucesso!'
        ] );
        wp_die( );
    }
    #[ NoReturn ]
    public static function finalizar_importacao( ): void {
        $imoveis_importados = 0;
        $token = '';
        if ( isset( $_POST[ 'token' ] ) ) {
            $token = sanitize_text_field( $_POST[ 'token' ] );
        }
        if ( isset( $_POST[ 'imoveis_importados' ] ) ) {
            $imoveis_importados = sanitize_text_field( $_POST[ 'imoveis_importados' ] );
        }
        $options = get_option( 'pinedu_imovel_options', [ ] );
        if ( empty( $options[ 'tempo_atualizacao' ] ) ) {
            $options['tempo_atualizacao'] = 1;
        }
        self::exclui_agendamento_completo( );
        self::agendar_importacao( ( new DateTime( ) )->getTimestamp( ), intval( $options['tempo_atualizacao'] ) );
        $options[ 'imoveis_importados' ] = $imoveis_importados;
        $options[ 'ultima_atualizacao' ] = new DateTime( );
        $options[ 'token' ] = $token;
        $options[ 'proxima_atualizacao' ] = self::parse_timestamp_scheduler( self::consulta_agendamento( ) );
        $options[ 'importacao_andamento' ] = false;

        $options[ 'success' ] = true;
        update_option( 'pinedu_imovel_options', $options );
        /* Enviar estes dados para o Servidor */
        $endpoint = '/wordpress/postImport';
        $url_servidor = $options[ 'url_servidor' ];
        $fullUrl = trailingslashit( $url_servidor ) . ltrim( $endpoint, '/' );
        $args = [
            'imoveisImportados' => $imoveis_importados
            , 'dataAtualizacao' => formataData_iso8601( $options[ 'ultima_atualizacao' ] )
            , 'proximaAtualizacao' => formataData_iso8601( $options[ 'proxima_atualizacao' ] )
            , 'token' => $options[ 'token' ]
            , 'urlWordpress' => home_url()
            , 'pathIntegracao' => 'pinedu-imovel/v1'
            , 'tokenUsername' => $options[ 'token_username' ]
            , 'tokenPassword' => $options[ 'token_password' ]
        ];
        $request = new PineduRequest( );
        $request->get( $fullUrl, $args );
        wp_send_json( $options );
        wp_die( );
    }
    public static function preparar_imagem_destaque( ) {
        $meta_key_to_search = 'imagem_destaque';
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_imagem_destaque' );
        }
        $args = [
            'post_type' => 'imovel',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => $meta_key_to_search,
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_thumbnail_id',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];
        $query = new WP_Query( $args );
        $post_ids = $query->posts;
        $count = $query->post_count;
        wp_reset_postdata( );
        wp_send_json( [ 
            'success' => true,
            'ids' => $post_ids,
            'total' => $count,
        ] );
        wp_die( );
    }
    public static function retificar_imagem_destaque( ) {
        $meta_key_to_search = 'imagem_destaque';
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:retificar_imagem_destaque' );
        }
        $meta_key_destaque = 'imagem_destaque';
        $args = [
            'post_type'      => 'imovel',
            'posts_per_page' => -1, // Obter todos os posts de uma vez
            'post_status'    => 'any',
            'fields'         => 'ids', // Apenas IDs para otimizar a memória
            'meta_query'     => [
                'relation' => 'AND', // Garante que ambas as condições de NÃO EXISTÊNCIA e a de EXISTÊNCIA sejam verificadas
                [
                    'key'     => $meta_key_destaque,
                    'compare' => 'NOT EXISTS', // NÃO existe 'imagem_destaque'
                ],
                [
                    'key'     => '_thumbnail_id', // Chave interna do WordPress para a Imagem Destacada
                    'compare' => 'NOT EXISTS',    // NÃO existe a meta _thumbnail_id
                ],
                [
                    'key'     => 'fotos', // Chave para a meta que deve EXISTIR
                    'compare' => 'EXISTS',        // Verifica se a meta 'fotos' existe no post
                ],
            ],
        ];
        $posts = new WP_Query( $args );
        $posts_ids = $posts->posts;
        $count = $posts->post_count;
        if ( ! empty( $posts_ids ) ) {
            foreach ( $posts_ids as $post_id ) {
                $foto = get_post_meta( $post_id, 'fotos', true );
                if ( ! empty( $foto ) && is_array( $foto ) ) {
                    $dados_para_salvar = array( 
                        'id'          => $foto['id'],
                        'url'         => $foto['url'],
                        'alt_text'    => $foto['nome'],
                        'title'       => $foto['nome'],
                        'description' => $foto['descricao'],
                        'label'       => $foto['nome'],
                    );
                    $meta_id = add_post_meta( 
                        $post_id,
                        $meta_key_destaque,
                        $dados_para_salvar,
                        true
                    );
                }
            }
        }
        wp_reset_postdata( );
        wp_send_json( [
            'success' => true,
            'total' => $count,
        ] );
        wp_die( );
    }
    public static function finalizar_imagem_destaque( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_imagem_destaque' );
        }
        $meta_key_to_delete = 'imagem_destaque';
        $args = [
            'post_type'      => 'imovel',
            'posts_per_page' => -1, // Obter todos os posts de uma vez
            'post_status'    => 'any',
            'fields'         => 'ids', // Apenas IDs para otimizar a memória
            'meta_query'     => [
                [
                    'key'     => $meta_key_to_delete,
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_thumbnail_id', // Chave interna do WordPress para a Imagem Destacada
                    'compare' => 'EXISTS',        // Verifica se a meta _thumbnail_id existe no post
                ],
            ],
        ];
        $posts_com_meta = new WP_Query( $args );
        $posts_ids = $posts_com_meta->posts;
        $count = 0;
        if ( ! empty( $posts_ids ) ) {
            foreach ( $posts_ids as $post_id ) {
                $deleted = delete_post_meta( $post_id, $meta_key_to_delete );
                if ( $deleted ) {
                    $count++;
                }
            }
        }
        wp_reset_postdata( );
        wp_send_json( [
            'success' => true,
            'total' => $count,
        ] );
        wp_die( );
    }
    public static function importar_imagem_destaque( ) {
        $meta_key_to_search = 'imagem_destaque';
        $max = 50;
        $offset = 0;
        if ( isset( $_POST[ 'offset' ] ) ) {
            $offset = intval( $_POST[ 'offset' ] );
        }
        if ( isset( $_POST[ 'max' ] ) ) {
            $max = intval( $_POST[ 'max' ] );
        }
        $ids = [];

        if ( isset( $_POST['ids'] ) && is_string( $_POST['ids'] ) ) {
            $ids_string = wp_unslash( $_POST['ids'] );
            $decoded = json_decode( $ids_string, true );
            if ( json_last_error( ) === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $ids = array_map( 'intval', $decoded );
            }
        }
        if ( empty( $ids ) ) {
            wp_send_json( [
                'success' => false,
                'message' => 'Nenhum imóvel para excluir!'
            ] );
            wp_die( );
        }

        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_imagem_destaque' );
        }
        $args = [
            'post_type'      => 'imovel',
            'post_status'    => 'any',
            'posts_per_page' => $max,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'post__in'       => $ids,
        ];
        $query = new WP_Query( $args );
        $result = true;
        if ( $query->have_posts( ) ) {
            $result = baixar_fotos_destaque( $query, false );
        }
        $count = $query->post_count;
        wp_reset_postdata( );
        wp_send_json( [
            'success' => ( $count > 0 ),
            'returned' => $count,
        ] );
        wp_die( );
    }
    public static function testar_server( ) {
        $options = get_option( 'pinedu_imovel_options', [ ] );
        $options['fotos_demanda'] = 'on';
        $options['importacao_andamento'] = true;
        update_option( 'pinedu_imovel_options', $options );
        if ( isset( $_POST[ 'url_servidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'url_servidor' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $url_servidor = $options[ 'url_servidor' ] ?? '';
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:testar_server' );
            error_log( 'url_servidor:' . $url_servidor );
        }
        $testar = new Pinedu_Imovel_Testar_Server( );
        wp_send_json( $testar->testar_server( $url_servidor ) );
        wp_die( );
    }
    public static function pre_login( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:pre_login' );
        }
        $options = get_option( 'pinedu_imovel_options', [ ] );
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:pre_login:options:' . print_r( $options, true ) );
        }
        $importacao_andamento = false;
        if ( isset( $options['importacao_andamento'] ) && 'on' === $options['importacao_andamento'] ) {
            $importacao_andamento = true;
        }
        if ( true === $importacao_andamento ) {
            wp_send_json( ['success'=> false, 'message' => 'Importação em andamento por outro processo. Tente novamente mais tarde!'] );;
            wp_die( );
        }
        wp_send_json( ['success'=> true, 'message' => 'Importação autorizada!'] );;
        wp_die( );
    }
    public static function pos_login( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:pos_login' );
        }
        $options = get_option( 'pinedu_imovel_options', [ ] );
        //$options['fotos_demanda'] = 'on';
        $options['importacao_andamento'] = true;

        if ( isset( $_POST[ 'urlServidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'urlServidor' ] );
            $options['url_servidor'] = $url_servidor;
        }
        if ( isset( $_POST[ 'token' ] ) ) {
            $token = sanitize_text_field( $_POST[ 'token' ] );
            $options['token'] = $token;
        }
        if ( isset( $_POST[ 'expiracaoToken' ] ) ) {
            $expiracao_token = sanitize_text_field( $_POST[ 'expiracaoToken' ] );
            $options['expiracaoToken'] = $expiracao_token;
        }
        update_option( 'pinedu_imovel_options', $options );
        wp_send_json( ['success'=> true, 'message' => 'Contexto inicializado com sucesso!'] );;
        wp_die( );
    }
    public static function importar_basicos( ) {
        if ( isset( $_POST[ 'url_servidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'url_servidor' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $url_servidor = $options[ 'url_servidor' ] ?? '';
        }
        $forcar = false;
        if ( isset( $_POST[ 'forcar' ] ) ) {
            $forcar_string = sanitize_text_field( $_POST[ 'forcar' ] ?? '' );
            $forcar = filter_var( $forcar_string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_basicos' );
        }
        $importar = new Pinedu_Imovel_Importar_Basicos( $url_servidor );
        $importar->recupera_dados_json( $url_servidor, $forcar );
        wp_die( );
    }
    public static function preparar_imoveis( ) {
        if ( isset( $_POST[ 'url_servidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'url_servidor' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $url_servidor = $options[ 'url_servidor' ] ?? '';
        }
        $forcar = false;
        if ( isset( $_POST[ 'forcar' ] ) ) {
            $forcar_string = sanitize_text_field( $_POST[ 'forcar' ] ?? '' );
            $forcar = filter_var( $forcar_string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_imoveis' );
            error_log( 'url_servidor:' . $url_servidor );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $importar->preparar_imoveis( $url_servidor, $forcar );
    }
    public static function importar_imoveis( ) {
        $max = 50;
        $offset = 0;
        if ( isset( $_POST[ 'offset' ] ) ) {
            $offset = intval( $_POST[ 'offset' ] );
        }
        if ( isset( $_POST[ 'max' ] ) ) {
            $max = intval( $_POST[ 'max' ] );
        }
        if ( isset( $_POST[ 'url_servidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'url_servidor' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $url_servidor = $options[ 'url_servidor' ] ?? '';
        }
        if ( isset( $_POST[ 'ultima_atualizacao' ] ) ) {
            $ultima_atualizacao = sanitize_text_field( $_POST[ 'ultima_atualizacao' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $ultima_atualizacao = $options[ 'ultima_atualizacao' ] ?? '';
        }
        $forcar = false;
        if ( isset( $_POST[ 'forcar' ] ) ) {
            $forcar_string = sanitize_text_field( $_POST[ 'forcar' ] ?? '' );
            $forcar = filter_var( $forcar_string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_imoveis' );
            error_log( 'url_servidor:' . $url_servidor );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $importar->importa_imoveis_front_end( $url_servidor, $ultima_atualizacao, [ ], $forcar, $offset, $max );
    }
    public static function importar_imoveis_json( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_imoveis' );
        }
        $imoveis = [];
        if ( isset( $_POST['imoveis'] ) && is_string( $_POST['imoveis'] ) ) {
            $imoveis_string = wp_unslash( $_POST['imoveis'] );
            if (is_development_mode()) {
                error_log( 'PineduImportarFrontEnd:importar_imoveis_json:imoveis_string:' . $imoveis_string );
            }
            $imoveis = json_decode( $imoveis_string, true );
            if (is_development_mode()) {
                error_log( 'PineduImportarFrontEnd:importar_imoveis_json:imoveis:' . print_r( $imoveis, true ) );;
            }
        }
        if ( empty( $imoveis ) ) {
            wp_send_json( [
                'success' => false,
                'message' => 'Nenhum imóvel para Importar 0 !'
            ] );
            wp_die( );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $importar->importa_imoveis_particao_json( $imoveis );
    }
    public static function preparar_excluir_imoveis( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_excluir_imoveis' );
        }
        if ( isset( $_POST[ 'url_servidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'url_servidor' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $url_servidor = $options[ 'url_servidor' ] ?? '';
        }
        $forcar = false;
        if ( isset( $_POST[ 'forcar' ] ) ) {
            $forcar_string = sanitize_text_field( $_POST[ 'forcar' ] ?? '' );
            $forcar = filter_var( $forcar_string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $data = $importar->preparar_imoveis_excluidos( $url_servidor, $forcar );
        $excluidos = [];
        if ( $data['success'] === true ) {
            $excluidos = $data['excluidos'];
        }
/*        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_excluir_imoveis:excluidos:' . print_r( $excluidos, true ) );
        }*/
        $posts_ids = self::recupera_post_id_from_referencias( $excluidos );
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_excluir_imoveis:posts_ids:' . print_r( $posts_ids, true ) );
        }
        wp_send_json( $posts_ids );
        wp_die( );
    }
    public static function recupera_post_id_from_referencias_json( $mapa_referencias ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_excluir_imoveis' );
        }
        $forcar = false;
        if ( isset( $_POST[ 'forcar' ] ) ) {
            $forcar_string = sanitize_text_field( $_POST[ 'forcar' ] ?? '' );
            $forcar = filter_var( $forcar_string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $excluidos = [];

        if ( isset( $_POST['excluidos'] ) && is_string( $_POST['excluidos'] ) ) {
            $excluidos_string = wp_unslash( $_POST['excluidos'] );
            $excluidos = json_decode( $excluidos_string, true );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_excluir_imoveis:excluidos:' . print_r( $excluidos, true ) );
        }

        $posts_ids = self::recupera_post_id_from_referencias( $excluidos );
        wp_send_json( $posts_ids );
        wp_die( );
    }
    private static function recupera_post_id_from_referencias( $mapa_referencias ) {
        $referencias_desejadas = [];
        foreach ( $mapa_referencias as $ref ) {
            $referencias_desejadas[] = intval( $ref['referencia'] );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_excluir_imoveis:referencias_desejadas:' . print_r( $referencias_desejadas, true ) );
        }
        $args = [
            'post_type'      => 'imovel',
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'referencia',
                    'value'   => $referencias_desejadas,
                    'compare' => 'IN',
                ],
            ],
        ];

        $query = new WP_Query( $args );
        $post_ids = $query->posts;
        $count = $query->post_count;
        wp_reset_postdata( );
        return ['ids' => $post_ids, 'total' => $count, 'success' => ( $count>0 )];
    }
    public static function excluir_imoveis( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:excluir_imoveis' );
        }
        $excluidos = [];

        if ( isset( $_POST['excluidos'] ) && is_string( $_POST['excluidos'] ) ) {
            $excluidos_string = wp_unslash( $_POST['excluidos'] );
            $decoded = json_decode( $excluidos_string, true );
            if ( json_last_error( ) === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $excluidos = array_map( 'intval', $decoded );
            }
        }
        if ( empty( $excluidos ) ) {
            wp_send_json( [
                'success' => false,
                'message' => 'Nenhum imóvel para excluir!'
            ] );
            wp_die( );
        }
/*        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:excluir_imoveis' . print_r( $excluidos, true ) );;
        }*/
        $importa_Imovel = new Pinedu_Imovel_Importa_Imovel( );
        $importa_Imovel->trata_excluidos_post_ids( $excluidos );
        wp_send_json( 
            [ 
                'success' => true,
                'message' => 'Imóveis fora de Contexto Excluídos com sucesso!'
            ]
        );
        wp_die( );
    }
    public static function agendar_importacao( $data_hora, $horas ) {
        self::getImportador( )->agendar_importacao( $data_hora, $horas );
    }
    public static function exclui_agendamento_completo( ) {
        self::getImportador( )->exclui_agendamento_completo( );
    }
    public static function consulta_agendamento( ) {
        self::getImportador( )->consulta_agendamento( );
    }
    public static function parse_timestamp_scheduler( $timestamp_utc, $target_timezone = 'America/Sao_Paulo' ) {
        self::getImportador( )->parse_timestamp_scheduler( $timestamp_utc, $target_timezone );
    }
    public static function get_agendamento_info( ):string {
        return self::getImportador( )->get_agendamento_info( );
    }
}