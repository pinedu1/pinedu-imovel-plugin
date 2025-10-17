<?php
require_once plugin_dir_path(__FILE__) . 'PineduRequest.php';

class Pinedu_Imovel_Importar_Basicos {
	const ENDPOINT = '/wordpress/basicos';
	private $empresa;
	private $loja;
	private $corretor;
	private $contrato;
	private $tipo_imovel;
	private $cidades;
	private $faixa_valor;
	private $tipo_dependencia;
	public function __construct( ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-empresa.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-loja.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-corretor.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-contrato.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-tipo-imovel.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-cidade.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-faixa-valor.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pinedu-imovel-importa-tipo-dependencia.php';
		require_once PINEDU_PLUGIN_DIR . '/includes/classes/PrettyUrl.php';

		$this->empresa = new Pinedu_Imovel_Importa_Empresa( );
		$this->loja = new Pinedu_Imovel_Importa_Loja( );
		$this->corretor = new Pinedu_Imovel_Importa_Corretor( );
		$this->contrato = new Pinedu_Imovel_Importa_Contrato( );
		$this->tipo_imovel = new Pinedu_Imovel_Importa_Tipo_Imovel( );
		$this->cidades = new Pinedu_Imovel_Importa_Cidade( );
		$this->faixa_valor = new Pinedu_Imovel_Importa_Faixa_Valor( );
		$this->tipo_dependencia = new Pinedu_Imovel_Importa_Tipo_Dependencia( );
	}
    public function recupera_dados_json( $url, $forcar = false ) {
        wp_send_json( $this->recupera_dados( $url, $forcar ) );
        wp_die();
    }
    public function recupera_dados( $url, $forcar = false ) {
        $fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );
        $data = PineduRequest::post( $fullUrl, ['forcar' => $forcar] );
        if ( json_last_error( ) !== JSON_ERROR_NONE ) {
            wp_send_json_error( ['message' => 'Erro ao decodificar JSON: ' . json_last_error_msg( )] );
            return false;
        }
        return $data;
    }
	public function invoca_server( $url, $forcar = false ) {
		try {
			$data = $this->recupera_dados( $url, $forcar );
			if ( $data !== false && isset( $data['success'] ) && $data['success'] === true ) {
				// Trata Parametros da Empresa
				$this->importa_parametros( $data );
				// Trata Empresa
				$this->importa_empresa( $data );
				// Trata Loja
				$this->importa_loja( $data );
				// Trata Corretor
				$this->importa_corretor( $data );
				// Trata Tipo de Contrato [venda, locacao, lancamento]
				$this->importa_contratos( $data );
				// Trata Tipo de Imovel [apartamento, casa, sobrado]
				$this->importa_tipo_imoveis( $data );
				// Trata Cidades, Regiões e Bairros [Santo André > Centro > Jardim Eldorado]
				$this->importa_cidades( $data );
				// Trata Faixas de valores
				$this->importa_faixa_valor( $data );
				// Tipo de Dependencia
				$this->importa_tipo_dependencia( $data );
				// Faz o cache de Pretty Url
				$this->cria_url_cache();
			} else {
				wp_send_json_error( [
					'message' => $data['message'] ?? 'Formato do arquivo inválido ( Básicos )!'
				] );
			}
		} catch ( Exception $e ) {
			// Log do erro ( opcional )
			error_log( 'Erro durante a importação: ' . $e->getMessage( ) );
			wp_send_json_error( [
				'message' => 'Ocorreu um erro durante o processamento: ' . $e->getMessage( )
				, 'error_code' => 'import_error'
				, 'trace' => defined( 'WP_DEBUG' ) && WP_DEBUG ? $e->getTrace( ) : null
				, 'details' => WP_DEBUG ? $e->getTraceAsString( ) : null
			], 500 );
			return false;
		} finally {
			return true;
		}
	}
    public function importar_callback( $data ) {
        try {
            if ( isset( $data['success'] ) && $data['success'] === true ) {
                // Trata Parametros da Empresa
                $this->importa_parametros( $data );
                // Trata Empresa
                $this->importa_empresa( $data );
                // Trata Loja
                $this->importa_loja( $data );
                // Trata Corretor
                $this->importa_corretor( $data );
                // Trata Tipo de Contrato [venda, locacao, lancamento]
                $this->importa_contratos( $data );
                // Trata Tipo de Imovel [apartamento, casa, sobrado]
                $this->importa_tipo_imoveis( $data );
                // Trata Cidades, Regiões e Bairros [Santo André > Centro > Jardim Eldorado]
                $this->importa_cidades( $data );
                // Trata Faixas de valores
                $this->importa_faixa_valor( $data );
                // Tipo de Dependencia
                $this->importa_tipo_dependencia( $data );
            } else {
                wp_send_json_error( [
                    'message' => $data['message'] ?? 'Formato do arquivo inválido ( Básicos )!'
                ] );
            }
        } catch ( Exception $e ) {
            // Log do erro ( opcional )
            error_log( 'Erro durante a importação: ' . $e->getMessage( ) );
            return false;
        } finally {
            return true;
        }
    }
	private function importa_empresa( $data ) {
		if ( !isset( $data['empresa'] ) || !is_array( $data['empresa'] ) || empty( $data['empresa'] ) ) {
			wp_send_json_error( ['message' => 'Nó Empresa não encontrado ou vazio no JSON'] );
			return false;
		}
		return $this->empresa->importa( $data['empresa'] );
	}
	private function importa_loja( $data ) {
		if ( !isset( $data['lojas'] ) || !is_array( $data['lojas'] ) || empty( $data['lojas'] ) ) {
			wp_send_json_error( ['message' => 'Nó Lojas não encontrado ou vazio no JSON'] );
			return false;
		}
		return $this->loja->importar( $data['lojas'] );
	}
	private function importa_corretor( $data ) {
		if ( !isset( $data['corretores'] ) || !is_array( $data['corretores'] ) || empty( $data['corretores'] ) ) {
			wp_send_json_error( ['message' => 'Nó Corretores não encontrado ou vazio no JSON'] );
			return false;
		}
		return $this->corretor->importar( $data['corretores'] );
	}
	private function importa_contratos( $data ) {
		if ( !isset( $data['tipoContratos'] ) || !is_array( $data['tipoContratos'] ) || empty( $data['tipoContratos'] ) ) {
			wp_send_json_error( ['message' => 'Nó tipoContratos não encontrado ou vazio no JSON'] );
			return false;
		}
		return $this->contrato->importar( $data['tipoContratos'] );
	}
	private function importa_tipo_imoveis( $data ) {
		if ( !isset( $data['tipoImoveis'] ) || !is_array( $data['tipoImoveis'] ) || empty( $data['tipoImoveis'] ) ) {
			wp_send_json_error( ['message' => 'Nó tipoImoveis não encontrado ou vazio no JSON'] );
			return false;
		}

		return $this->tipo_imovel->importa_tipo_imoveis( $data['tipoImoveis'] );
	}
	private function importa_parametros( $data ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		$options = is_array( $options ?? null ) ? $options : [];
		$parametros = $data['parametroSistema'];
        //error_log( 'Parametros: ' . print_r( $parametros, true ) );
		$parametros = is_array( $parametros ?? null ) ? $parametros : [];
		$options['contrato'] = $parametros['contrato'] ?? ''; // Ou outro valor padrão
		unset( $options['contrato'] );
		if ( isset( $parametros['contrato'] ) && ( $parametros['contrato'] > 0 ) ) {
			$options['contrato'] = $parametros['contrato'];
		}
		unset( $options['tipo_imovel'] );
		if ( isset( $parametros['tipoImovel'] ) && !empty( $parametros['tipoImovel'] ) ) {
			$options['tipo_imovel'] = $parametros['tipoImovel'];
		}
		unset( $options['cidade'] );
		if ( isset( $parametros['cidade'] ) && !empty( $parametros['cidade'] ) ) {
			$options['cidade'] = $parametros['cidade'];
		}
		unset( $options['regiao'] );
		if ( isset( $parametros['regiao'] ) && !empty( $parametros['regiao'] ) ) {
			$options['regiao'] = $parametros['regiao'];
		}
		update_option( 'pinedu_imovel_options', $options );
        $options = get_option( 'pinedu_imovel_options', [] );
        //error_log( 'Options: ' . print_r( $options, true ) );
	}
	private function importa_cidades( $data ) {
		if ( !isset( $data['cidades'] ) || !is_array( $data['cidades'] ) || empty( $data['cidades'] ) ) {
			wp_send_json_error( ['message' => 'Nó cidades não encontrado ou vazio no JSON'] );
			return false;
		}

		return $this->cidades->importa_cidades( $data['cidades'] );
	}
	private function importa_faixa_valor( $data ) {
		if ( !isset( $data['faixaValores'] ) || !is_array( $data['faixaValores'] ) || empty( $data['faixaValores'] ) ) {
			wp_send_json_error( ['message' => 'Nó faixaValores não encontrado ou vazio no JSON'] );
			return false;
		}
		$faixas_valores = $data['faixaValores'];
		//
		$faixa_venda = $faixas_valores['venda'];
		$faixa_locacao = $faixas_valores['locacao'];
		$faixa_lancamento = $faixas_valores['lancamento'];
		//
		$this->faixa_valor->importa( $faixa_venda );
		$this->faixa_valor->importa( $faixa_locacao );
		$this->faixa_valor->importa( $faixa_lancamento );
	}
	private function importa_tipo_dependencia( $data ) {
		if ( !isset( $data['tipoDependencias'] ) || !is_array( $data['tipoDependencias'] ) || empty( $data['tipoDependencias'] ) ) {
			wp_send_json_error( ['message' => 'Nó tipoDependencias não encontrado ou vazio no JSON'] );
			return false;
		}
		$tipo_dependencias = $data['tipoDependencias'];
		$this->tipo_dependencia->importa( $tipo_dependencias );
	}

	private function cria_url_cache() {
		$pretty_urls = new PrettyUrl( true );
		$pretty_urls->do();
	}
}
