<?php
require_once plugin_dir_path(__FILE__) . 'PineduRequest.php';
class Pinedu_Imovel_Testar_Server {
	const ENDPOINT = '/wordpress/index';
	public function __construct() {
		add_action('wp_ajax_pinedu_testar_servidor', [$this, 'invoca_server']);
	}

	/**
	 * @param $url: Url do servidor de Importação
	 * @param $isHook: Indica que a chamada é via Hook | TRUE, para dar sendError | FALSE chamada como função, teste interno
	 * @return false|mixed
	 */
	public static function testar_server( $url, $isHook = false ) {
		$fullUrl = trailingslashit($url) . ltrim(self::ENDPOINT, '/');
        error_log('testar_server:url: ' . print_r( $fullUrl, true ) );
        $data = PineduRequest::post( $fullUrl );
        error_log('testar_server:data: ' . print_r( $data, true ) );
		return $data;
	}
	public function invoca_server( ) {
        xdebug_break();
		$url = $_POST['url_servidor'] ?? '';

		$data = self::testar_server( $url, true );

		if (isset($data['success']) && $data['success'] === true) {
			$options = get_option('pinedu_imovel_options', []);
			$options['url_servidor'] = $data['url_servidor'] ?? '';
			$ultima_carga = $data['ultimaCarga'];
			if (!empty($ultima_carga)) {
				$options['ultima_atualizacao'] = $ultima_carga;
			}
			$options['url_servidor'] = $data['url_servidor'] ?? '';
			update_option('pinedu_imovel_options', $options);

			wp_send_json_success([
				'message' => $data['message'] ?? 'Servidor OK'
				, 'url_servidor' => $data['url_servidor']
			]);
		} else {
			wp_send_json_error([
				'message' => $data['message'] ?? 'Servidor respondeu com erro'
			]);
		}
	}
}
