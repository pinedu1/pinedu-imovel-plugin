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

class PineduImportarFrontEnd {
    const PREFIXO_ADMIN = 'wp_ajax_';
    const PREFIXO = 'wp_ajax_nopriv_';
    const HOOK_IMPORTACAO = 'PINEDU_EXECUTAR_IMPORTACAO';
    const HOOK_INICIALIZAR = 'IMPORTA_FRONTEND_INICIALIZAR';
    const HOOK_PREPARAR_BASICOS = 'IMPORTA_FRONTEND_PREPARAR_BASICOS';
    const HOOK_PREPARAR_IMOVEIS = 'IMPORTA_FRONTEND_PREPARAR_IMOVEIS';
    const HOOK_IMPORTAR_IMOVEIS = 'IMPORTA_FRONTEND_IMPORTAR_IMOVEIS';
    const HOOK_EXCLUIR_IMOVEIS = 'IMPORTA_FRONTEND_EXCLUIR_IMOVEIS';
    const HOOK_PREPARAR_IMAGEM_DESTAQUE = 'PREPARA_IMAGEM_DESTAQUE';
    const HOOK_IMPORTAR_IMAGEM_DESTAQUE = 'IMPORTA_IMAGEM_DESTAQUE';
    const HOOK_FINALIZAR_IMPORTACAO = 'FINALIZA_IMPORTACAO';
    const HOOK_IMPORTAR_CIDADE = 'IMPORTA_FRONTEND_IMPORTAR_CIDADE';
    const HOOK_IMPORTAR_EMPRESA = 'IMPORTA_FRONTEND_IMPORTAR_EMPRESA';
    const HOOK_IMPORTAR_LOJA = 'IMPORTA_FRONTEND_IMPORTAR_LOJA';
    const HOOK_IMPORTAR_CORRETOR = 'IMPORTA_FRONTEND_IMPORTAR_CORRETOR';
    const HOOK_IMPORTAR_CONTRATO = 'IMPORTA_FRONTEND_IMPORTAR_CONTRATO';
    const HOOK_IMPORTAR_TIPO_IMOVEL = 'IMPORTA_FRONTEND_IMPORTAR_TIPO_IMOVEL';
    const HOOK_IMPORTAR_FAIXA_VALOR = 'IMPORTA_FRONTEND_IMPORTAR_FAIXA_VALOR';
    const HOOK_IMPORTAR_TIPO_DEPENDENCIA = 'IMPORTA_FRONTEND_IMPORTAR_TIPO_DEPENDENCIA';
    public static function init( ) {
        if ( !is_admin( ) ) return;
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:init' );
        }
        add_action( self::PREFIXO_ADMIN . self::HOOK_INICIALIZAR, [ __CLASS__, 'testar_server' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_BASICOS, [ __CLASS__, 'importar_basicos' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_IMOVEIS, [ __CLASS__, 'preparar_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_IMOVEIS, [ __CLASS__, 'importar_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_EXCLUIR_IMOVEIS, [ __CLASS__, 'excluir_imoveis' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_PREPARAR_IMAGEM_DESTAQUE, [ __CLASS__, 'preparar_imagem_destaque' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_IMAGEM_DESTAQUE, [ __CLASS__, 'importar_imagem_destaque' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_FINALIZAR_IMPORTACAO, [ __CLASS__, 'finalizar_importacao' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_CIDADE, [ __CLASS__, 'importar_cidade' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_EMPRESA, [ __CLASS__, 'importar_empresa' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_LOJA, [ __CLASS__, 'importar_lojas'], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_CORRETOR, [ __CLASS__, 'importar_corretor' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_CONTRATO, [ __CLASS__, 'importar_contrato' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_TIPO_IMOVEL, [ __CLASS__, 'importar_tipo_imovel' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_FAIXA_VALOR, [ __CLASS__, 'importar_faixa_valor' ], 10 );
        add_action( self::PREFIXO_ADMIN . self::HOOK_IMPORTAR_TIPO_DEPENDENCIA, [ __CLASS__, 'importar_tipo_dependencia' ], 10 );
    }
    public static function importar_empresa( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_empresa' );
        }
        $empresa_json = $_POST['empresa'] ?? null;
        $empresa_json = stripslashes($empresa_json);
        if ($empresa_json) {
            $empresa = json_decode($empresa_json, true); // agora é array associativo
        } else {
            $empresa = null;
        }
        if ( $empresa === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Empresa não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $empresa_importa = new Pinedu_Imovel_Importa_Empresa( );
        $empresa_importa->importa( $empresa );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados Empresa importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_cidade( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_cidade' );
        }
        $cidades_json = $_POST['cidades'] ?? null;
        $cidades_json = stripslashes($cidades_json);
        if ($cidades_json) {
            $cidades = json_decode($cidades_json, true); // agora é array associativo
        } else {
            $cidades = null;
        }
        if ( $cidades === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Cidade não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $importa_cidades = new Pinedu_Imovel_Importa_Cidade();
        $importa_cidades->importa_cidades( $cidades );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados das Cidades importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_lojas( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_lojas' );
        }
        $lojas_json = $_POST['lojas'] ?? null;
        $lojas_json = stripslashes($lojas_json);
        if ($lojas_json) {
            $lojas = json_decode($lojas_json, true); // agora é array associativo
        } else {
            $lojas = null;
        }
        if ( $lojas === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó Loja não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $importa_loja = new Pinedu_Imovel_Importa_Loja();
        $importa_loja->importar( $lojas );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados das Lojas importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_corretor( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_corretor' );
        }
        $corretores_json = $_POST['corretores'] ?? null;
        $corretores_json = stripslashes($corretores_json);
        if ($corretores_json) {
            $corretores = json_decode($corretores_json, true); // agora é array associativo
        } else {
            $corretores = null;
        }
        if ( $corretores === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó corretores não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $importa_corretor = new Pinedu_Imovel_Importa_Corretor();
        $importa_corretor->importar( $corretores );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos corretores importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_contrato( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_contrato' );
        }
        $tipo_contratos_json = $_POST['tipo_contratos'] ?? null;
        $tipo_contratos_json = stripslashes($tipo_contratos_json);
        if ($tipo_contratos_json) {
            $tipo_contratos = json_decode($tipo_contratos_json, true); // agora é array associativo
        } else {
            $tipo_contratos = null;
        }
        if ( $tipo_contratos === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó tipo_contratos não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $importa_contrato = new Pinedu_Imovel_Importa_Contrato();
        $importa_contrato->importar( $tipo_contratos );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos tipo de Contratos importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_tipo_imovel( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_tipo_imovel' );
        }
        $tipo_imoveis_json = $_POST['tipo_imoveis'] ?? null;
        $tipo_imoveis_json = stripslashes($tipo_imoveis_json);
        if ($tipo_imoveis_json) {
            $tipo_imoveis = json_decode($tipo_imoveis_json, true); // agora é array associativo
        } else {
            $tipo_imoveis = null;
        }
        if ( $tipo_imoveis === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó tipo_imoveis não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $importa_tipo_imovel = new Pinedu_Imovel_Importa_Tipo_Imovel();
        $importa_tipo_imovel->importa_tipo_imoveis( $tipo_imoveis );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos tipo de imoveis importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_faixa_valor( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_faixa_valor' );
        }
        $faixa_valores_json = $_POST['faixa_valores'] ?? null;
        $faixa_valores_json = stripslashes($faixa_valores_json);
        if ($faixa_valores_json) {
            $faixa_valores = json_decode($faixa_valores_json, true); // agora é array associativo
        } else {
            $faixa_valores = null;
        }
        if ( $faixa_valores === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó faixa de valores não encontrado ou vazio no JSON'] );
            wp_die();
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_faixa_valor_1' . print_r( $faixa_valores, true) );
        }
        $importa_Faixa_valor = new Pinedu_Imovel_Importa_Faixa_Valor();
        foreach ( $faixa_valores as $faixa ) {
            $importa_Faixa_valor->importa( $faixa );
        }
        wp_send_json( [
            'success' => true,
            'message' => 'Dados das faixas de valores importados com Sucesso!'
        ] );
        wp_die();
    }
    public static function importar_tipo_dependencia( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_tipo_dependencias' );
        }
        $tipo_dependencias_json = $_POST['tipo_dependencias'] ?? null;
        $tipo_dependencias_json = stripslashes($tipo_dependencias_json);
        if ($tipo_dependencias_json) {
            $tipo_dependencias = json_decode($tipo_dependencias_json, true); // agora é array associativo
        } else {
            $tipo_dependencias = null;
        }
        if ( $tipo_dependencias === null ) {
            wp_send_json_error( ['success' => false, 'message' => 'Nó faixa de valores não encontrado ou vazio no JSON'] );
            wp_die();
        }
        $importa_tipo_dependencias = new Pinedu_Imovel_Importa_Tipo_Dependencia();
        $importa_tipo_dependencias->importa( $tipo_dependencias );
        wp_send_json( [
            'success' => true,
            'message' => 'Dados dos tipo de dependências importados com Sucesso!'
        ] );
        wp_die();
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
        if (empty( $options[ 'tempo_atualizacao' ] )) {
            $options['tempo_atualizacao'] = 1;
        }
        self::exclui_agendamento_completo();
        self::agendar_importacao( ( new DateTime() )->getTimestamp(), intval( $options['tempo_atualizacao'] ) );
        $options[ 'imoveis_importados' ] = $imoveis_importados;
        $options[ 'ultima_atualizacao' ] = new DateTime( );
        $options[ 'token' ] = $token;
        $options[ 'proxima_atualizacao' ] = self::parse_timestamp_scheduler( self::consulta_agendamento( ) );
        $options[ 'importacao_andamento' ] = false;
        $options[ 'success' ] = true;
        update_option( 'pinedu_imovel_options', $options );
        wp_send_json( $options );
        wp_die( );
    }
    public static function preparar_imagem_destaque( ) {
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:preparar_imagem_destaque' );
        }
        $args = [
            'post_type' => 'imovel',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [ 'key' => 'imagem_destaque', 'value' => '', 'compare' => '!=' ],
            ]
        ];
        $query = new WP_Query( $args );
        $count = $query->post_count;
        wp_reset_postdata( );
        wp_send_json( [ 
            'success' => true,
            'total' => $count,
        ] );
        wp_die( );
    }
    public static function importar_imagem_destaque() {
        $max = 50;
        $offset = 0;
        if ( isset( $_POST[ 'offset' ] ) ) {
            $offset = intval( $_POST[ 'offset' ] );
        }
        if ( isset( $_POST[ 'max' ] ) ) {
            $max = intval( $_POST[ 'max' ] );
        }
        if ( is_development_mode( ) ) {
            error_log( 'PineduImportarFrontEnd:importar_imagem_destaque' );
        }
        $args = [
            'post_type'      => 'imovel',
            'post_status'    => 'any',
            'posts_per_page' => $max,
            'offset'         => $offset,
            'meta_query'     => [
                [
                    'key'     => 'imagem_destaque',
                    'value'   => '',
                    'compare' => '!=', // só pega quem tem valor diferente de vazio
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if (is_development_mode()) {
            error_log( 'PineduImportarFrontEnd:importar_imagem_destaque:args:' . print_r( $args, true ) );
        }
        $query = new WP_Query( $args );
        if (is_development_mode()) {
            error_log( 'PineduImportarFrontEnd:importar_imagem_destaque:posts:' . print_r( $query->posts, true ) );
        }
        baixar_fotos_destaque( $query, true );
        wp_reset_postdata( );
        wp_send_json( [
            'success' => true,
            'returned' => $query->post_count,
        ] );
        wp_die( );
    }
    public static function testar_server( ) {
        $options = get_option( 'pinedu_imovel_options', [ ] );
        $options['fotos_demanda'] = 'on';
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
            error_log( 'url_servidor:' . $url_servidor );
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
            error_log( 'PineduImportarFrontEnd:importar_basicos' );
            error_log( 'url_servidor:' . $url_servidor );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $importar->preparar_imoveis( $url_servidor, $forcar );
    }
    public static function excluir_imoveis( ) {
        if ( isset( $_POST[ 'url_servidor' ] ) ) {
            $url_servidor = sanitize_text_field( $_POST[ 'url_servidor' ] );
        } else {
            $options = get_option( 'pinedu_imovel_options', [ ] );
            $url_servidor = $options[ 'url_servidor' ] ?? '';
        }
        if ( isset( $_POST[ 'excluidos' ] ) ) {
            $excluidos_data = $_POST[ 'excluidos' ];
            // Debug para ver o tipo e valor
/*            if ( is_development_mode( ) ) {
                error_log( 'Tipo de excluidos: ' . gettype( $excluidos_data ) );
                error_log( 'Valor de excluidos: ' . print_r( $excluidos_data, true ) );
            }*/
            if ( is_array( $excluidos_data ) ) {
                $excluidos = array_map( 'sanitize_text_field', $excluidos_data );
            } elseif ( is_string( $excluidos_data ) ) {
                // Se for string JSON, tenta decodificar
                $decoded = json_decode( $excluidos_data, true );
                if ( json_last_error( ) === JSON_ERROR_NONE && is_array( $decoded ) ) {
                    $excluidos = array_map( 'sanitize_text_field', $decoded );
                } else {
                    // Se não for JSON, trata como string única
                    $excluidos = array( sanitize_text_field( $excluidos_data ) );
                }
            } else {
                $excluidos = array( );
            }
        } else {
            wp_send_json( 
                [ 
                    'success' => true,
                    'message' => 'Não foram encontrados imóveis para excluir!'
                ]
        );
            wp_die( );
        }
        $importa_Imovel = new Pinedu_Imovel_Importa_Imovel( );
        $importa_Imovel->trata_excluidos_from_referecia_array( $excluidos );
        wp_send_json( 
            [ 
                'success' => true,
                'message' => 'Imóveis fora de Contexto Excluídos com sucesso!'
            ]
    );
        wp_die( );
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
            error_log( 'PineduImportarFrontEnd:importar_basicos' );
            error_log( 'url_servidor:' . $url_servidor );
        }
        $importar = new Pinedu_Imovel_Importar_Imoveis( );
        $importar->importa_imoveis_front_end( $url_servidor, $ultima_atualizacao, [ ], $forcar, $offset, $max );
    }
/*
    public static function importar_cidade( ) {}
    public static function importar_empresa( ) {}
    public static function importar_loja( ) {}
    public static function importar_corretor( ) {}
    public static function importar_contrato( ) {}
    public static function importar_tipo_imovel( ) {}
    public static function importar_faixa_valor( ) {}
    public static function importar_tipo_dependencia( ) {}
*/
    public static function agendar_importacao( $data_hora, $horas ) {
        if ( $horas <= 0 ) $horas = 1;
        $timestamp = wp_next_scheduled( self::HOOK_IMPORTACAO );
        if ( !wp_next_scheduled( self::HOOK_IMPORTACAO ) ) {
            wp_schedule_event( ( ( $data_hora?? time( ) ) + ( 3600 * ( $horas?? 1 ) ) ), 'hourly', self::HOOK_IMPORTACAO );
        }
        add_action( self::HOOK_IMPORTACAO, [ __CLASS__, 'invoca_importacao' ] );
    }
    public static function exclui_agendamento_completo( ) {
        $timestamp = wp_next_scheduled( self::HOOK_IMPORTACAO );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_IMPORTACAO );
        }
        remove_action( self::HOOK_IMPORTACAO, [ __CLASS__, 'invoca_importacao' ] );
    }
    public static function consulta_agendamento( ) {
        $timestamp = wp_next_scheduled( self::HOOK_IMPORTACAO );
        return $timestamp;
    }
    public static function parse_timestamp_scheduler( $timestamp_utc, $target_timezone = 'America/Sao_Paulo' ) {
        if ( false === $timestamp_utc || !is_numeric( $timestamp_utc ) ) {
            return false;
        }
        try {
            $tz = new DateTimeZone( $target_timezone );
        } catch ( Exception $e ) {
            $tz = new DateTimeZone( 'UTC' );
        }
        $datetime_obj = new DateTime( "@$timestamp_utc", new DateTimeZone( 'UTC' ) );
        $datetime_obj->setTimezone( $tz );
        return $datetime_obj;
    }
    public static function get_agendamento_info( ) {
        $timestamp = self::consulta_agendamento( );
        if ( $timestamp === false ) {
            return "Nenhum agendamento ativo";
        }
        return sprintf( 
            "Próxima execução em: %s ( %s )",
            parse_timestamp_scheduler( $timestamp )->format( 'd/m/Y H:i:s' ),
    );
    }
}