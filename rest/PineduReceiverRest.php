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
    }
    public static function receber_basicos( $request ) {
        $json_string = $request->get_body();
        //error_log( 'JSON recebido: ' . $json_string );
        $data = json_decode( $json_string, true );
        //error_log( 'Hello World Basicos!!!' );
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
        //error_log( 'JSON recebido: ' . $json_string );
        $data = json_decode( $json_string, true );
        //error_log( 'Hello World Imóveis!!!' );
        $importa_imoveis = new Pinedu_Imovel_Importar_Imoveis();
        $result = $importa_imoveis->importar_callback( $data );
        return $result;
    }
    public static function verify_credentials( $request ) {
        $auth_header = $request->get_header('Authorization');
        //error_log('Authorization Header: ' . $auth_header);
        if (empty($auth_header)) {
            error_log('Authorization header missing');
            return false;
        }
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $bearer_token = trim($matches[1]);
            //error_log('Bearer Token: ' . $bearer_token);
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
        return false;
    }
}