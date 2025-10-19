<?php

class PineduRequest{
    private const ENDPOINT = '/wordpress/index';
    private const TIMEOUT = 60;

    public function __construct( ) {
    }

    /**
     * Verifica se o token está expirado
     * @return bool
     */
    private static function is_token_expired() {
        $options = get_option('pinedu_imovel_options', []);
        $token_expiration_date = $options['token_expiration_date'] ?? '1980-01-01T00:00:00-300';

        try {
            $expiration_time = new DateTime($token_expiration_date);
            $current_time = new DateTime();

            return $current_time >= $expiration_time;
        } catch (Exception $e) {
            error_log('Erro ao verificar expiração do token: ' . $e->getMessage());
            return true; // Se houver erro, considera expirado para forçar renovação
        }
    }

    /**
     * Realiza login para obter novo token
     * @return bool
     */
    private static function refresh_token() {
        $options = get_option('pinedu_imovel_options', []);

        $username = $options['token_username'];
        $password = $options['token_password'];
        $login_url = $options['url_servidor']; // URL base para login

        if (empty($username) || empty($password) || empty($login_url)) {
            error_log('Credenciais ou URL não configuradas para renovação do token');
            return false;
        }

        // Endpoint de login (ajuste conforme sua API)
        $login_endpoint = trailingslashit($login_url) . 'wordpress/login';

        $payload = [
            'timeout' => self::TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'sslverify' => true,
            'body' => wp_json_encode([
                'empresa' => 1,
                'username' => $username,
                'password' => $password
            ])
        ];
        //error_log('PineduRequest::post - payload: ' . print_r($payload, true));
        //error_log('PineduRequest::post - $login_endpoint: ' . print_r($login_endpoint, true));
        $response = wp_remote_post($login_endpoint, $payload);
        //error_log('PineduRequest::post - response: ' . print_r( $response, true ) );

        if (is_wp_error($response)) {
            error_log('Erro ao renovar token: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        //error_log('Resposta de login 0: ' . print_r($body, true));
        $data = json_decode($body, true);
        //error_log('Resposta de login 1: ' . print_r($data, true));
        if ( isset( $data['success'] ) && $data['success']) {
            $options['token'] = $data['token'];
            $options['token_expiration_date'] = $data['expiracaoToken'];
            update_option('pinedu_imovel_options', $options);
            return true;
        }

        //error_log('Resposta de login não contém token ou data de expiração');
        return false;
    }

    /**
     * Verifica e renova token se necessário
     * @return bool
     */
    private static function ensure_valid_token() {
        if (self::is_token_expired()) {
            return self::refresh_token();
        }
        return true;
    }

    public static function get( $url, $argumentos = [], $isHook = false ) {
        // Verifica e renova token se necessário
        if (!self::ensure_valid_token()) {
            if (is_development_mode()) {
                error_log('PineduRequest->get: Falha ao renovar token de autentica' . ' , url: ' . $url . ', args' . print_r($argumentos, true) . ' usHook: ' . $isHook);
            }
            if ($isHook) {
                wp_send_json_error(['message' => 'Falha ao renovar token de autenticação']);
            }
            return false;
        }

        $options = get_option('pinedu_imovel_options', []);
        $token = $options['token'] ?? '';
        $headers = [
            'timeout' => self::TIMEOUT
            , 'headers' => [ 'Content-Type' => 'application/json' , 'Authorization' => 'Bearer ' . sanitize_text_field( $token ) ]
            , 'sslverify' => true
        ];
        $credenciais = [ 'username' => urlencode( $options['token_username'] ), 'password' => urlencode( $options['token_password'] ) ];
        $args = array_merge( $credenciais, $argumentos );
        $my_url = self::monta_get_url( $url, $args );
        //error_log( "URL: " . $my_url );
        $response = wp_remote_get( $my_url, $headers );
        if ( is_wp_error( $response ) ) {
            if ( $isHook ) wp_send_json_error( ['message' => 'Erro de conexão com o servidor'] );
        }
        $body = wp_remote_retrieve_body( $response );

        return json_decode( $body, true );
    }

    public static function getFile( $url, $file_name, $argumentos = [] ) {
        // Verifica e renova token se necessário
        if (!self::ensure_valid_token()) {
            if (is_development_mode()) {
                error_log('PineduRequest->getFile: Falha ao renovar token de autentica' . ' , url: ' . $url . ', args' . print_r($argumentos, true));
            }
            return null;
        }

        $options = get_option('pinedu_imovel_options', []);
        $token = $options['token'] ?? '';
        $headers = [
            'timeout' => self::TIMEOUT
            , 'headers' => [ 'Content-Type' => 'application/json' , 'Authorization' => 'Bearer ' . sanitize_text_field( $token ) ]
            , 'sslverify' => true
            , 'stream' => true
            , 'filename' => $file_name
        ];
        $credenciais = [ 'username' => $options['token_username'], 'password' => $options['token_password'] ];
        $args = array_merge( $credenciais, $argumentos );
        $my_url = self::monta_get_url( $url, $args );
        $response = wp_remote_get( $my_url, $headers );
        if ( is_wp_error( $response ) ) {
            return null;
        }

        return $response;
    }

    public static function post( $url, $argumentos = [], $isHook = false ) {
        // Verifica e renova token se necessário
        if (!self::ensure_valid_token()) {
            if (is_development_mode()) {
                error_log('PineduRequest->post: Falha ao renovar token de autentica' . ' , url: ' . $url . ', args' . print_r($argumentos, true) . ' usHook: ' . $isHook);
            }
            if ($isHook) {
                wp_send_json_error(['message' => 'Falha ao renovar token de autenticação']);
            }
            return false;
        }

        $options = get_option( 'pinedu_imovel_options', [] );
        $credenciais = [ 'username' => urlencode( $options['token_username'] ), 'password' => urlencode( $options['token_password'] ) ];
        if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_send_json_error( ['message' => 'URL inválida.'] );
            return false;
        }
        $token = $options['token'] ?? '';
        $payload = [
            'timeout' => self::TIMEOUT
            , 'headers' => [ 'Content-Type' => 'application/json' , 'Authorization' => 'Bearer ' . $token ]
            , 'sslverify' => true
        ];
        if ( $argumentos && is_array( $argumentos ) ) {
            $a = array_merge( $argumentos, $credenciais );
            $payload['body'] = wp_json_encode( $a );
        } else {
            $payload[ 'body' ] = wp_json_encode( $credenciais );
        }
        $response = wp_remote_post( $url, $payload );
        if ( is_wp_error( $response ) ) {
            error_log( "Erro: " . json_encode( $response ) );
            if ( $isHook ) wp_send_json_error( ['message' => 'Erro de conexão com o servidor'] );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        return $data;
    }
    protected static function monta_get_url( $url, $args) {
        if ( ! empty( $args ) ) {
            $query_string = http_build_query( $args );
            if ( strpos( $url, '?' ) !== false ) {
                return $url . '&' . $query_string;
            } else {
                return $url . '?' . $query_string;
            }
        }
        return $url;
    }
	public static function put( $url, $data ) {}
	public static function delete( $url ) {}
}