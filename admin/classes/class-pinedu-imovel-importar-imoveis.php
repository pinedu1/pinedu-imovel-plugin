<?php
require_once plugin_dir_path(__FILE__) . 'PineduRequest.php';

class Pinedu_Imovel_Importar_Imoveis extends Pinedu_Importa_Libs {
	const ENDPOINT = '/wordpress/imoveis';
	const IMOVEIS_POR_BLOCO = 50;
	private $imoveis_importados = 0;
	private $ultima_atualizacao;
	private $token;
	private $imoveis_excluidos = array();
	public function __construct() {
		require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importa-imovel.php';
	}
	public function getToken(): string {
		return $this->token??'';
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
	public function invoca_server( $url, $forcar = false) {
		try {
			set_time_limit(0);
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
				$clicks = [];
				if ( $offset <= 0 ) {
					$clicks = $this->imoveis_clicks();
				}
				$data = $this->call_remote_server( $fullUrl, $max, $offset, $clicks, $ultima_atualizacao, $forcar );
				if (!$data) {
					break;
				}
				if ( isset( $data[ 'token' ] ) ) {
					$this->token = $data[ 'token' ];
				}
				if ( isset($data['excluidos']) && !empty( $data['excluidos'] ) ) {
					$this->imoveis_excluidos = $data['excluidos'];
				}
				$pagination = $data['pagination'];
				$returned = (int)$pagination['returned'];
				$total = (int)$pagination['total'];
				$offset += $returned;
				/* Invoca importacao */
				$imoveis_importar->importa_imoveis( $data['imoveis'] );
                /* Atualiza contador de imoveis importados */
                $this->imoveis_importados = ($this->imoveis_importados + $returned);
			} while ($offset < $total);
			if ( isset( $this->imoveis_excluidos ) && !empty( $this->imoveis_excluidos ) ) {
				$imoveis_importar->trata_excluidos( $this->imoveis_excluidos );
			}

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
		$args = [ 'max' => $max, 'offset' => $offset, 'forcar' => $forcar ];
		if ( $clicados ) {
			$args['visitas'] = $clicados;
		}
		if ( $ultima_atualizacao ) {
			$args['ultimaAtualizacao'] = formataData_iso8601( $ultima_atualizacao );
		}
		if ( $forcar == true ) {
			$args['ultimaAtualizacao'] = '1980-01-01T00:00:00.000Z';
		}
        error_log( "Argumentos: " . print_r( $args, true ) );
		$data = PineduRequest::get( $url, $args );

		if ( ((bool)$data[ 'success' ]) != true ) {
			//error_log( "Imoveis success false ");
			wp_send_json_error( ['message' => $data[ 'message' ] ?? 'Formato do arquivo inválido (Imóveis)!'] );
			return null;
		}
		//error_log( "Imoveis success true ");
		return $data;
	}
}
class Pinedu_Importa_Libs {
	protected function monta_get_url( $url, $args) {
		return add_query_arg( $args, $url );
	}
}

/*
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'Teste de WP_DEBUG_LOG: Esta mensagem deve ir para o arquivo de debug do WordPress. Hora: ' . date('Y-m-d H:i:s') );

			// Exemplo de log de uma variável
			$minhaVariavel = ['chave' => 'valor', 'id' => 123];
			error_log( 'Teste de WP_DEBUG_LOG: Minha variável: ' . print_r($minhaVariavel, true) );
		} else {
			error_log( 'WP_DEBUG não está definido ou é falso. O debug do WordPress não está ativo.' );
		}



*/