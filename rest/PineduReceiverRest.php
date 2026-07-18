<?php
require_once plugin_dir_path( __FILE__ ) . '../admin/classes/PineduRequest.php';
require_once plugin_dir_path( __FILE__ ) . '../admin/classes/class-pinedu-imovel-importar-basicos.php';
require_once plugin_dir_path( __FILE__ ) . '../admin/classes/class-pinedu-imovel-importar-imoveis.php';
class PineduReceiverRest extends PineduRequest {
    private const DEBUG = false;
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
	public static function instala_rest_end_point( ) {
		register_rest_route( 'pinedu-imovel/v1', 'inicializar', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'inicializar_atualizacao' ),
			'permission_callback' => array( __CLASS__, 'verify_credentials' ),
		 ) );
		register_rest_route( 'pinedu-imovel/v1', 'encerrar', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'encerrar_atualizacao' ),
			'permission_callback' => array( __CLASS__, 'verify_credentials' ),
		 ) );
		register_rest_route( 'pinedu-imovel/v1', 'update_basicos', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'receber_basicos' ),
			'permission_callback' => array( __CLASS__, 'verify_credentials' ),
		 ) );
		register_rest_route( 'pinedu-imovel/v1', 'update_imoveis', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'receber_imoveis' ),
			'permission_callback' => array( __CLASS__, 'verify_credentials' ),
		 ) );
		// Rota de Cliques
		register_rest_route( 'pinedu-imovel/v1', 'clicks', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'enviar_clicks' ),
			'permission_callback' => array( __CLASS__, 'verify_credentials' ),
		 ) );
	}
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
		$clicks_formatados = [];
		$meta_ids_para_excluir = [];
		if ( ! empty( $resultados ) ) {
			foreach ( $resultados as $linha ) {
				$dados = maybe_unserialize( $linha->evento_dados );
				if ( is_array( $dados ) && isset( $dados['cookie'] ) && isset( $dados['data'] ) ) {
					$clicks_formatados[] = [
						'referencia' => $linha->referencia,
						'data'	   => $dados['data'],
						'cookie'	 => $dados['cookie'],
						'clicks'	 => 1 // É um registro unitário na fila de eventos
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
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ( $ids_imploded )" );
		}
		$response = [
			'success' => count( $clicks_formatados ) > 0,
			'clicks'  => $clicks_formatados
		];
		return rest_ensure_response( $response );
	}
	public static function inicializar_atualizacao( $request ) {
		$options = get_option( 'pinedu_imovel_options', [] );
		if ( $options['importacao_andamento'] === true ) {
			wp_send_json_error( [
				'message' => 'Importação em andamento por outro processo. Tente novamente mais tarde!',
			] );
			wp_die( );
		}
		if ( ! isset( $options['ultima_atualizacao'] ) ) {
			$options['ultima_atualizacao'] = DateTime::createFromFormat(
				'd/m/Y H:i:sT',
				'01/01/1980 00:00:00-300'
			 );
		}
		$options['importacao_andamento'] = true;
		$options['inicio_importacao'] = new DateTime( );
		update_option( 'pinedu_imovel_options', $options );
		wp_send_json( [
			'importacao_andamento' => $options['importacao_andamento'],
			'inicio_importacao' => formataData_iso8601( $options['inicio_importacao'] ),
			'ultima_atualizacao' => formataData_iso8601( $options[ 'ultima_atualizacao' ] )
		] );
	}
	public static function encerrar_atualizacao( $request ) {
		$json_string = $request->get_body( );
		$data = json_decode( $json_string, true );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'encerrar_atualizacao: ' . print_r( $data, true ) );
		}
		$token = isset( $data['token'] ) ? sanitize_text_field( $data['token'] ) : '';
		$imoveis_importados = isset( $data['imoveis_importados'] ) ? sanitize_text_field( $data['imoveis_importados'] ) : 0;
		$options = get_option( 'pinedu_imovel_options', [] );
		$options['ultima_atualizacao'] = new \DateTime( ); // Padronizado \DateTime
		if ( isset( $options['inicio_importacao'] ) && $options['inicio_importacao'] instanceof \DateTime ) {
			$options['tempo_utilizado'] = $options['inicio_importacao']->diff( $options['ultima_atualizacao'] );
		}
		$options['imoveis_importados'] = $imoveis_importados;
		$options['token'] = $token;
		$options['importacao_andamento'] = false;
		update_option( 'pinedu_imovel_options', $options );
		$resposta = [
			'status' => 'sucesso',
			'dataAtualizacao' => formataData_iso8601( $options['ultima_atualizacao'] ),
			'importacao_andamento' => false
		];
		// Se houver comando, executa de forma SÍNCRONA antes de responder
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
	 */
	private static function arquivo_necessita_atualizacao( $file_path ) {
		// Se não existe, precisa rodar agora
		if ( ! file_exists( $file_path ) ) {
			return true;
		}
		$idade_segundos = time( ) - filemtime( $file_path );
		$limite_segundos = ( 3 * 3600 ) + ( 50 * 60 ); // 3 horas e 50 min ( 13800 segundos )
		return $idade_segundos > $limite_segundos;
	}
	public static function receber_basicos( $request ) {
		$json_string = $request->get_body( );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'JSON recebido: ' . $json_string );
		}
		$data = json_decode( $json_string, true );
		$importa_basicos = new Pinedu_Imovel_Importar_Basicos( );
		$result = $importa_basicos->importar_callback( $data );
		if ( $result === true ) {
			$data = [
				'success' => true
				, 'dataAtualizacao' => formataData_iso8601( new \DateTime( 'now', new \DateTimeZone( 'America/Sao_Paulo' ) ) )
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
		$json_string = $request->get_body( );
		$data = json_decode( $json_string, true );
		$importa_imoveis = new Pinedu_Imovel_Importar_Imoveis( );
		$result = $importa_imoveis->importar_callback( $data );
		return $result;
	}
    public static function verify_credentials( $request ) {
        // 1. Captura a Rota/Endpoint e o Método (A URL que você quer!)
        $rota = $request->get_route();
        $metodo = $request->get_method();

        if ( self::DEBUG && is_development_mode( ) ) {
            // 2. Imprime um cabeçalho no Log para você achar fácil
            error_log( '=========================================' );
            error_log( 'NOVA REQUISIÇÃO NA URL: ' . $metodo . ' ' . $rota );
            error_log( '=========================================' );
        }
        $auth_header = $request->get_header( 'Authorization' );
        $username    = $request->get_header( 'Username' );
        $password    = $request->get_header( 'Password' );

        if ( self::DEBUG && is_development_mode( ) ) {
            // Imprime as credenciais logo abaixo da URL
            error_log( 'Username: ' . $username . ' | Password: ' . $password . ' | Token: ' . $auth_header );
        }
        // Se não recebeu token/header: false
        if ( empty( $auth_header ) ) {
            if ( self::DEBUG && is_development_mode( ) ) {
                error_log( 'ERRO: Authorization header missing na URL ' . $rota );
            }
           return false;
        }

        // Verifica se é um token Bearer (Regex corrigido sem os espaços internos)
        if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
           $bearer_token = trim( $matches[1] );

           if ( self::DEBUG && is_development_mode( ) ) {
              error_log( 'Bearer Token recebido com sucesso: ' . $bearer_token );
           }

           // Passa o trio para validação
           return self::validate_bearer_token( $bearer_token, $username, $password );
        }

        if ( self::DEBUG && is_development_mode( ) ) {
            // Se falhou no regex do Bearer
            error_log( 'ERRO: Invalid Authorization format na URL: ' . $rota );
            error_log( 'Formato que chegou e foi rejeitado: ' . $auth_header );
        }
        return false;
    }
	private static function validate_bearer_token( $token, $req_username, $req_password ): bool {
		$options = get_option( 'pinedu_imovel_options', [] );
		//error_log('Options: ' . print_r( $options, true ) );
		// 1. Tenta validar o token existente primeiro ( caminho feliz e mais rápido )
		if ( isset( $options['token'] ) && $options['token'] === $token ) {
			return true;
		}
		// 2. Se o token não existir ou for diferente, verifica as credenciais
		// Estamos assumindo que username e password válidos estão armazenados em 'pinedu_imovel_options'
		$saved_username = $options['token_username'] ?? '';
		$saved_password = $options['token_password'] ?? '';
		if ( !empty( $req_username ) && !empty( $req_password ) &&
			 $req_username === $saved_username && $req_password === $saved_password ) {
			// 3. Credenciais conferem! Atualiza o option com o novo token
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
	// Transformado em método estático e público
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
	public static function optimize_tables( $forcar = false ) {
		$dia_semana = ( int ) wp_date( 'w' ); // 0 = Domingo
		$hora_atual = ( int ) wp_date( 'H' );
		// Somente aos domingos, e entre as 06:00:00 até 06:59:59
		if ( ( $forcar == false ) && ( $dia_semana !== 0 ) || ( $hora_atual !== 6 ) ) {
			return;
		}
		$options = get_option( 'pinedu_imovel_options', [] );
		$ultima_otimizacao = isset( $options['ultima_otimizacao'] ) ? ( int ) $options['ultima_otimizacao'] : 0;
		$idade_segundos = time( ) - $ultima_otimizacao;
		// Se a última otimização ocorreu há menos de 2 horas ( 7200 segundos ),
		// significa que já rodou hoje dentro desta janela das 6h. Sai fora!
		if ( ( $forcar === false ) && ( $idade_segundos < 7200 ) ) {
			if ( self::DEBUG && is_development_mode( ) ) {
				error_log( 'OPTIMIZE TABLE ignorado: Já foi executado recentemente nesta janela.' );
			}
			return;
		}
		global $wpdb;
		// Senta o aço: Busca TODAS as tabelas com o prefixo do seu WordPress
		$tabelas = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
		if ( ! empty( $tabelas ) ) {
			// Junta o array de tabelas em uma string separada por vírgulas
			$tabelas_string = implode( ', ', $tabelas );
			// Executa a otimização em massa num comando só
			$wpdb->query( "OPTIMIZE TABLE {$tabelas_string}" );
		}
		// Registra o momento exato em que a otimização acabou de ocorrer
		$options['ultima_otimizacao'] = time( );
		update_option( 'pinedu_imovel_options', $options );
		if ( self::DEBUG && is_development_mode( ) ) {
			error_log( 'OPTIMIZE TABLE executado com sucesso para TODAS as tabelas no domingo às ' . wp_date( 'H:i:s' ) );
		}
	}
	/**
	 * Consulta centralizada e otimizada ( apenas 1 hit no banco )
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
	public static function generate_site_map( $forcar = false ) {
		$hora_atual = ( int ) wp_date( 'H' );
		// 00:00 até 00:59:59 e 12:00 até 12:59:59
		if ( ( $forcar === false ) && ( $hora_atual !== 0 && $hora_atual !== 12 ) ) {
			return;
		}
		$file_path = ABSPATH . 'sitemap_imoveis.xml';
		// Verifica se o arquivo é mais velho que 3h50m
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
				$node  = "\t<url>\n";
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
	public static function generate_json_ld( $forcar = false ) {
		$hora_atual = ( int ) wp_date( 'H' );
		// 04:00 até 04:59:59 e 16:00 até 16:59:59
		if ( ( $forcar === false ) && ( $hora_atual !== 4 && $hora_atual !== 16 ) ) {
			return;
		}
		$file_path = ABSPATH . 'catalog_data.json';
		// Verifica se o arquivo é mais velho que 3h50m
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
				$offers = [];
				// Formatando as strings para Title Case
				$tipo_imovel_formatado = mb_convert_case( $imovel->tipoImovelNome ?? '', MB_CASE_TITLE, 'UTF-8' );
				$cidade_formatada	  = mb_convert_case( $imovel->cidade ?? '', MB_CASE_TITLE, 'UTF-8' );
				$bairro_formatado	  = mb_convert_case( $imovel->bairro ?? '', MB_CASE_TITLE, 'UTF-8' );
				if ( ( 1 == intval( $imovel->ativarVenda ) ) && ! empty( $imovel->vendaValor ) ) {
					$offers[] = [
						'@type'			=> 'Offer',
						'price'			=> $imovel->vendaValor,
						'priceCurrency'	=> 'BRL',
						'businessFunction' => 'http://purl.org/goodrelations/v1#Sell'
					];
				}
				if ( ( 1 == intval( $imovel->ativarLancamento ) ) && ! empty( $imovel->lancamentoValor ) ) {
					$offers[] = [
						'@type'			=> 'Offer',
						'price'			=> $imovel->lancamentoValor,
						'priceCurrency'	=> 'BRL',
						'businessFunction' => 'http://purl.org/goodrelations/v1#Sell'
					];
				}
				if ( ( 1 == intval( $imovel->ativarLocacao ) ) && ! empty( $imovel->locacaoValor ) ) {
					$offers[] = [
						'@type'			=> 'Offer',
						'price'			=> $imovel->locacaoValor,
						'priceCurrency'	=> 'BRL',
						'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut'
					];
				}
				$item = [
					'@context' => 'https://schema.org',
					'@type'	=> 'Accommodation',
					'name'	 => $imovel->post_title,
					'url'	  => esc_url( $base_url . $imovel->post_name . '/' ),
					'sku'	  => $imovel->referencia ?? '',
					'category' => $tipo_imovel_formatado
				];
				// Construindo o endereço se a cidade ou bairro existirem
				if ( ! empty( $cidade_formatada ) || ! empty( $bairro_formatado ) ) {
					$item['address'] = [
						'@type' => 'PostalAddress'
					];
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
	public static function generate_feed( $forcar = false ) {
		$hora_atual = ( int ) wp_date( 'H' );
		// 08:00 até 08:59:59 e 20:00 até 20:59:59
		if ( ( $forcar === false ) && $hora_atual !== 8 && $hora_atual !== 20 ) {
			return;
		}
		$file_path = ABSPATH . 'feed_catalog.xml';
		// Verifica se o arquivo é mais velho que 3h50m
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
				// Formatando as strings para Title Case e escapando para XML
				$tipo_imovel = htmlspecialchars( mb_convert_case( $imovel->tipoImovelNome ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$cidade	  = htmlspecialchars( mb_convert_case( $imovel->cidade ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
				$bairro	  = htmlspecialchars( mb_convert_case( $imovel->bairro ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
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
				$item  = "\t<item>\n";
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
    public static function purgar_transientes_pnd() {
        global $wpdb;
        // O prefixo do nome do transiente é 'pnd'.
        // No banco, a chave do transiente é '_transient_pnd...'
        // O timeout é '_transient_timeout_pnd...'
        $prefixo = 'pnd';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                    OR option_name LIKE %s",
                '_transient_' . $wpdb->esc_like($prefixo) . '%',
                '_transient_timeout_' . $wpdb->esc_like($prefixo) . '%'
            )
        );
        wp_cache_flush();
    }
}