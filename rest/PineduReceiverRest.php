<?php
require_once plugin_dir_path(__FILE__) . '../admin/classes/PineduRequest.php';
require_once plugin_dir_path(__FILE__) . '../admin/classes/class-pinedu-imovel-importar-basicos.php';
require_once plugin_dir_path(__FILE__) . '../admin/classes/class-pinedu-imovel-importar-imoveis.php';

class PineduReceiverRest extends PineduRequest {
    private static $instance = null;

    private function __construct() {
        // Construtor privado
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    private static function getInstance(): PineduReceiverRest {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function instala_rest_end_point() {
        register_rest_route('pinedu-imovel/v1', 'inicializar', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'inicializar_atualizacao' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
        register_rest_route('pinedu-imovel/v1', 'encerrar', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'encerrar_atualizacao' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
        register_rest_route('pinedu-imovel/v1', 'update_basicos', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'receber_basicos' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
        register_rest_route('pinedu-imovel/v1', 'update_imoveis', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'receber_imoveis' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));

        // Rota de Cliques
        register_rest_route('pinedu-imovel/v1', 'clicks', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'enviar_clicks' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
    }

    public static function enviar_clicks( $request ) {
        global $wpdb;

        // Query SQL para buscar os eventos isolados e seus meta_ids (para exclusão segura posterior)
        $query = "
            SELECT 
                ref.meta_value AS referencia,
                vis.meta_value AS evento_dados,
                vis.meta_id AS meta_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} ref 
                ON p.ID = ref.post_id AND ref.meta_key = 'referencia'
            INNER JOIN {$wpdb->postmeta} vis 
                ON p.ID = vis.post_id AND vis.meta_key = 'visita_evento'
            WHERE p.post_type = 'imovel'
              AND p.post_status = 'publish'
        ";

        $resultados = $wpdb->get_results( $query );
        $clicks_formatados = [];
        $meta_ids_para_excluir = [];

        if ( ! empty( $resultados ) ) {
            foreach ( $resultados as $linha ) {
                $dados = maybe_unserialize( $linha->evento_dados );

                if ( is_array( $dados ) && isset( $dados['cookie'] ) && isset( $dados['data'] ) ) {
                    $clicks_formatados[] = [
                        'referencia' => $linha->referencia,
                        'data'       => $dados['data'],
                        'cookie'     => $dados['cookie'],
                        'clicks'     => 1 // É um registro unitário na fila de eventos
                    ];
                    // Guarda a chave primária da wp_postmeta para a exclusão
                    $meta_ids_para_excluir[] = $linha->meta_id;
                }
            }
        }

        // Exclui APENAS os registros que acabamos de colocar no JSON.
        // Isso previne que um clique que ocorreu enquanto este script rodava seja apagado acidentalmente.
        if ( ! empty( $meta_ids_para_excluir ) ) {
            // Monta uma string como "105,106,107" com blindagem de inteiros para segurança
            $ids_imploded = implode( ',', array_map( 'intval', $meta_ids_para_excluir ) );
            $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($ids_imploded)" );
        }

        $response = [
            'success' => count( $clicks_formatados ) > 0,
            'clicks'  => $clicks_formatados
        ];

        return rest_ensure_response( $response );
    }

    public static function inicializar_atualizacao( $request ) {
        $options = get_option('pinedu_imovel_options', []);
        if ( isset( $options['importacao_andamento'] ) && 'on' === $options['importacao_andamento'] ) {
            $options['importacao_andamento'] = true;
        } else {
            $options['importacao_andamento'] = false;
        }
        if ( $options['importacao_andamento'] === true ) {
            wp_send_json_error( [
                'message' => 'Importação em andamento por outro processo. Tente novamente mais tarde!',
            ] );
            wp_die();
        }
        if ( ! isset( $options['ultima_atualizacao'] ) ) {
            $options['ultima_atualizacao'] = DateTime::createFromFormat(
                'd/m/Y H:i:sT',
                '01/01/1980 00:00:00-300'
            );
        }

        $options['importacao_andamento'] = true;
        $options['inicio_importacao'] = new DateTime();
        update_option('pinedu_imovel_options', $options);
        wp_send_json( [
            'importacao_andamento' => $options['importacao_andamento'],
            'inicio_importacao' => formataData_iso8601( $options['inicio_importacao'] ),
            'ultima_atualizacao' => formataData_iso8601( $options[ 'ultima_atualizacao' ] )
        ] );
    }

    public static function encerrar_atualizacao( $request ) {
        $json_string = $request->get_body();
        $data = json_decode( $json_string, true );

        if ( isset( $data[ 'token' ] ) ) {
            $token = sanitize_text_field( $data[ 'token' ] );
        }
        if ( isset( $data[ 'imoveis_importados' ] ) ) {
            $imoveis_importados = sanitize_text_field( $data[ 'imoveis_importados' ] );
        }
        $options = get_option( 'pinedu_imovel_options', [ ] );
        $options[ 'ultima_atualizacao' ] = new DateTime( );
        if ( isset( $options['inicio_importacao'] ) && $options['inicio_importacao'] instanceof DateTime ) {
            $intervalo = $options['inicio_importacao']->diff( $options['ultima_atualizacao'] );
            $options['tempo_utilizado'] = $intervalo;
        }
        $options[ 'imoveis_importados' ] = $imoveis_importados;
        $options[ 'token' ] = $token;
        $options[ 'importacao_andamento' ] = false;
        update_option( 'pinedu_imovel_options', $options );

        wp_send_json( [
            'inicio_importacao' => formataData_iso8601( $options['inicio_importacao'] ),
            'dataAtualizacao' => formataData_iso8601( $options[ 'ultima_atualizacao' ] ),
            'importacao_andamento' => $options['importacao_andamento']
        ] );
    }

    public static function receber_basicos( $request ) {
        $json_string = $request->get_body();
        if (is_development_mode()) {
            error_log('JSON recebido: ' . $json_string);
        }
        $data = json_decode( $json_string, true );
        if (is_development_mode()) {
            error_log( 'Hello World Basicos!!!' );
        }
        $importa_basicos = new Pinedu_Imovel_Importar_Basicos();
        $result = $importa_basicos->importar_callback( $data );
        if ( $result === true ) {
            $data = [
                'success' => true
                , 'dataAtualizacao' => formataData_iso8601( new \DateTime('now', new \DateTimeZone('America/Sao_Paulo') ) )
            ];
            return $data;
        }
        $data = [
            'success' => false
            , 'message' => 'Erro na importação dos dados Básicos'
        ];
        return $data;
    }

    public static function receber_imoveis( $request ) {
        $json_string = $request->get_body();
        $data = json_decode( $json_string, true );
        $importa_imoveis = new Pinedu_Imovel_Importar_Imoveis();
        $result = $importa_imoveis->importar_callback( $data );
        return $result;
    }

    public static function verify_credentials( $request ) {
        $auth_header = $request->get_header('Authorization');
        if (is_development_mode()) {
            error_log('Authorization Header: ' . $auth_header);
        }
        if (empty($auth_header)) {
            error_log('Authorization header missing');
            return false;
        }
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $bearer_token = trim($matches[1]);
            if (is_development_mode()) {
                error_log('Bearer Token: ' . $bearer_token);
            }
            return self::validate_bearer_token($bearer_token);
        }
        error_log('Invalid Authorization format');
        return false;
    }

    private static function validate_bearer_token( $token ): bool {
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( isset( $options['token'] ) && $options['token'] === $token ) {
            return true;
        }
        error_log('Invalid Token');
        return false;
    }
}