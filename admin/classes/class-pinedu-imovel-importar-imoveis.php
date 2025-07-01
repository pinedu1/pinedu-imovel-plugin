<?php
class Pinedu_Imovel_Importar_Imoveis extends Pinedu_Importa_Libs {
	const ENDPOINT = '/pndWordpress/api/imoveis';
	const TOKEN = '';
	const IMOVEIS_POR_BLOCO = 50;
	private $imoveis_importados = 0;
	private $ultima_atualizacao;
	private $token;
	public function __construct() {
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importa-imovel.php';
	}
	public function getToken(): string {
		return $this->token;
	}
	public function getUltimaAtualizacao() {
		return $this->ultima_atualizacao;
	}
	public function getImoveisImportados(): int {
		return $this->imoveis_importados;
	}
	public function setImoveisImportados( int $imoveis_importados ): void {
		$this->imoveis_importados = $imoveis_importados;
	}
	private function imoveis_clicks( ) {
		$query = new WP_Query(
			array(
				'post_type' => 'imovel'
				, 'post_status' => 'any'
				, 'meta_query' => array( [ 'key' => 'visitas', 'compare' => 'EXISTS' ] )
			)
		);
		$visitados = array();
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				global $post;
				$visitas = get_post_meta( $post->ID, 'visitas', false );
				foreach ( $visitas as $visita ) {
					$cookie = $visita['cookie'];
					$valor = $visita['clicks'];
					$visitados[] = array( 'cookie' => $cookie, 'clicks' => $valor, 'referencia' => (int)$post->referencia );
				}
			}
		}
		wp_reset_postdata();
		return json_encode( $visitados );
	}
	public function invoca_server() {
		try {
			$url = $_POST[ 'url_servidor' ] ?? '';
			$forcar = $_POST[ 'forcar' ] ?? false;
			if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
				wp_send_json_error( [ 'message' => 'URL inválida.' ] );
			}
			$fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );
			$offset = 0;
			$max = (int)self::IMOVEIS_POR_BLOCO;
			$this->imoveis_importados = 0;

			$options = get_option('pinedu_imovel_options', []);
			$ultima_atualizacao = new DateTime();
			if ( isset( $options['ultima_atualizacao'] ) ) {
				$ultima_atualizacao = $options[ 'ultima_atualizacao' ];
			}

			$imoveis_importar = new Pinedu_Imovel_Importa_Imovel();
			do {
				if ( $offset <= 0 ) {
					$data = $this->call_remote_server( $fullUrl, $max, $offset, $this->imoveis_clicks(), $ultima_atualizacao, $forcar );
				} else {
					$data = $this->call_remote_server( $fullUrl, $max, $offset, [], $ultima_atualizacao, $forcar );
				}
				if (!$data) {
					break;
				}
				$pagination = $data['pagination'];
				$returned = (int)$pagination['returned'];
				$total    = (int)$pagination['total'];
				$offset += $returned;
				$this->imoveis_importados = ($this->imoveis_importados + $returned);
				/* Invoca importacao */
				$imoveis_importar->importa_imoveis( $data['imoveis'] );
				if ( isset( $data[ 'token' ] ) ) {
					$this->token = $data[ 'token' ];
				}
			} while ($offset < $total);
			$this->ultima_atualizacao = new DateTime( 'now', new DateTimeZone( wp_timezone_string( ) ) );
		} catch ( Exception $e ) {
			error_log('Erro durante a importação de imóveis: ' . $e->getMessage());
			wp_send_json_error([
				'message' => 'Ocorreu um erro durante o processamento: ' . $e->getMessage()
				, 'error_code' => 'import_error'
				, 'trace' => defined('WP_DEBUG') && WP_DEBUG ? $e->getTrace() : null
				, 'details' => WP_DEBUG ? $e->getTraceAsString() : null
			], 400);
			return false;
		} finally {
			return true;
		}
	}
	private function call_remote_server( $url, $max = 0, $offset = 0, $clicados = array(), $ultima_atualizacao = null, $forcar = false ) {
		$options = get_option('pinedu_imovel_options', []);
		$headers = [
			'timeout' => ( 60 * 5 )
			, 'headers' => [
				'Content-Type' => 'application/json'
				, 'Authorization' => 'Bearer ' . sanitize_text_field( $options['token'] )
			]
			, 'sslverify' => true
		];
		$args = [ 'max' => $max, 'offset' => $offset ];
		$args['forcar'] = false;
		if ( $forcar ) {
			$args['forcar'] = true;
		}
		if ( $clicados ) {
			$args['visitas'] = $clicados;
		}
		if ( $ultima_atualizacao ) {
			$args['ultimaAtualizacao'] = formataData_iso8601( $ultima_atualizacao );
		}
		$my_url = $this->monta_get_url( $url, $args );
		$response = wp_remote_get( $my_url, $headers );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( ['message' => 'Erro de conexão com o servidor'] );
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( isset( $data[ 'success' ] ) && $data[ 'success' ] === true ) {
			return $data;
		} else {
			wp_send_json_error( [
				'message' => $data[ 'message' ] ?? 'Formato do arquivo inválido (Imóveis)!'
			] );
			return null;
		}
	}
}
class Pinedu_Importa_Libs {
	protected function monta_get_url( $url, $args) {
		return add_query_arg( $args, $url );
	}
}