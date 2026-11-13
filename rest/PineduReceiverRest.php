<?php
require_once plugin_dir_path( __FILE__ ) . '../admin/classes/PineduRequest.php';
require_once plugin_dir_path( __FILE__ ) . '../admin/classes/class-pinedu-imovel-importar-basicos.php';
require_once plugin_dir_path( __FILE__ ) . '../admin/classes/class-pinedu-imovel-importar-imoveis.php';
/**
 * Controlador REST responsável por recepcionar os payloads de sincronização do ERP no WordPress,
 * gerenciar o ciclo de vida da importação, exportar eventos de cliques e regerar artefatos de SEO.
 *
 * @author Eduardo Pinheiro da Silva
 */
class PineduReceiverRest extends PineduRequest {
	private const DEBUG = true;
	private static $instance = null;
	private function __construct( ) {
		// Construtor privado
	}
	private function __clone( ) {}
	public function __wakeup( ) {
		throw new \Exception( "Cannot unserialize a singleton." );
	}
	private static function getInstance( ): PineduReceiverRest {
		if ( self::$instance === null ) {
			self::$instance = new self( );
		}
		return self::$instance;
	}
	/**
	 * Registra todas as rotas REST do plugin (controle de transação, metadados fracionados, imóveis e cliques).
	 *
	 * @return void
	 */
	public static function instala_rest_end_point( ) {
		$endpoints = [
			'inicializar' => [ 'method' => 'POST', 'callback' => 'inicializar_atualizacao' ]
			, 'encerrar' => [ 'method' => 'POST', 'callback' => 'encerrar_atualizacao' ]
			, 'update_basicos' => [ 'method' => 'POST', 'callback' => 'receber_basicos' ]
			, 'update_parametro_sistema' => [ 'method' => 'POST', 'callback' => 'receber_parametro_sistema' ]
			, 'update_tipo_dependencias' => [ 'method' => 'POST', 'callback' => 'receber_tipo_dependencias' ]
			, 'update_faixa_valores' => [ 'method' => 'POST', 'callback' => 'receber_faixa_valores' ]
			, 'update_empresa' => [ 'method' => 'POST', 'callback' => 'receber_empresa' ]
			, 'update_lojas' => [ 'method' => 'POST', 'callback' => 'receber_lojas' ]
			, 'update_corretores' => [ 'method' => 'POST', 'callback' => 'receber_corretores' ]
			, 'update_tipo_contratos' => [ 'method' => 'POST', 'callback' => 'receber_tipo_contratos' ]
			, 'update_tipo_imoveis' => [ 'method' => 'POST', 'callback' => 'receber_tipo_imoveis' ]
			, 'update_cidades' => [ 'method' => 'POST', 'callback' => 'receber_cidades' ]
			, 'update_imoveis' => [ 'method' => 'POST', 'callback' => 'receber_imoveis' ]
			, 'clicks' => [ 'method' => 'GET', 'callback' => 'enviar_clicks' ]
		];
		foreach ( $endpoints as $rota => $config ) {
			register_rest_route( 'pinedu-imovel/v1', $rota, array(
				'methods' => $config['method']
				, 'callback' => array( __CLASS__, $config['callback'] )
				, 'permission_callback' => array( __CLASS__, 'verify_credentials' )
			) );
		}
	}
	/**
	 * Coleta os eventos de visita registrados nos imóveis publicados, devolve-os ao ERP e remove os registros processados.
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST.
	 * @return \WP_REST_Response|\WP_Error Resposta formatada contendo a lista de cliques coletados.
	 */
	public static function enviar_clicks( $request ) {
		global $wpdb;
		// Query SQL para buscar os eventos isolados e seus meta_ids ( para exclusão segura posterior )
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
		$clicks_formatados = [ ];
		$meta_ids_para_excluir = [ ];
		if ( ! empty( $resultados ) ) {
			foreach ( $resultados as $linha ) {
				$dados = maybe_unserialize( $linha->evento_dados );
				if ( is_array( $dados ) && isset( $dados['cookie'] ) && isset( $dados['data'] ) ) {
					// É um registro unitário na fila de eventos
					$clicks_formatados[] = [ 'referencia' => $linha->referencia, 'data' => $dados['data'], 'cookie' => $dados['cookie'], 'clicks' => 1 ];
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
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ( $ids_imploded )" );
		}
		$response = [ 'success' => count( $clicks_formatados ) > 0, 'clicks' => $clicks_formatados ];
		return rest_ensure_response( $response );
	}
	/**
	 * Inicia a transação de sincronização marcando a flag de importação em andamento nas opções do sistema.
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST.
	 * @return void
	 */
	public static function inicializar_atualizacao( $request ) {
		$options = get_option( 'pinedu_imovel_options', [ ] );
		if ( isset( $options['importacao_andamento'] ) && $options['importacao_andamento'] === true ) {
			wp_send_json_error( [ 'message' => 'Importação em andamento por outro processo. Tente novamente mais tarde!' ] );
			wp_die( );
		}
		if ( ! isset( $options['ultima_atualizacao'] ) ) {
			$options['ultima_atualizacao'] = DateTime::createFromFormat( 'd/m/Y H:i:sT', '01/01/1980 00:00:00-300' );
		}
		$options['importacao_andamento'] = true;
		$options['inicio_importacao'] = new DateTime( );
		update_option( 'pinedu_imovel_options', $options );
		wp_send_json( [ 'importacao_andamento' => $options['importacao_andamento'], 'inicio_importacao' => formataData_iso8601( $options['inicio_importacao'] ), 'ultima_atualizacao' => formataData_iso8601( $options['ultima_atualizacao'] ) ] );
	}
	/**
	 * Finaliza a transação de sincronização, atualiza estatísticas de tempo/token e aciona rotinas pós-processamento.
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST contendo o resumo da importação.
	 * @return void
	 */
	public static function encerrar_atualizacao( $request ) {
		$json_string = $request->get_body( );
		$data = json_decode( $json_string, true );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'encerrar_atualizacao: ' . print_r( $data, true ) );
		}
		$token = isset( $data['token'] ) ? sanitize_text_field( $data['token'] ) : '';
		$imoveis_importados = isset( $data['imoveis_importados'] ) ? sanitize_text_field( $data['imoveis_importados'] ) : 0;
		$options = get_option( 'pinedu_imovel_options', [ ] );
		$options['ultima_atualizacao'] = new \DateTime( );
		if ( isset( $options['inicio_importacao'] ) && $options['inicio_importacao'] instanceof \DateTime ) {
			$options['tempo_utilizado'] = $options['inicio_importacao']->diff( $options['ultima_atualizacao'] );
		}
		$options['imoveis_importados'] = $imoveis_importados;
		$options['token'] = $token;
		$options['importacao_andamento'] = false;
		update_option( 'pinedu_imovel_options', $options );
		$resposta = [ 'status' => 'sucesso', 'dataAtualizacao' => formataData_iso8601( $options['ultima_atualizacao'] ), 'importacao_andamento' => false ];
		if ( isset( $data['POST_PROCESS_ACTION'] ) ) {
			$action = sanitize_text_field( $data['POST_PROCESS_ACTION'] );
			self::pinedu_trigger_action_callback( $action );
		} else {
			self::generate_site_map( );
			self::generate_json_ld( );
			self::generate_feed( );
			self::optimize_tables( );
		}
		wp_send_json( $resposta );
	}
	/**
	 * Verifica se o arquivo precisa ser atualizado ( mais velho que 03:50:00 ).
	 *
	 * @param string $file_path Caminho físico absoluto do arquivo no servidor.
	 * @return bool True se o arquivo não existir ou tiver mais de 3 horas e 50 minutos.
	 */
	private static function arquivo_necessita_atualizacao( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return true;
		}
		$idade_segundos = time( ) - filemtime( $file_path );
		$limite_segundos = ( 3 * 3600 ) + ( 50 * 60 );
		return $idade_segundos > $limite_segundos;
	}
	/**
	 * Recepciona o payload monolítico legado de cadastros básicos e delega para Pinedu_Imovel_Importar_Basicos.
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST.
	 * @return array Status da operação e timestamp de atualização.
	 */
	public static function receber_basicos( $request ) {
		$json_string = $request->get_body( );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'JSON recebido: ' . $json_string );
		}
		$data = json_decode( $json_string, true );
		$importa_basicos = new Pinedu_Imovel_Importar_Basicos( );
		$result = $importa_basicos->importar_callback( $data );
		if ( $result === true ) {
			$data = [ 'success' => true, 'dataAtualizacao' => formataData_iso8601( new \DateTime( 'now', new \DateTimeZone( 'America/Sao_Paulo' ) ) ) ];
			return $data;
		}
		$data = [ 'success' => false, 'message' => 'Erro na importação dos dados Básicos' ];
		return $data;
	}
	// --- MÉTODOS AUXILIARES PARA AS NOVAS ROTAS FRACIONADAS ---
	/**
	 * Processa individualmente cada fração de metadados básicos enviada pelo ERP, tratando retornos nulos e exceções.
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST contendo a fração JSON.
	 * @param string $metodo_importacao Nome do método público a ser invocado em Pinedu_Imovel_Importar_Basicos.
	 * @return array Resultado da operação contendo 'success' e 'dataAtualizacao' ou 'message' com o erro capturado.
	 */
	private static function processar_fracao_basicos( $request, $metodo_importacao ) {
		try {
			$json_string = $request->get_body( );
			if ( is_development_mode( ) ) {
				error_log( "JSON {$metodo_importacao} recebido: " . $json_string );
			}
			$data = json_decode( $json_string, true );
			$importa_basicos = new Pinedu_Imovel_Importar_Basicos( );
			if ( method_exists( $importa_basicos, $metodo_importacao ) ) {
				$result = $importa_basicos->$metodo_importacao( $data );
				if ( is_null( $result ) ) {
					$result = true;
				}
				if ( is_development_mode( ) ) {
					error_log( "RESULT: {$metodo_importacao} recebido: " . print_r( $result, true ) );
				}
			} else {
				$result = $importa_basicos->importar_callback( $data );
			}
			if ( $result === true ) {
				return [ 'success' => true, 'dataAtualizacao' => formataData_iso8601( new \DateTime( 'now', new \DateTimeZone( 'America/Sao_Paulo' ) ) ) ];
			}
			return [ 'success' => false, 'message' => "Erro na importação de metadados via: {$metodo_importacao}" ];
		} catch ( \Throwable $e ) {
			error_log( "Erro na execução de {$metodo_importacao}: " . $e->getMessage( ) );
			return [ 'success' => false, 'message' => "Erro na importação de metadados via: {$metodo_importacao} - " . $e->getMessage( ) ];
		}
	}
	public static function receber_parametro_sistema( $request ) { return self::processar_fracao_basicos( $request, 'importa_parametros' ); }
	public static function receber_tipo_dependencias( $request ) { return self::processar_fracao_basicos( $request, 'importa_tipo_dependencia' ); }
	public static function receber_faixa_valores( $request ) { return self::processar_fracao_basicos( $request, 'importa_faixa_valor' ); }
	public static function receber_empresa( $request ) { return self::processar_fracao_basicos( $request, 'importa_empresa' ); }
	public static function receber_lojas( $request ) { return self::processar_fracao_basicos( $request, 'importa_loja' ); }
	public static function receber_corretores( $request ) { return self::processar_fracao_basicos( $request, 'importa_corretor' ); }
	public static function receber_tipo_contratos( $request ) { return self::processar_fracao_basicos( $request, 'importa_contratos' ); }
	public static function receber_tipo_imoveis( $request ) { return self::processar_fracao_basicos( $request, 'importa_tipo_imoveis' ); }
	public static function receber_cidades( $request ) { return self::processar_fracao_basicos( $request, 'importa_cidades' ); }
	// ---------------------------------------------------------
	/**
	 * Recepciona o lote de imóveis enviados pelo ERP e delega a importação para Pinedu_Imovel_Importar_Imoveis.
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST contendo o lote de imóveis.
	 * @return mixed Resultado do processamento do lote de imóveis.
	 */
	public static function receber_imoveis( $request ) {
		$json_string = $request->get_body( );
		$data = json_decode( $json_string, true );
		if ( is_development_mode( ) ) {
			error_log( "PineduReceiverRest:receber_imoveis" . print_r( $data, true ) );
		}
		$importa_imoveis = new Pinedu_Imovel_Importar_Imoveis( );
		$result = $importa_imoveis->importar_callback( $data );
		return $result;
	}
	/**
	 * Valida as credenciais de autenticação (Header Authorization Bearer e fallback Username/Password).
	 *
	 * @param \WP_REST_Request $request Objeto da requisição REST.
	 * @return bool True se o token ou as credenciais forem válidos, false caso contrário.
	 */
	public static function verify_credentials( $request ) {
		$rota = $request->get_route( );
		$metodo = $request->get_method( );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( '=========================================' );
			error_log( 'NOVA REQUISIÇÃO NA URL: ' . $metodo . ' ' . $rota );
			error_log( '=========================================' );
		}
		$auth_header = $request->get_header( 'Authorization' );
		$username = $request->get_header( 'Username' );
		$password = $request->get_header( 'Password' );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'Username: ' . $username . ' | Password: ' . $password . ' | Token: ' . $auth_header );
		}
		if ( empty( $auth_header ) ) {
			if ( self::DEBUG && is_development_mode( ) ) {
				error_log( 'ERRO: Authorization header missing na URL ' . $rota );
			}
			return false;
		}
		if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			$bearer_token = trim( $matches[1] );
			if ( self::DEBUG && is_development_mode( ) ) {
				error_log( 'Bearer Token recebido com sucesso: ' . $bearer_token );
			}
			return self::validate_bearer_token( $bearer_token, $username, $password );
		}
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'ERRO: Invalid Authorization format na URL: ' . $rota );
			error_log( 'Formato que chegou e foi rejeitado: ' . $auth_header );
		}
		return false;
	}
	/**
	 * Confronta o Bearer Token recebido com o token salvo nas opções ou valida via usuário e senha renovando o token.
	 *
	 * @param string $token Token JWT extraído do cabeçalho Authorization.
	 * @param string|null $req_username Usuário recebido no cabeçalho da requisição.
	 * @param string|null $req_password Senha recebida no cabeçalho da requisição.
	 * @return bool True se validado com sucesso.
	 */
	private static function validate_bearer_token( $token, $req_username, $req_password ): bool {
		$options = get_option( 'pinedu_imovel_options', [ ] );
		if ( isset( $options['token'] ) && $options['token'] === $token ) {
			return true;
		}
		$saved_username = $options['token_username'] ?? '';
		$saved_password = $options['token_password'] ?? '';
		if ( ! empty( $req_username ) && ! empty( $req_password ) && $req_username === $saved_username && $req_password === $saved_password ) {
			$options['token'] = $token;
			update_option( 'pinedu_imovel_options', $options );
			if ( self::DEBUG && is_development_mode( ) ) {
				error_log( 'Novo Token validado e registrado com sucesso via Username/Password.' );
			}
			return true;
		}
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'Invalid Token and Invalid Credentials' );
		}
		return false;
	}
	public static function pinedu_trigger_action_callback( $action ) {
		switch ( $action ) {
			case 'OPTIMIZE_TABLES':
				self::optimize_tables( true );
				break;
			case 'GENERATE_SITE_MAP':
				self::generate_site_map( true );
				break;
			case 'GENERATE_JSON_LD':
				self::generate_json_ld( true );
				break;
			case 'GENERATE_FEED':
				self::generate_feed( true );
				break;
		}
	}
	/**
	 * Executa o comando OPTIMIZE TABLE em todas as tabelas do WordPress aos domingos às 06h ou quando forçado.
	 *
	 * @param bool $forcar Ignora a trava de dia/horário e intervalo mínimo quando true.
	 * @return void
	 */
	public static function optimize_tables( $forcar = false ) {
		$dia_semana = ( int ) wp_date( 'w' );
		$hora_atual = ( int ) wp_date( 'H' );
		if ( ( $forcar == false ) && ( $dia_semana !== 0 ) || ( $hora_atual !== 6 ) ) {
			return;
		}
		$options = get_option( 'pinedu_imovel_options', [ ] );
		$ultima_otimizacao = isset( $options['ultima_otimizacao'] ) ? ( int ) $options['ultima_otimizacao'] : 0;
		$idade_segundos = time( ) - $ultima_otimizacao;
		if ( ( $forcar === false ) && ( $idade_segundos < 7200 ) ) {
			if ( self::DEBUG && is_development_mode( ) ) {
				error_log( 'OPTIMIZE TABLE ignorado: Já foi executado recentemente nesta janela.' );
			}
			return;
		}
		global $wpdb;
		$tabelas = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
		if ( ! empty( $tabelas ) ) {
			$tabelas_string = implode( ', ', $tabelas );
			$wpdb->query( "OPTIMIZE TABLE {$tabelas_string}" );
		}
		$options['ultima_otimizacao'] = time( );
		update_option( 'pinedu_imovel_options', $options );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'OPTIMIZE TABLE executado com sucesso para TODAS as tabelas no domingo às ' . wp_date( 'H:i:s' ) );
		}
	}
	/**
	 * Consulta centralizada e otimizada ( apenas 1 hit no banco ).
	 *
	 * @return array Lista de objetos contendo os dados dos imóveis ativos publicados.
	 */
	public static function obter_dados_imoveis_ativos( ) {
		global $wpdb;
		$query = "
			SELECT
				p.ID,
				p.post_title,
				p.post_name,
				p.post_modified,
				pm_tipo_nome.meta_value AS tipoImovelNome,
				pm_tipo_id.meta_value AS tipoImovelId,
				pm_cidade.meta_value AS cidade,
				pm_bairro.meta_value AS bairro,
				pm_ref.meta_value AS referencia,
				pm_thumb.meta_value AS thumbnail_id,
				pm_av.meta_value AS ativarVenda,
				pm_vv.meta_value AS vendaValor,
				pm_al.meta_value AS ativarLocacao,
				pm_lv.meta_value AS locacaoValor,
				pm_an.meta_value AS ativarLancamento,
				pm_nv.meta_value AS lancamentoValor
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_status
				ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D'
			INNER JOIN {$wpdb->postmeta} pm_tipo_id
				ON p.ID = pm_tipo_id.post_id AND pm_tipo_id.meta_key = 'tipoImovel_id'
			INNER JOIN {$wpdb->postmeta} pm_tipo_nome
				ON p.ID = pm_tipo_nome.post_id AND pm_tipo_nome.meta_key = 'tipoImovelNome'
			LEFT JOIN {$wpdb->postmeta} pm_cidade
				ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade'
			LEFT JOIN {$wpdb->postmeta} pm_bairro
				ON p.ID = pm_bairro.post_id AND pm_bairro.meta_key = 'bairro'
			LEFT JOIN {$wpdb->postmeta} pm_ref
				ON p.ID = pm_ref.post_id AND pm_ref.meta_key = 'referencia'
			LEFT JOIN {$wpdb->postmeta} pm_thumb
				ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
			LEFT JOIN {$wpdb->postmeta} pm_av ON p.ID = pm_av.post_id AND pm_av.meta_key = 'ativarVenda'
			LEFT JOIN {$wpdb->postmeta} pm_vv ON p.ID = pm_vv.post_id AND pm_vv.meta_key = 'vendaValor'
			LEFT JOIN {$wpdb->postmeta} pm_al ON p.ID = pm_al.post_id AND pm_al.meta_key = 'ativarLocacao'
			LEFT JOIN {$wpdb->postmeta} pm_lv ON p.ID = pm_lv.post_id AND pm_lv.meta_key = 'locacaoValor'
			LEFT JOIN {$wpdb->postmeta} pm_an ON p.ID = pm_an.post_id AND pm_an.meta_key = 'ativarLancamento'
			LEFT JOIN {$wpdb->postmeta} pm_nv ON p.ID = pm_nv.post_id AND pm_nv.meta_key = 'lancamentoValor'
			WHERE p.post_type = 'imovel'
				AND p.post_status = 'publish'
		";
		return $wpdb->get_results( $query );
	}
	/**
	 * Gera o arquivo XML de sitemap de imóveis ativos (sitemap_imoveis.xml).
	 *
	 * @param bool $forcar Ignora janelas de horário e tempo de cache do arquivo quando true.
	 * @return void
	 */
	public static function generate_site_map( $forcar = false ) {
		$hora_atual = ( int ) wp_date( 'H' );
		if ( ( $forcar === false ) && ( $hora_atual !== 0 && $hora_atual !== 12 ) ) {
			return;
		}
		$file_path = ABSPATH . 'sitemap_imoveis.xml';
		if ( ( $forcar === false ) && ( ! self::arquivo_necessita_atualizacao( $file_path ) ) ) {
			return;
		}
		$resultados = self::obter_dados_imoveis_ativos( );
		$handle = fopen( $file_path, 'w' );
		if ( ! $handle ) {
			error_log( 'Não foi possível abrir sitemap_imoveis.xml para gravação.' );
			return;
		}
		$base_url = trailingslashit( home_url( ) ) . 'imoveis/';
		fwrite( $handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL );
		fwrite( $handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL );
		if ( ! empty( $resultados ) ) {
			foreach ( $resultados as $imovel ) {
				$loc = esc_url( $base_url . $imovel->post_name . '/' );
				$lastmod = date( 'c', strtotime( $imovel->post_modified ) );
				$node = "\t<url>\n";
				$node .= "\t\t<loc>{$loc}</loc>\n";
				$node .= "\t\t<lastmod>{$lastmod}</lastmod>\n";
				$node .= "\t\t<changefreq>daily</changefreq>\n";
				$node .= "\t</url>\n";
				fwrite( $handle, $node );
			}
		}
		fwrite( $handle, '</urlset>' );
		fclose( $handle );
	}
	/**
	 * Gera o catálogo estruturado Schema.org em formato JSON-LD (catalog_data.json).
	 *
	 * @param bool $forcar Ignora janelas de horário e tempo de cache do arquivo quando true.
	 * @return void
	 */
	public static function generate_json_ld( $forcar = false ) {
		$hora_atual = ( int ) wp_date( 'H' );
		if ( ( $forcar === false ) && ( $hora_atual !== 4 && $hora_atual !== 16 ) ) {
			return;
		}
		$file_path = ABSPATH . 'catalog_data.json';
		if ( ( $forcar === false ) && ( ! self::arquivo_necessita_atualizacao( $file_path ) ) ) {
			return;
		}
		$resultados = self::obter_dados_imoveis_ativos( );
		$handle = fopen( $file_path, 'w' );
		if ( ! $handle ) {
			error_log( 'Não foi possível abrir catalog_data.json para gravação.' );
			return;
		}
		$base_url = trailingslashit( home_url( ) ) . 'imoveis/';
		fwrite( $handle, '[' . PHP_EOL );
		$primeiro_item = true;
		if ( ! empty( $resultados ) ) {
			foreach ( $resultados as $imovel ) {
				$offers = [ ];
				$tipo_imovel_formatado = mb_convert_case( $imovel->tipoImovelNome ?? '', MB_CASE_TITLE, 'UTF-8' );
				$cidade_formatada = mb_convert_case( $imovel->cidade ?? '', MB_CASE_TITLE, 'UTF-8' );
				$bairro_formatado = mb_convert_case( $imovel->bairro ?? '', MB_CASE_TITLE, 'UTF-8' );
				if ( ( 1 == intval( $imovel->ativarVenda ) ) && ! empty( $imovel->vendaValor ) ) {
					$offers[] = [ '@type' => 'Offer', 'price' => $imovel->vendaValor, 'priceCurrency' => 'BRL', 'businessFunction' => 'http://purl.org/goodrelations/v1#Sell' ];
				}
				if ( ( 1 == intval( $imovel->ativarLancamento ) ) && ! empty( $imovel->lancamentoValor ) ) {
					$offers[] = [ '@type' => 'Offer', 'price' => $imovel->lancamentoValor, 'priceCurrency' => 'BRL', 'businessFunction' => 'http://purl.org/goodrelations/v1#Sell' ];
				}
				if ( ( 1 == intval( $imovel->ativarLocacao ) ) && ! empty( $imovel->locacaoValor ) ) {
					$offers[] = [ '@type' => 'Offer', 'price' => $imovel->locacaoValor, 'priceCurrency' => 'BRL', 'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut' ];
				}
				$item = [
					'@context' => 'https://schema.org'
					, '@type' => 'Accommodation'
					, 'name' => $imovel->post_title
					, 'url' => esc_url( $base_url . $imovel->post_name . '/' )
					, 'sku' => $imovel->referencia ?? ''
					, 'category' => $tipo_imovel_formatado
				];
				if ( ! empty( $cidade_formatada ) || ! empty( $bairro_formatado ) ) {
					$item['address'] = [ '@type' => 'PostalAddress' ];
					if ( ! empty( $cidade_formatada ) ) {
						$item['address']['addressLocality'] = $cidade_formatada;
					}
					if ( ! empty( $bairro_formatado ) ) {
						$item['address']['streetAddress'] = $bairro_formatado;
					}
				}
				if ( ! empty( $offers ) ) {
					$item['offers'] = ( count( $offers ) === 1 ) ? $offers[0] : $offers;
				}
				$json_string = json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				if ( $primeiro_item ) {
					fwrite( $handle, $json_string );
					$primeiro_item = false;
				} else {
					fwrite( $handle, ',' . PHP_EOL . $json_string );
				}
			}
		}
		fwrite( $handle, PHP_EOL . ']' );
		fclose( $handle );
	}
	/**
	 * Gera o feed XML RSS 2.0 compatível com Google Merchant/Base (feed_catalog.xml).
	 *
	 * @param bool $forcar Ignora janelas de horário e tempo de cache do arquivo quando true.
	 * @return void
	 */
	public static function generate_feed( $forcar = false ) {
		$hora_atual = ( int ) wp_date( 'H' );
		if ( ( $forcar === false ) && $hora_atual !== 8 && $hora_atual !== 20 ) {
			return;
		}
		$file_path = ABSPATH . 'feed_catalog.xml';
		if ( ( $forcar === false ) && ( ! self::arquivo_necessita_atualizacao( $file_path ) ) ) {
			return;
		}
		$resultados = self::obter_dados_imoveis_ativos( );
		$handle = fopen( $file_path, 'w' );
		if ( ! $handle ) {
			error_log( 'Não foi possível abrir feed_catalog.xml para gravação.' );
			return;
		}
		$base_url = trailingslashit( home_url( ) ) . 'imoveis/';
		fwrite( $handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL );
		fwrite( $handle, '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL );
		fwrite( $handle, '<channel>' . PHP_EOL );
		if ( ! empty( $resultados ) ) {
			foreach ( $resultados as $imovel ) {
				$loc = esc_url( $base_url . $imovel->post_name . '/' );
				$title = htmlspecialchars( $imovel->post_title, ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$referencia = htmlspecialchars( $imovel->referencia ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$tipo_imovel = htmlspecialchars( mb_convert_case( $imovel->tipoImovelNome ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$cidade = htmlspecialchars( mb_convert_case( $imovel->cidade ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$bairro = htmlspecialchars( mb_convert_case( $imovel->bairro ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$valor_feed = '0';
				if ( ( 1 == intval( $imovel->ativarVenda ) ) && ! empty( $imovel->vendaValor ) ) {
					$valor_feed = $imovel->vendaValor;
				} elseif ( ( 1 == intval( $imovel->ativarLancamento ) ) && ! empty( $imovel->lancamentoValor ) ) {
					$valor_feed = $imovel->lancamentoValor;
				} elseif ( ( 1 == intval( $imovel->ativarLocacao ) ) && ! empty( $imovel->locacaoValor ) ) {
					$valor_feed = $imovel->locacaoValor;
				}
				$preco = htmlspecialchars( $valor_feed, ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$image_link = '';
				if ( ! empty( $imovel->thumbnail_id ) ) {
					$img_url = wp_get_attachment_image_url( $imovel->thumbnail_id, 'full' );
					if ( $img_url ) {
						$image_link = esc_url( $img_url );
					}
				}
				$item = "\t<item>\n";
				$item .= "\t\t<g:id>{$referencia}</g:id>\n";
				$item .= "\t\t<g:title>{$title}</g:title>\n";
				$item .= "\t\t<g:link>{$loc}</g:link>\n";
				if ( ! empty( $image_link ) ) {
					$item .= "\t\t<g:image_link>{$image_link}</g:image_link>\n";
				}
				$item .= "\t\t<g:price>{$preco} BRL</g:price>\n";
				$item .= "\t\t<g:product_type>{$tipo_imovel}</g:product_type>\n";
				if ( ! empty( $cidade ) ) {
					$item .= "\t\t<g:city>{$cidade}</g:city>\n";
				}
				if ( ! empty( $bairro ) ) {
					$item .= "\t\t<g:neighborhood>{$bairro}</g:neighborhood>\n";
				}
				$item .= "\t\t<g:availability>in stock</g:availability>\n";
				$item .= "\t</item>\n";
				fwrite( $handle, $item );
			}
		}
		fwrite( $handle, '</channel>' . PHP_EOL );
		fwrite( $handle, '</rss>' );
		fclose( $handle );
	}
	/**
	 * Remove todos os transientes do plugin ('_transient_pnd*') do banco de dados e esvazia o cache de objetos do WordPress.
	 *
	 * @return void
	 */
	public static function purgar_transientes_pnd( ) {
		global $wpdb;
		$prefixo = 'pnd';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
					OR option_name LIKE %s",
				'_transient_' . $wpdb->esc_like( $prefixo ) . '%',
				'_transient_timeout_' . $wpdb->esc_like( $prefixo ) . '%'
			)
		);
		wp_cache_flush( );
	}
}