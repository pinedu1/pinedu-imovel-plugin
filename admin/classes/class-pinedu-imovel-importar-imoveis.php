<?php

use JetBrains\PhpStorm\NoReturn;

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
    #[NoReturn]
    public function preparar_imoveis_excluidos($url_servidor, $forcar = false ) {
        $endpoint = '/wordpress/listaExcluidos';
        $options = get_option('pinedu_imovel_options', []);

        $ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
        if ( isset( $options['ultima_atualizacao'] ) ) {
            $ultima_atualizacao = $options[ 'ultima_atualizacao' ];
        }
        $args = [ 'forcar' => $forcar ];
        if ( $ultima_atualizacao ) {
            $args['ultimaAtualizacao'] = formataData_iso8601( $ultima_atualizacao );
        }
        if (is_development_mode()) {
            error_log("preparar_imoveis_excluidos: " . print_r($args, true));
        }
        $fullUrl = trailingslashit( $url_servidor ) . ltrim( $endpoint, '/' );
        $data = PineduRequest::get( $fullUrl, $args );
        return $data;
    }
    #[NoReturn]
    public function preparar_imoveis($url_servidor, $forcar = false ): void {
        $endpoint = '/wordpress/preparaImportacao';
        $options = get_option('pinedu_imovel_options', []);

        $ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
        if ( isset( $options['ultima_atualizacao'] ) ) {
            $ultima_atualizacao = $options[ 'ultima_atualizacao' ];
        }
        $args = [ 'forcar' => $forcar ];
        if ( $ultima_atualizacao ) {
            $args['ultimaAtualizacao'] = formataData_iso8601( $ultima_atualizacao );
        }
        if (is_development_mode()) {
            /*
            if ( $forcar === true ) {
                $args['ultimaAtualizacao'] = '1980-01-01T00:00:00.000Z';
            }*/
        }
        if (is_development_mode()) {
            error_log("Argumentos ImportaImovel: " . print_r($args, true));
        }
        $fullUrl = trailingslashit( $url_servidor ) . ltrim( $endpoint, '/' );
        $data = PineduRequest::get( $fullUrl, $args );
        if (is_development_mode()) {
            error_log("Retorno Prepara IMoveis: " . print_r($data, true));
        }
        wp_send_json($data);
        wp_die();
    }
    public function importa_imoveis_front_end( $url, $ultima_atualizacao, $clicks = [], $forcar = false, $offset = 0, $max = 0 ) {
        $ignorar_excluidos = true;
        $data = $this->importa_imoveis_particao( $url, $ultima_atualizacao, $clicks, $forcar, $offset, $max, $ignorar_excluidos );
        if ($data === false) {
            wp_send_json([
                'success' => false,
                'message' => 'A importação não retornou dados '
            ]);
        }
        //$data['success'] = true;
        wp_send_json( $data );
        wp_die();
    }
    public function importa_imoveis_particao( $url, $ultima_atualizacao, $clicks = [], $forcar = false, $offset = 0, $max = 0, $ignorar_excluidos = false ) {
        $fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );
        $dados_retornar = [ 'success' => true, 'returned' => 0, 'total' => 0, 'excluidos' => [] ];
        $imoveis_importar = new Pinedu_Imovel_Importa_Imovel();

        $data = $this->call_remote_server( $fullUrl, $max, $offset, $clicks, $ultima_atualizacao, $forcar, $ignorar_excluidos );
        if (!$data) {
            return false;
        }
        if ( isset( $data[ 'token' ] ) ) {
            $dados_retornar['token'] = $data[ 'token' ];
        }
        if ( isset($data['excluidos']) && !empty( $data['excluidos'] ) ) {
            $dados_retornar['excluidos'] = $data['excluidos'];
        }
        $pagination = $data['pagination'];
        $dados_retornar['returned'] = (int)$pagination['returned'];
        $dados_retornar['total'] = (int)$pagination['total'];
        /* Invoca importacao */
        if ( isset($data['imoveis']) && !empty( $data['imoveis'] ) ) {
            $imoveis_importar->importa_imoveis( $data['imoveis'] );
        }
        return $dados_retornar;
    }
	public function invoca_server( $url, $forcar = false) {
		try {
			set_time_limit(0);
			if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
				wp_send_json_error( [ 'message' => 'URL inválida.' ] );
			}
			$offset = 0;
			$max = (int)self::IMOVEIS_POR_BLOCO;
			$this->imoveis_importados = 0;

			$options = get_option('pinedu_imovel_options', []);
			$ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
			if ( isset( $options['ultima_atualizacao'] ) ) {
				$ultima_atualizacao = $options[ 'ultima_atualizacao' ];
			}

			$imoveis_importar = new Pinedu_Imovel_Importa_Imovel();
			do {
				$clicks = [];
				if ( $offset <= 0 ) {
					$clicks = $this->imoveis_clicks();
				}
                $data = $this->importa_imoveis_particao( $url, $ultima_atualizacao, $clicks, $forcar, $offset, $max );
				if ( isset( $data[ 'token' ] ) ) {
					$this->token = $data[ 'token' ];
				}
				if ( isset($data['excluidos']) && !empty( $data['excluidos'] ) ) {
					$this->imoveis_excluidos = $data['excluidos'];
				}
				$returned = $data['returned'];
				$total = $data['total'];
				$offset += $returned;
                /* Atualiza contador de imoveis importados */
                $this->imoveis_importados = ($this->imoveis_importados + $returned);
			} while ($offset < $total);
			if ( isset( $this->imoveis_excluidos ) && !empty( $this->imoveis_excluidos ) ) {
				$imoveis_importar->trata_excluidos( $this->imoveis_excluidos );
			}

			$this->ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
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
	public function invoca_server_backup( $url, $forcar = false) {
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
			$ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
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

			$this->ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
		} catch ( Exception $e ) {
			if (is_development_mode()) {
                error_log('Erro durante a importação de imóveis: ' . $e->getMessage());
            }
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
    public function importar_callback( $data ) {
        try {
            $options = get_option('pinedu_imovel_options', []);
            $options['importacao_andamento'] = true;
            $imoveis_importar = new Pinedu_Imovel_Importa_Imovel();
            if ( isset( $data['imoveis'] ) && !empty( $data['imoveis'] ) ) {
                /* Invoca importacao */
                $imoveis_importar->importa_imoveis( $data['imoveis'] );
                /* Atualiza contador de imoveis importados */
            }
            if ( isset( $this->imoveis_excluidos ) && !empty( $this->imoveis_excluidos ) ) {
                $imoveis_importar->trata_excluidos( $this->imoveis_excluidos );
            }
            $this->ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
            $options['imoveis_importados'] = $imoveis_importar->getImoveisImportados();
            $options['ultima_atualizacao'] = $this->ultima_atualizacao;
            $options['importacao_andamento'] = false;
            update_option('pinedu_imovel_options', $options);
            $ret = [
                'success'=> true
                , 'ultima_atualizacao'=> $this->ultima_atualizacao
                , 'imoveis_importados' => $options['imoveis_importados']
            ];
            return $ret;
        } catch ( Exception $e ) {
            $ret = [
                'success'=> false
                , 'message'=> 'Ocorreu um erro durante o processamento: ' . $e->getMessage()
            ];
            return $ret;
        }
    }

	private function call_remote_server( $url, $max = 0, $offset = 0, $clicados = [], $ultima_atualizacao = null, $forcar = false, $ignorar_excluidos = false ) {
		$args = [ 'max' => $max, 'offset' => $offset, 'forcar' => $forcar ];
		if ( $clicados ) {
			$args['visitas'] = $clicados;
		}
        if ( isset($ignorar_excluidos) && ((bool)$ignorar_excluidos) === true ) {
            $args['ignorarExcluidos'] = $ignorar_excluidos;
        }
        if (is_development_mode()) {
            error_log("ultima_atualizacao: " . print_r($ultima_atualizacao, true));
        }
        if (empty($ultima_atualizacao) || strtolower($ultima_atualizacao) === 'null') {
            $ultima_atualizacao = new DateTime('1980-01-01T00:00:00.000Z');
        } else {
            $ultima_atualizacao = formataData_iso8601($ultima_atualizacao);
        }
        $args['ultimaAtualizacao'] = $ultima_atualizacao;

        if (is_development_mode()) {
            error_log("Argumentos ImportaImovel_1: " . print_r($args, true));
        }
		$data = PineduRequest::get( $url, $args );

		if ( ((bool)$data[ 'success' ]) != true ) {
            if (is_development_mode()) {
                error_log( "Imoveis success false " . print_r($data, true));
            }
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