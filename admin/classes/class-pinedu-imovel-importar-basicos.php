<?php
require_once plugin_dir_path( __FILE__ ) . 'PineduRequest.php';
/**
 * Classe orquestradora para importação e sincronização dos cadastros básicos e metadados imobiliários no WordPress.
 * Recebe payloads JSON monolíticos ou fracionados oriundos do ERP e delega a persistência às classes especializadas.
 *
 * @author Eduardo Pinheiro da Silva
 */
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
	/**
	 * Carrega as dependências dos módulos de importação e instancia os objetos de persistência de cada entidade.
	 */
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
	/**
	 * Recupera os cadastros básicos do servidor remoto e emite a resposta diretamente em formato JSON.
	 *
	 * @param string $url URL base do servidor ERP.
	 * @param bool $forcar Indica se a exportação completa deve ser forçada na origem.
	 * @return void
	 */
	public function recupera_dados_json( $url, $forcar = false ) {
		wp_send_json( $this->recupera_dados( $url, $forcar ) );
		wp_die( );
	}
	/**
	 * Realiza requisição POST ao endpoint do ERP para buscar o payload de cadastros básicos.
	 *
	 * @param string $url URL base do servidor ERP.
	 * @param bool $forcar Flag para forçar regeneração dos dados.
	 * @return array|false Array associativo com o payload decodificado ou false em caso de erro de parsing.
	 */
	public function recupera_dados( $url, $forcar = false ) {
		$fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );
		$data = PineduRequest::post( $fullUrl, [ 'forcar' => $forcar ] );
		if ( json_last_error( ) !== JSON_ERROR_NONE ) {
			wp_send_json_error( [ 'message' => 'Erro ao decodificar JSON: ' . json_last_error_msg( ) ] );
			return false;
		}
		return $data;
	}
	/**
	 * Consulta ativamente o servidor ERP, processa todas as entidades básicas em lote e reconstrói o cache de URLs amigáveis.
	 *
	 * @param string $url URL base do servidor ERP.
	 * @param bool $forcar Define se a sincronização deve ignorar filtros incrementais.
	 * @return bool Retorna true ao finalizar o fluxo.
	 */
	public function invoca_server( $url, $forcar = false ) {
		try {
			$data = $this->recupera_dados( $url, $forcar );
			if ( $data !== false && isset( $data['success'] ) && $data['success'] === true ) {
				// Trata Parâmetros do Sistema
				$this->importa_parametros( $data );
				// Trata Dados Cadastrais da Empresa
				$this->importa_empresa( $data );
				// Trata Unidades e Lojas
				$this->importa_loja( $data );
				// Trata Equipe de Corretores
				$this->importa_corretor( $data );
				// Trata Tipos de Contrato [Venda, Locação, Lançamento]
				$this->importa_contratos( $data );
				// Trata Tipos de Imóvel [Apartamento, Casa, Comercial, etc.]
				$this->importa_tipo_imoveis( $data );
				// Trata Árvore Geográfica de Cidades, Regiões e Bairros
				$this->importa_cidades( $data );
				// Trata Faixas de Valores para Filtros de Busca
				$this->importa_faixa_valor( $data );
				// Trata Tipos de Dependências e Características dos Imóveis
				$this->importa_tipo_dependencia( $data );
				// Reconstrói o Cache de PrettyUrl
				$this->cria_url_cache( );
			} else {
				wp_send_json_error( [ 'message' => $data['message'] ?? 'Formato do arquivo inválido ( Básicos )!' ] );
			}
		} catch ( Exception $e ) {
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
	/**
	 * Processa de forma passiva o payload monolítico completo recebido via endpoint REST legado (update_basicos).
	 *
	 * @param array $data Array associativo decodificado do corpo da requisição REST.
	 * @return bool Retorna true ao finalizar o bloco de execução.
	 */
	public function importar_callback( $data ) {
		try {
			if ( isset( $data['success'] ) && $data['success'] === true ) {
				// Trata Parâmetros do Sistema
				$this->importa_parametros( $data );
				// Trata Dados Cadastrais da Empresa
				$this->importa_empresa( $data );
				// Trata Unidades e Lojas
				$this->importa_loja( $data );
				// Trata Equipe de Corretores
				$this->importa_corretor( $data );
				// Trata Tipos de Contrato [Venda, Locação, Lançamento]
				$this->importa_contratos( $data );
				// Trata Tipos de Imóvel [Apartamento, Casa, Comercial, etc.]
				$this->importa_tipo_imoveis( $data );
				// Trata Árvore Geográfica de Cidades, Regiões e Bairros
				$this->importa_cidades( $data );
				// Trata Faixas de Valores para Filtros de Busca
				$this->importa_faixa_valor( $data );
				// Trata Tipos de Dependências e Características dos Imóveis
				$this->importa_tipo_dependencia( $data );
			} else {
				wp_send_json_error( [ 'message' => $data['message'] ?? 'Formato do arquivo inválido ( Básicos )!' ] );
			}
		} catch ( Exception $e ) {
			error_log( 'Erro durante a importação: ' . $e->getMessage( ) );
			return false;
		} finally {
			return true;
		}
	}
	/**
	 * Valida e delega a importação dos dados cadastrais da imobiliária (nó 'empresa').
	 *
	 * @param array $data Payload contendo a chave 'empresa'.
	 * @return mixed Resultado da operação de persistência de Pinedu_Imovel_Importa_Empresa ou false se inválido.
	 */
	public function importa_empresa( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Empresa: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['empresa'] ) || !is_array( $data['empresa'] ) || empty( $data['empresa'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó Empresa não encontrado ou vazio no JSON' ] );
			return false;
		}
		return $this->empresa->importa( $data['empresa'] );
	}
	/**
	 * Valida e delega a importação da lista de filiais/lojas (nó 'lojas').
	 *
	 * @param array $data Payload contendo a chave 'lojas'.
	 * @return mixed Resultado da operação de persistência de Pinedu_Imovel_Importa_Loja ou false se inválido.
	 */
	public function importa_loja( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Lojas: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['lojas'] ) || !is_array( $data['lojas'] ) || empty( $data['lojas'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó Lojas não encontrado ou vazio no JSON' ] );
			return false;
		}
		return $this->loja->importar( $data['lojas'] );
	}
	/**
	 * Valida e delega a importação do quadro de corretores (nó 'corretores').
	 *
	 * @param array $data Payload contendo a chave 'corretores'.
	 * @return mixed Resultado da operação de persistência de Pinedu_Imovel_Importa_Corretor ou false se inválido.
	 */
	public function importa_corretor( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Corretores: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['corretores'] ) || !is_array( $data['corretores'] ) || empty( $data['corretores'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó Corretores não encontrado ou vazio no JSON' ] );
			return false;
		}
		return $this->corretor->importar( $data['corretores'] );
	}
	/**
	 * Valida e delega a importação das modalidades de contrato (nó 'tipoContratos').
	 *
	 * @param array $data Payload contendo a chave 'tipoContratos'.
	 * @return mixed Resultado da operação de persistência de Pinedu_Imovel_Importa_Contrato ou false se inválido.
	 */
	public function importa_contratos( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Contratos: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['tipoContratos'] ) || !is_array( $data['tipoContratos'] ) || empty( $data['tipoContratos'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó tipoContratos não encontrado ou vazio no JSON' ] );
			return false;
		}
		return $this->contrato->importar( $data['tipoContratos'] );
	}
	/**
	 * Valida e delega a importação das categorias de imóveis (nó 'tipoImoveis').
	 *
	 * @param array $data Payload contendo a chave 'tipoImoveis'.
	 * @return mixed Resultado da operação de persistência de Pinedu_Imovel_Importa_Tipo_Imovel ou false se inválido.
	 */
	public function importa_tipo_imoveis( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Tipo Imoveis: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['tipoImoveis'] ) || !is_array( $data['tipoImoveis'] ) || empty( $data['tipoImoveis'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó tipoImoveis não encontrado ou vazio no JSON' ] );
			return false;
		}
		return $this->tipo_imovel->importa_tipo_imoveis( $data['tipoImoveis'] );
	}
	/**
	 * Verifica a presença do nó 'parametroSistema' no payload e aciona a gravação nas opções do WordPress.
	 *
	 * @param array $data Payload contendo a chave 'parametroSistema'.
	 * @return void
	 */
	public function importa_parametros( $data ) {
		if ( isset( $data['parametroSistema'] ) ) {
			$this->importa_parametros_data( $data['parametroSistema'] );
		}
	}
	/**
	 * Persiste os parâmetros padrão do sistema (contrato, tipo de imóvel, cidade e região) em 'pinedu_imovel_options'.
	 *
	 * @param array $parametros Mapa de parâmetros extraído do nó 'parametroSistema'.
	 * @return void
	 */
	public function importa_parametros_data( $parametros ) {
		$options = get_option( 'pinedu_imovel_options', [ ] );
		if ( isset( $parametros['contrato'] ) && ( $parametros['contrato'] > 0 ) ) {
			$options['contrato'] = $parametros['contrato'];
		}
		if ( isset( $parametros['tipoImovel'] ) && !empty( $parametros['tipoImovel'] ) ) {
			$options['tipo_imovel'] = $parametros['tipoImovel'];
		}
		if ( isset( $parametros['cidade'] ) && !empty( $parametros['cidade'] ) ) {
			$options['cidade'] = $parametros['cidade'];
		}
		if ( isset( $parametros['regiao'] ) && !empty( $parametros['regiao'] ) ) {
			$options['regiao'] = $parametros['regiao'];
		}
		update_option( 'pinedu_imovel_options', $options );
	}
	/**
	 * Valida e delega a importação da hierarquia geográfica de cidades, regiões e bairros (nó 'cidades').
	 *
	 * @param array $data Payload contendo a chave 'cidades'.
	 * @return mixed Resultado da operação de persistência de Pinedu_Imovel_Importa_Cidade ou false se inválido.
	 */
	public function importa_cidades( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Cidades: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['cidades'] ) || !is_array( $data['cidades'] ) || empty( $data['cidades'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó cidades não encontrado ou vazio no JSON' ] );
			return false;
		}
		return $this->cidades->importa_cidades( $data['cidades'] );
	}
	/**
	 * Valida e delega a importação das faixas de valores para venda, locação e lançamento (nó 'faixaValores').
	 *
	 * @param array $data Payload contendo a chave 'faixaValores'.
	 * @return false|void Retorna false caso o nó seja inválido.
	 */
	public function importa_faixa_valor( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Faixa Valor: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['faixaValores'] ) || !is_array( $data['faixaValores'] ) || empty( $data['faixaValores'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó faixaValores não encontrado ou vazio no JSON' ] );
			return false;
		}
		$faixas_valores = $data['faixaValores'];
		$faixa_venda = $faixas_valores['venda'];
		$faixa_locacao = $faixas_valores['locacao'];
		$faixa_lancamento = $faixas_valores['lancamento'];
		$this->faixa_valor->importa( $faixa_venda );
		$this->faixa_valor->importa( $faixa_locacao );
		$this->faixa_valor->importa( $faixa_lancamento );
	}
	/**
	 * Valida e delega a importação do catálogo de dependências e características de imóveis (nó 'tipoDependencias').
	 *
	 * @param array $data Payload contendo a chave 'tipoDependencias'.
	 * @return false|void Retorna false caso o nó seja inválido.
	 */
	public function importa_tipo_dependencia( $data ) {
		if ( is_development_mode( ) ) {
			error_log( 'Importa Tipo Dependencias: ' . print_r( $data, true ) );
		}
		if ( !isset( $data['tipoDependencias'] ) || !is_array( $data['tipoDependencias'] ) || empty( $data['tipoDependencias'] ) ) {
			wp_send_json_error( [ 'message' => 'Nó tipoDependencias não encontrado ou vazio no JSON' ] );
			return false;
		}
		$tipo_dependencias = $data['tipoDependencias'];
		$this->tipo_dependencia->importa( $tipo_dependencias );
	}
	/**
	 * Instancia o gerenciador PrettyUrl e reconstrói as regras de cache de rotas SEO.
	 *
	 * @return void
	 */
	private function cria_url_cache( ) {
		$pretty_urls = new PrettyUrl( true );
		$pretty_urls->do( );
	}
}