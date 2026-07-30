<?php
/**
 * Class PrettyUrl
 * 
 * Gerencia o roteamento amigavel, NLP para buscas imobiliarias e injeta tags de SEO.
 * Utiliza o padrão de projeto Strategy (Closures) para resolucao de escopo de SEO.
 */
class PrettyUrl {
	const CACHE_URL_AMIGAVEL = 'CACHE_PRETTY_URL';
	const INDEX_CACHE = 'index.php?post_type=imovel&tipo_pesquisa_submit=imovel';
	private $delete_cache;
	/**
	 * Construtor da classe.
	 * 
	 * @param bool $delete_cache Flag para forcar exclusao do cache ( default: false ).
	 * @return void
	 */
	public function __construct( $delete_cache = false ) {
		$this->delete_cache = $delete_cache;
		add_action( 'template_redirect' , [$this , 'redirecionar_acentos'] , 1 );
		add_filter( 'query_vars' , [$this , 'liberar_query_vars_consulta'] );
		add_action( 'wp_head' , [$this , 'inject_seo_tags'] , 1 );
	}
	/**
	 * Mapeia e injeta tags HTML para SEO no cabecalho ( head ).
	 * Aplica Design Pattern Strategy com Closures para derivar as strings corretas.
	 * 
	 * @return void
	 */
	public function inject_seo_tags( ) {
		$referencia = get_query_var( 'referencia' );
		$tipo_pesquisa = get_query_var( 'tipo_pesquisa_submit' );
		$seo_strategies = [
			'referencia' => function( ) use ( $referencia ) {
				$ref_safe = esc_html( $referencia );
				return [
					'title' => "Imóvel Referência {$ref_safe} | Haddad Imóveis"
					, 'description' => "Veja os detalhes, fotos e preço do imóvel referência {$ref_safe} na Haddad Imóveis."
				];
			}
			, 'imovel' => function( ) {
				$contrato_get = isset( $_GET['contrato'] ) ? sanitize_text_field( wp_unslash( $_GET['contrato'] ) ) : '';
				$tipo_imovel_get = isset( $_GET['tipo-imovel'] ) ? sanitize_text_field( wp_unslash( $_GET['tipo-imovel'] ) ) : '';
				$cidade_get = isset( $_GET['cidade'] ) ? sanitize_text_field( wp_unslash( $_GET['cidade'] ) ) : '';
				$regiao_get = isset( $_GET['regiao'] ) ? sanitize_text_field( wp_unslash( $_GET['regiao'] ) ) : '';
				$search_string = trim( implode( ' ' , [
					$contrato_get == '1' ? 'venda' : ( ( $contrato_get == '2' ) ? 'locacao' : '' )
					, $tipo_imovel_get
					, $cidade_get
					, $regiao_get
				] ) );
				$mapa_contrato = self::get_mapa_contrato( );
				$mapa_tipo = self::get_mapa_tipo_imovel( );
				$contrato_canonico = '';
				$tipo_canonico = '';
				foreach ( $mapa_contrato as $canonical => $sinonimos ) {
					if ( isset( $sinonimos[ strtolower( $contrato_get == '1' ? 'venda' : 'locacao' ) ] ) || strpos( $search_string , $canonical ) !== false ) {
						$contrato_canonico = $canonical;
						break;
					}
				}
				foreach ( $mapa_tipo as $canonical => $sinonimos ) {
					if ( isset( $sinonimos[ strtolower( $tipo_imovel_get ) ] ) || strpos( $search_string , str_replace( '-' , ' ' , $canonical ) ) !== false ) {
						$tipo_canonico = str_replace( '-' , ' ' , $canonical );
						break;
					}
				}
				$partes_title = [];
				$prefixo_acao = '';
				if ( $contrato_canonico === 'venda' ) {
					$prefixo_acao = 'Comprar';
				} elseif ( $contrato_canonico === 'locacao' ) {
					$prefixo_acao = 'Alugar';
				}
				if ( ! empty( $prefixo_acao ) ) {
					$partes_title[] = $prefixo_acao;
				}
				if ( ! empty( $tipo_canonico ) ) {
					$partes_title[] = mb_convert_case( $tipo_canonico , MB_CASE_TITLE , 'UTF-8' );
				} else {
					$partes_title[] = 'Imóveis';
				}
				$cidade_nome = ( $cidade_get === '0001' ) ? 'Birigui' : ( ( $cidade_get === '0002' ) ? 'Araçatuba' : ( ( $cidade_get === '0005' ) ? 'Buritama' : '' ) );
				$regiao_nome = ( $regiao_get === '00010001' ) ? 'Centro' : '';
				if ( ! empty( $cidade_nome ) ) {
					$localidade = 'em ' . $cidade_nome;
					if ( ! empty( $regiao_nome ) ) {
						$localidade .= ' - ' . $regiao_nome;
					}
					$partes_title[] = $localidade;
				}
				$title_seo = ! empty( $partes_title ) ? implode( ' ' , $partes_title ) . ' | Haddad Imóveis' : 'Busca de Imóveis | Haddad Imóveis';
				$acao_desc = ( $contrato_canonico === 'venda' ) ? 'à venda' : 'para locação';
				$desc_tipo = ! empty( $tipo_canonico ) ? mb_convert_case( $tipo_canonico , MB_CASE_LOWER , 'UTF-8' ) : 'imóveis';
				$desc_local = ! empty( $cidade_nome ) ? "em {$cidade_nome}" : "na região";
				$description_seo = sprintf( 'Confira as melhores opções de %s %s %s. Encontre o imóvel ideal com a Haddad Imóveis.' , $desc_tipo , $acao_desc , $desc_local );
				return [
					'title' => $title_seo
					, 'description' => $description_seo
				];
			}
			, 'default' => function( ) {
				return [
					'title' => 'Comprar ou Alugar Imóveis | Haddad Imóveis'
					, 'description' => 'Confira as melhores opções de imóveis disponíveis na Haddad Imóveis em Birigui e região.'
				];
			}
		];
		$estrategia_ativa = 'default';
		if ( ! empty( $referencia ) ) {
			$estrategia_ativa = 'referencia';
		} elseif ( $tipo_pesquisa === 'imovel' ) {
			$estrategia_ativa = 'imovel';
		}
		$seo_data = $seo_strategies[ $estrategia_ativa ]( );
		echo '<title>' . esc_html( $seo_data[ 'title' ] ) . '</title>' . "\n";
		echo '<meta name="description" content="' . esc_attr( $seo_data[ 'description' ] ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $seo_data[ 'title' ] ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $seo_data[ 'description' ] ) . '" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $seo_data[ 'title' ] ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $seo_data[ 'description' ] ) . '" />' . "\n";
	}
	/**
	 * Libera variaveis customizadas para interceptacao de query na URL.
	 * 
	 * @param array $query_vars Array nativo do WordPress.
	 * @return array Variaveis modificadas.
	 */
	public function liberar_query_vars_consulta( $query_vars ) {
		$query_vars[] = 'referencia';
		$query_vars[] = 'tipo_pesquisa_submit';
		return $query_vars;
	}
	/**
	 * Define dicionario canonico e sinonimos para tipos de imoveis.
	 * 
	 * @return array Estrutura de mapeamento invertido ( array_flip ).
	 */
	public static function get_mapa_tipo_imovel( ) {
		return [
			'apartamento' => array_flip( [
				'apartamento'
				, 'flat'
				, 'loft'
				, 'duplex'
				, 'triplex'
				, 'apartamento flat'
				, 'apartamento loft'
				, 'apartamento duplex'
				, 'apartamento triplex'
				, 'cobertura'
				, 'apartamento cobertura'
				, 'kitnet'
				, 'kit'
				, 'studio'
				, 'penthouse'
				, 'garden'
			] )
			, 'imovel-comercial' => array_flip( [
				'imovel comercial'
				, 'loja'
				, 'sobreloja'
				, 'sobre loja'
				, 'salão'
				, 'sala'
				, 'barracão'
				, 'galpão'
				, 'consultório'
				, 'escritório'
				, 'bar'
				, 'bodega'
				, 'restaurante'
				, 'lanchonete'
				, 'box'
				, 'ponto comercial'
				, 'depósito'
				, 'pavilhão'
				, 'prédio comercial'
				, 'centro comercial'
				, 'loja térrea'
			] )
			, 'casa' => array_flip( [
				'casa'
				, 'casa térra'
				, 'sobrado'
				, 'assobradado'
				, 'assobradada'
				, 'residência'
				, 'casa em condomínio'
				, 'mansão'
				, 'edícula'
				, 'casa de vila'
				, 'bangalô'
				, 'chalé'
				, 'casa geminada'
			] )
			, 'rural' => array_flip( [
				'rural'
				, 'chácara'
				, 'sítio'
				, 'fazenda'
				, 'propriedade'
				, 'granja'
				, 'estância'
				, 'pomar'
				, 'horta'
				, 'haras'
				, 'pousada'
				, 'área rural'
			] )
			, 'lazer' => array_flip( [
				'lazer'
				, 'rancho'
				, 'balneário'
				, 'clubinho'
				, 'clube'
				, 'chácara'
				, 'quadra'
				, 'casa de campo'
				, 'centro de eventos'
				, 'área de lazer'
				, 'salão de festas'
				, 'retiro'
			] )
			, 'terreno-ou-area' => array_flip( [
				'terreno ou área'
				, 'área'
				, 'área industrial'
				, 'área incorporação'
				, 'terreno'
				, 'lote'
				, 'gleba'
				, 'eira'
				, 'loteamento'
				, 'área comercial'
				, 'recinto'
				, 'recinto de exposiçao'
				, 'exposiçao'
				, 'área para rodeio'
				, 'estacionamento'
			] )
		];
	}
	/**
	 * Define dicionario canonico e sinonimos para modalidade de contrato.
	 * 
	 * @return array Estrutura de mapeamento invertido.
	 */
	public static function get_mapa_contrato( ) {
		return [
			'venda' => array_flip( [
				'venda'
				, 'vendas'
				, 'compra'
				, 'aquisição'
				, 'aquisicao'
				, 'investimento'
				, 'negociação'
				, 'comercialização'
				, 'comprar'
				, 'vender'
				, 'adquirir'
				, 'adiquirir'
				, 'investir'
				, 'comprando'
				, 'vendendo'
				, 'vende'
				, 'compro'
				, 'adquire'
				, 'adiquire'
				, 'vende se'
				, 'compra se'
			] )
			, 'locacao' => array_flip( [
				'locação'
				, 'locacao'
				, 'locações'
				, 'locacoes'
				, 'aluguel'
				, 'aluguéis'
				, 'alugueis'
				, 'aluguer'
				, 'arrendamento'
				, 'alugar'
				, 'locar'
				, 'arrendar'
				, 'alugando'
				, 'locando'
				, 'aluga'
				, 'alugo'
				, 'loca'
				, 'aluga se'
				, 'loca se'
			] )
		];
	}
	/**
	 * Define palavras-ruido ( stop words ) a serem extirpadas pela NLP.
	 * 
	 * @return array Colecao de preposicoes e prefixos urbanos.
	 */
	public static function get_ruidos( ) {
		return [
			'em'
			, 'de'
			, 'do'
			, 'da'
			, 'dos'
			, 'das'
			, 'no'
			, 'na'
			, 'nos'
			, 'nas'
			, 'com'
			, 'para'
			, 'vila'
			, 'vl'
			, 'bairro'
			, 'jardim'
			, 'jd'
			, 'residencial'
			, 'res'
			, 'condominio'
			, 'cond'
			, 'parque'
			, 'pq'
			, 'conjunto'
			, 'conj'
			, 'cj'
			, 'habitacional'
			, 'hab'
			, 'loteamento'
			, 'gleba'
			, 'chacara'
			, 'chacaras'
			, 'sitio'
			, 'fazenda'
			, 'estancia'
			, 'recanto'
		];
	}
	/**
	 * Aplica faxina de caracteres em nomes brutos de bairros.
	 * 
	 * @param string $nome Nome nativo proveniente do banco de dados.
	 * @return string Nome tratado e limpo.
	 */
	public static function limpar_faxina_bairro( $nome ) {
		$nome = remove_accents( strtolower( trim( $nome ) ) );
		$nome = preg_replace( '/^[0-9]+[a-z]?\s*( ?:-\s* )?/' , '' , $nome );
		$nome = str_replace( '.' , ' ' , $nome );
		$ruidos = self::get_ruidos( );
		foreach ( $ruidos as $ruido ) {
			$nome = preg_replace( '/\b' . $ruido . '\b/u' , ' ' , $nome );
		}
		return trim( preg_replace( '/\s+/' , ' ' , $nome ) );
	}
	/**
	 * Constrói ( ou recupera via cache ) a hierarquia de Cidades e Regioes com base em Taxonomy.
	 * 
	 * @return array Estrutura multidimensional otimizada.
	 */
	public static function get_mapa_hierarquico( ) {
		global $wpdb;
		$cache_key = 'CACHE_MAPA_HIERARQUICO_V4';
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		$dados = false;
		if ( ! $em_desenvolvimento ) {
			$dados = get_transient( $cache_key );
		}
		if ( $dados !== false ) {
			return $dados;
		}
		$query = "SELECT b.name AS bairro_nome, b.slug AS bairro_slug, r.name AS regiao_nome, r.slug AS regiao_slug, c.name AS cidade_nome, c.slug AS cidade_slug FROM {$wpdb->terms} b INNER JOIN {$wpdb->term_taxonomy} tt_b ON b.term_id = tt_b.term_id AND tt_b.taxonomy = 'bairro' LEFT JOIN {$wpdb->termmeta} tm_b ON b.term_id = tm_b.term_id AND tm_b.meta_key = 'parent_id' LEFT JOIN {$wpdb->terms} r ON r.slug = tm_b.meta_value INNER JOIN {$wpdb->term_taxonomy} tt_r ON r.term_id = tt_r.term_id AND tt_r.taxonomy = 'regiao' LEFT JOIN {$wpdb->termmeta} tm_r ON r.term_id = tm_r.term_id AND tm_r.meta_key = 'parent_id' LEFT JOIN {$wpdb->terms} c ON c.slug = tm_r.meta_value INNER JOIN {$wpdb->term_taxonomy} tt_c ON c.term_id = tt_c.term_id AND tt_c.taxonomy = 'cidade' WHERE c.slug IS NOT NULL";
		$resultados = $wpdb->get_results( $query );
		$cidades_nlp = [];
		$regioes_nlp = [];
		$bairros_nlp = [];
		$regioes_rules = [];
		foreach ( $resultados as $row ) {
			$cidade_slug_url = sanitize_title( $row->cidade_nome );
			$regiao_sem_prefixo = preg_replace( '/^[0-9]+[a-z]?\s*( ?:-\s* )?/i' , '' , $row->regiao_nome );
			$regiao_slug_url = sanitize_title( $regiao_sem_prefixo );
			$bairro_sem_prefixo = preg_replace( '/^[0-9]+[a-z]?\s*( ?:-\s* )?/i' , '' , $row->bairro_nome );
			$bairro_slug_url = sanitize_title( $bairro_sem_prefixo );
			$cidade_nome_limpo = remove_accents( strtolower( trim( $row->cidade_nome ) ) );
			$cidades_nlp[ $cidade_nome_limpo ] = [
				'slug' => $row->cidade_slug
				, 'nome' => $row->cidade_nome
				, 'slug_url' => $cidade_slug_url
			];
			$regiao_nome_limpo = self::limpar_faxina_bairro( $row->regiao_nome );
			if ( ! isset( $regioes_nlp[ $regiao_nome_limpo ] ) ) {
				$regioes_nlp[ $regiao_nome_limpo ] = [];
			}
			$regioes_nlp[ $regiao_nome_limpo ][ $row->cidade_slug ] = [
				'regiao_slug' => $row->regiao_slug
				, 'regiao_nome' => $row->regiao_nome
				, 'regiao_slug_url' => $regiao_slug_url
				, 'cidade_slug' => $row->cidade_slug
				, 'cidade_nome' => $row->cidade_nome
				, 'cidade_slug_url' => $cidade_slug_url
			];
			$bairro_nome_limpo = self::limpar_faxina_bairro( $row->bairro_nome );
			if ( ! isset( $bairros_nlp[ $bairro_nome_limpo ] ) ) {
				$bairros_nlp[ $bairro_nome_limpo ] = [];
			}
			$bairros_nlp[ $bairro_nome_limpo ][ $row->cidade_slug ] = [
				'bairro_slug' => $row->bairro_slug
				, 'bairro_nome' => $row->bairro_nome
				, 'bairro_slug_url' => $bairro_slug_url
				, 'regiao_slug' => $row->regiao_slug
				, 'regiao_nome' => $row->regiao_nome
				, 'regiao_slug_url' => $regiao_slug_url
				, 'cidade_slug' => $row->cidade_slug
				, 'cidade_nome' => $row->cidade_nome
				, 'cidade_slug_url' => $cidade_slug_url
			];
			$path_regiao = $cidade_slug_url . '/' . $regiao_slug_url;
			$regioes_rules[ $path_regiao ] = [
				'cidade' => $row->cidade_slug
				, 'regiao' => $row->regiao_slug
			];
		}
		$dados = [
			'cidades_nlp' => $cidades_nlp
			, 'regioes_nlp' => $regioes_nlp
			, 'bairros_nlp' => $bairros_nlp
			, 'regioes_rules' => $regioes_rules
		];
		if ( ! $em_desenvolvimento ) {
			set_transient( $cache_key , $dados , 10 * MINUTE_IN_SECONDS );
		}
		return $dados;
	}
	/**
	 * Intercepta requests iniciais lidando com 404 e analises semanticas textuais.
	 * 
	 * @return void Imprime redirecionamento 301 se string NLP for resolvida.
	 */
	public function redirecionar_acentos( ) {
		$uri = $_SERVER[ 'REQUEST_URI' ];
		$parsed = parse_url( $uri );
		$path = isset( $parsed[ 'path' ] ) ? ltrim( $parsed[ 'path' ] , '/' ) : '';
		$path_digitado = urldecode( $path );
		$path_digitado_clean = trim( rtrim( $path_digitado , '/' ) );
		if ( preg_match( '/(?:ref|referencia)[\-\/]([0-9]+)/i' , $path_digitado_clean , $matches ) ) {
			$referencia_alvo = $matches[ 1 ];
			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'referencia' AND meta_value = %s LIMIT 1" , $referencia_alvo ) );
			if ( $post_id ) {
				$novo_link = get_permalink( $post_id );
				$path_atual = rtrim( parse_url( $uri , PHP_URL_PATH ) , '/' );
				$path_alvo = rtrim( parse_url( $novo_link , PHP_URL_PATH ) , '/' );
				if ( $path_alvo !== $path_atual ) {
					wp_redirect( $novo_link , 301 );
					exit;
				}
			} else {
				if ( is_404( ) ) {
					return;
				}
			}
		}
		if ( is_404( ) ) {
			remove_action( 'template_redirect' , 'redirect_canonical' );
			$clean_path = remove_accents( strtolower( $path_digitado ) );
			$search_string = preg_replace( '/[\-\_\/\#\t\.]+/u' , ' ' , $clean_path );
			$ruidos = self::get_ruidos( );
			foreach ( $ruidos as $ruido ) {
				$search_string = preg_replace( '/\b' . $ruido . '\b/u' , ' ' , $search_string );
			}
			$search_string = preg_replace( '/\s+/' , ' ' , trim( $search_string ) );
			$string_inicial_debug = $search_string;
			$hierarquia = self::get_mapa_hierarquico( );
			$options = get_option( 'pinedu_imovel_options' , [] );
			$cidade_padrao_slug = $options[ 'cidade' ] ?? '0001';
			$contrato_encontrado = $this->extrair_e_limpar_termo( $search_string , self::get_mapa_contrato( ) );
			$tipo_encontrado = $this->extrair_e_limpar_termo( $search_string , self::get_mapa_tipo_imovel( ) );
			$cidade_explicita_slug = null;
			$cidade_explicita_nome = null;
			$cidade_match_pattern = null;
			foreach ( $hierarquia[ 'cidades_nlp' ] as $sinonimo => $data ) {
				$pattern = '/\b' . preg_quote( $sinonimo , '/' ) . '\b/u';
				if ( preg_match( $pattern , $search_string ) ) {
					$cidade_explicita_slug = $data[ 'slug' ];
					$cidade_explicita_nome = $data[ 'nome' ];
					$cidade_match_pattern = $pattern;
					break;
				}
			}
			$cidade_alvo = $cidade_explicita_slug ? $cidade_explicita_slug : $cidade_padrao_slug;
			$bairro_encontrado_nome = null;
			$regiao_encontrada_slug_url = null;
			$cidade_final_slug_url = null;
			$cidade_final_nome = null;
			$chaves_bairros = array_keys( $hierarquia[ 'bairros_nlp' ] );
			usort( $chaves_bairros , function( $a , $b ) { return strlen( $b ) - strlen( $a ); } );
			foreach ( $chaves_bairros as $bairro_limpo ) {
				if ( empty( $bairro_limpo ) ) continue;
				$bairro_valido = null;
				if ( isset( $hierarquia[ 'bairros_nlp' ][ $bairro_limpo ][ $cidade_alvo ] ) ) {
					$bairro_valido = $hierarquia[ 'bairros_nlp' ][ $bairro_limpo ][ $cidade_alvo ];
				} elseif ( ! $cidade_explicita_slug ) {
					$bairro_valido = reset( $hierarquia[ 'bairros_nlp' ][ $bairro_limpo ] );
				}
				if ( ! $bairro_valido ) continue;
				$pattern = '/\b' . preg_quote( $bairro_limpo , '/' ) . '\b/u';
				if ( preg_match( $pattern , $search_string ) ) {
					$search_string = preg_replace( $pattern , ' ' , $search_string );
					$bairro_encontrado_nome = $bairro_valido[ 'bairro_nome' ];
					$regiao_encontrada_slug_url = $bairro_valido[ 'regiao_slug_url' ];
					$cidade_final_slug_url = $bairro_valido[ 'cidade_slug_url' ];
					$cidade_final_nome = $bairro_valido[ 'cidade_nome' ];
					$pat_reg = '/\b' . preg_quote( self::limpar_faxina_bairro( $bairro_valido[ 'regiao_nome' ] ) , '/' ) . '\b/u';
					$search_string = preg_replace( $pat_reg , ' ' , $search_string );
					break;
				}
			}
			if ( ! $bairro_encontrado_nome ) {
				$chaves_regioes = array_keys( $hierarquia[ 'regioes_nlp' ] );
				usort( $chaves_regioes , function( $a , $b ) { return strlen( $b ) - strlen( $a ); } );
				foreach ( $chaves_regioes as $regiao_limpa ) {
					if ( empty( $regiao_limpa ) ) continue;
					$regiao_valida = null;
					if ( isset( $hierarquia[ 'regioes_nlp' ][ $regiao_limpa ][ $cidade_alvo ] ) ) {
						$regiao_valida = $hierarquia[ 'regioes_nlp' ][ $regiao_limpa ][ $cidade_alvo ];
					} elseif ( ! $cidade_explicita_slug ) {
						$regiao_valida = reset( $hierarquia[ 'regioes_nlp' ][ $regiao_limpa ] );
					}
					if ( ! $regiao_valida ) continue;
					$pattern = '/\b' . preg_quote( $regiao_limpa , '/' ) . '\b/u';
					if ( preg_match( $pattern , $search_string ) ) {
						$search_string = preg_replace( $pattern , ' ' , $search_string );
						$regiao_encontrada_slug_url = $regiao_valida[ 'regiao_slug_url' ];
						$cidade_final_slug_url = $regiao_valida[ 'cidade_slug_url' ];
						$cidade_final_nome = $regiao_valida[ 'cidade_nome' ];
						break;
					}
				}
			}
			if ( $cidade_match_pattern ) {
				$search_string = preg_replace( $cidade_match_pattern , ' ' , $search_string );
			}
			if ( ! $bairro_encontrado_nome && ! $regiao_encontrada_slug_url && $cidade_explicita_slug ) {
				$cidade_final_slug_url = sanitize_title( $cidade_explicita_nome );
				$cidade_final_nome = $cidade_explicita_nome;
			}
			$partes = [];
			if ( $contrato_encontrado ) $partes[] = $contrato_encontrado;
			if ( $tipo_encontrado ) $partes[] = $tipo_encontrado;
			if ( $cidade_final_slug_url ) $partes[] = $cidade_final_slug_url;
			if ( $regiao_encontrada_slug_url ) $partes[] = $regiao_encontrada_slug_url;
			$nova_rota = '';
			if ( $contrato_encontrado && $tipo_encontrado && $cidade_final_slug_url && ! $regiao_encontrada_slug_url ) {
				$nova_rota = $contrato_encontrado . '-' . $tipo_encontrado . '-' . $cidade_final_slug_url;
			} else {
				$nova_rota = implode( '/' , $partes );
			}
			$search_string = preg_replace( '/\s+/' , ' ' , trim( $search_string ) );
			if ( ! empty( $search_string ) ) {
				$sobras = str_replace( ' ' , '-' , $search_string );
				$nova_rota .= empty( $nova_rota ) ? $sobras : '/' . $sobras;
			}
			$nova_rota_clean = trim( $nova_rota , '/' );
			if ( isset( $_GET[ 'debug_url' ] ) ) {
				$relatorio = [
					'01_URI_DIGITADA' => $path_digitado_clean
					, '02_TEXTO_LIMPO' => $string_inicial_debug
					, '03_BAIRRO_IDENTIFICADO' => $bairro_encontrado_nome ?: 'NENHUM'
					, '04_REGIAO_CRAVADA_NA_URL' => $regiao_encontrada_slug_url ?: 'NENHUMA'
					, '05_CIDADE_CRAVADA_NA_URL' => $cidade_final_nome ?: 'NENHUMA'
					, '06_ROTA_GERADA' => $nova_rota_clean
					, '07_VAI_REDIRECIONAR?' => ( $nova_rota_clean !== $path_digitado_clean && ! empty( $nova_rota_clean ) ) ? 'SIM' : 'NÃO ( Impede o Loop Infinito )'
				];
				$css_fullscreen = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:#111; color:#0f0; padding:20px; font-size:13px; z-index:999999; margin:0; overflow:auto; box-sizing:border-box; font-family:monospace;';
				wp_die( '<pre style="' . $css_fullscreen . '">' . print_r( $relatorio , true ) . '</pre>' , 'Painel de Debug NLP' );
			}
			if ( $nova_rota_clean !== $path_digitado_clean && ! empty( $nova_rota_clean ) ) {
				$new_url = home_url( '/' . $nova_rota_clean . '/' );
				if ( ! empty( $parsed[ 'query' ] ) ) {
					$new_url .= '?' . $parsed[ 'query' ];
				}
				wp_redirect( $new_url , 301 );
				exit;
			}
		}
	}
	/**
	 * Limpa a string base via Regex para extrair termos validos.
	 * 
	 * @param string &$string_busca Endereco da string manipulada.
	 * @param array $mapa_agrupado Dicionario canonico.
	 * @return string|null Termo extraido.
	 */
	private function extrair_e_limpar_termo( &$string_busca , $mapa_agrupado ) {
		if ( empty( $mapa_agrupado ) ) return null;
		$sinonimos_achatados = [];
		foreach ( $mapa_agrupado as $canonical => $sinonimos ) {
			foreach ( array_keys( $sinonimos ) as $sinonimo ) {
				$sinonimos_achatados[ remove_accents( strtolower( $sinonimo ) ) ] = $canonical;
			}
		}
		uksort( $sinonimos_achatados , function( $a , $b ) { return strlen( $b ) - strlen( $a ); } );
		foreach ( $sinonimos_achatados as $sinonimo => $canonical ) {
			$pattern = '/\b' . preg_quote( $sinonimo , '/' ) . '\b/u';
			if ( preg_match( $pattern , $string_busca ) ) {
				$string_busca = preg_replace( $pattern , ' ' , $string_busca );
				return $canonical;
			}
		}
		return null;
	}
	/**
	 * Gera Internal Linking dinamicamente para os inputs de busca.
	 * 
	 * @param array $params Atributos HTTP.
	 * @return array Estrutura multidimensional com URLs e Labels de links SEO amigaveis.
	 */
	public static function gerar_links_from_search( $params ) {
		$links = [];
		$partes_url = [];
		$nomes = [];
		if ( ! empty( $params[ 'contrato' ] ) ) {
			$val = $params[ 'contrato' ];
			$term = get_term_by( 'slug' , $val , 'contrato' );
			if ( $val == '1' || ( $term && strtolower( $term->name ) == 'venda' ) ) {
				$c_slug = 'venda';
				$nomes[ 'contrato' ] = 'Venda';
			} elseif ( $val == '2' || ( $term && strtolower( $term->name ) == 'locacao' ) ) {
				$c_slug = 'locacao';
				$nomes[ 'contrato' ] = 'Locação';
			} elseif ( $term && ! is_wp_error( $term ) ) {
				$c_slug = sanitize_title( $term->name );
				$nomes[ 'contrato' ] = mb_convert_case( $term->name , MB_CASE_TITLE , 'UTF-8' );
			}
			if ( isset( $c_slug ) ) {
				$partes_url[] = $c_slug;
				$links[] = [
					'url' => "/" . implode( "/" , $partes_url ) . "/"
					, 'label' => $nomes[ 'contrato' ]
				];
			}
		}
		if ( ! empty( $params[ 'tipo-imovel' ] ) ) {
			$term = get_term_by( 'slug' , $params[ 'tipo-imovel' ] , 'tipo-imovel' );
			if ( $term && ! is_wp_error( $term ) ) {
				$t_slug = sanitize_title( $term->name );
				$nomes[ 'tipo' ] = mb_convert_case( $term->name , MB_CASE_TITLE , 'UTF-8' );
				$partes_url[] = $t_slug;
				$label = $nomes[ 'tipo' ];
				$links[] = [
					'url' => "/" . implode( "/" , $partes_url ) . "/"
					, 'label' => $label
				];
			}
		}
		if ( ! empty( $params[ 'cidade' ] ) ) {
			$term = get_term_by( 'slug' , $params[ 'cidade' ] , 'cidade' );
			if ( $term && ! is_wp_error( $term ) ) {
				$cid_slug = sanitize_title( $term->name );
				$nomes[ 'cidade' ] = mb_convert_case( $term->name , MB_CASE_TITLE , 'UTF-8' );
				$partes_url[] = $cid_slug;
				$label = $nomes[ 'cidade' ];
				$links[] = [
					'url' => "/" . implode( "/" , $partes_url ) . "/"
					, 'label' => $label
				];
			}
		}
		if ( ! empty( $params[ 'regiao' ] ) ) {
			$term = get_term_by( 'slug' , $params[ 'regiao' ] , 'regiao' );
			if ( $term && ! is_wp_error( $term ) ) {
				$reg_nome = self::limpar_faxina_bairro( $term->name );
				$reg_slug = sanitize_title( $reg_nome );
				$nomes[ 'regiao' ] = mb_convert_case( $reg_nome , MB_CASE_TITLE , 'UTF-8' );
				$partes_url[] = $reg_slug;
				$label = $nomes[ 'regiao' ];
				$links[] = [
					'url' => "/" . implode( "/" , $partes_url ) . "/"
					, 'label' => $label
				];
			}
		}
		return $links;
	}
	/**
	 * Mapeia arvore de breadcrumbs para entidades Post ( Imovel individual ).
	 * 
	 * @param object $post Instancia WP_Post tratada customizada.
	 * @return array Multi-nodes the URLs semanticas.
	 */
	public static function gerar_links_from_post( $post ) {
		if ( ! $post || $post->post_type !== 'imovel' ) return [];
		$cidade_slug = sanitize_title( $post->cidade );
		$regiao_slug = sanitize_title( self::limpar_faxina_bairro( $post->regiao ) );
		$tipo_slug = sanitize_title( $post->tipoImovelNome );
		$links = [];
		$finalidades = [];
		if ( ( 1 == intval( $post->ativarVenda ) ) || ( 1 == intval( $post->ativarLancamento ) ) ) $finalidades[] = 'venda';
		if ( ( 1 == intval( $post->ativarLocacao ) ) ) $finalidades[] = 'locacao';
		foreach ( $finalidades as $contrato ) {
			$lbl_base = ( $contrato == 'venda' ? "Venda" : "Locação" );
			$links[] = [
				'url' => "/{$contrato}/"
				, 'label' => $lbl_base
			];
			if ( ! empty( $tipo_slug ) ) {
				$raw_tipo = $post->tipoImovelNome ?? str_replace( '-' , ' ' , $tipo_slug );
				$nome_tipo = mb_convert_case( $raw_tipo , MB_CASE_TITLE , 'UTF-8' );
				$links[] = [
					'url' => "/{$contrato}/{$tipo_slug}/"
					, 'label' => "{$lbl_base} - {$nome_tipo}"
				];
				if ( ! empty( $cidade_slug ) ) {
					$raw_cidade = $post->cidade ?? str_replace( '-' , ' ' , $cidade_slug );
					$nome_cidade = mb_convert_case( $raw_cidade , MB_CASE_TITLE , 'UTF-8' );
					$acao = ( $contrato == 'venda' ? 'Venda' : 'Locação' );
					$links[] = [
						'url' => "/{$contrato}/{$tipo_slug}/{$cidade_slug}/"
						, 'label' => "{$nome_tipo} à {$acao} em {$nome_cidade}"
					];
					if ( ! empty( $regiao_slug ) ) {
						$raw_regiao = $post->regiao ?? str_replace( '-' , ' ' , $regiao_slug );
						$nome_regiao = mb_convert_case( $raw_regiao , MB_CASE_TITLE , 'UTF-8' );
						$links[] = [
							'url' => "/{$contrato}/{$tipo_slug}/{$cidade_slug}/{$regiao_slug}/"
							, 'label' => "{$nome_tipo} à {$acao} em {$nome_cidade} - {$nome_regiao}"
						];
					}
				}
			}
		}
		return $links;
	}
	/**
	 * Motor de injeção de Rules para Endpoints hierarquicos.
	 * 
	 * @param array $hierarquia_rules Mapa validado base.
	 * @param array $tax_contrato Mapeamento de contrato ativo.
	 * @param array $tax_tipo_imovel Mapeamento de tipo imovel ativo.
	 * @return array Nodes Regex formatados no padrao wp_rewrite.
	 */
	private function url_hierarquia_regras( $hierarquia_rules , $tax_contrato , $tax_tipo_imovel ) {
		$rules = [];
		foreach ( $hierarquia_rules as $path => $data ) {
			$base_rule = self::INDEX_CACHE . '&cidade=' . $data[ 'cidade' ] . '&regiao=' . $data[ 'regiao' ];
			$rules[] = [ 'regex' => '^' . $path . '/page/( [0-9]{1,3} )/?$' , 'rule' => $base_rule . '&paged=$matches[1]' , 'hierarchical' => 'top' ];
			$rules[] = [ 'regex' => '^' . $path . '/?$' , 'rule' => $base_rule , 'hierarchical' => 'top' ];
			foreach ( $tax_contrato as $nome_contrato => $c_data ) {
				$c_path = $nome_contrato . '/' . $path;
				$c_rule = $base_rule . '&contrato=' . $c_data[ 'slug' ];
				$rules[] = [ 'regex' => '^' . $c_path . '/page/( [0-9]{1,3} )/?$' , 'rule' => $c_rule . '&paged=$matches[1]' , 'hierarchical' => 'top' ];
				$rules[] = [ 'regex' => '^' . $c_path . '/?$' , 'rule' => $c_rule , 'hierarchical' => 'top' ];
				foreach ( $tax_tipo_imovel as $nome_tipo => $t_data ) {
					$ct_path = $nome_contrato . '/' . $nome_tipo . '/' . $path;
					$ct_rule = $c_rule . '&tipo-imovel=' . $t_data[ 'slug' ];
					$rules[] = [ 'regex' => '^' . $ct_path . '/page/( [0-9]{1,3} )/?$' , 'rule' => $ct_rule . '&paged=$matches[1]' , 'hierarchical' => 'top' ];
					$rules[] = [ 'regex' => '^' . $ct_path . '/?$' , 'rule' => $ct_rule , 'hierarchical' => 'top' ];
				}
			}
		}
		return $rules;
	}
	/**
	 * Regras restritas geradas para injetar endpoints SEO Otimizados isolados.
	 * 
	 * @param array $tax_contrato Mapeamento array multidimensional de Venda/Locacao.
	 * @param array $tax_tipo_imovel Mapeamento array multidimensional de Tipos.
	 * @param array $tax_cidade Mapeamento array multidimensional de Cidade/Area.
	 * @return array Nodes de regex com aliases isolados.
	 */
	private function url_seo_landing_pages( $tax_contrato , $tax_tipo_imovel , $tax_cidade ) {
		$rule = [];
		$chave_venda = 'venda';
		$chave_locacao = 'locacao';
		foreach ( array_keys( $tax_contrato ) as $key ) {
			if ( strpos( $key , 'vend' ) !== false || strpos( $key , 'compr' ) !== false ) $chave_venda = $key;
			if ( strpos( $key , 'loca' ) !== false || strpos( $key , 'alug' ) !== false ) $chave_locacao = $key;
		}
		$mapa_prefixos = [
			'venda' => $chave_venda
			, 'comprar' => $chave_venda
			, 'vender' => $chave_venda
			, 'aluguel' => $chave_locacao
			, 'alugar' => $chave_locacao
			, 'locar' => $chave_locacao
			, 'locação' => $chave_locacao
			, 'locacao' => $chave_locacao
			, 'arrendar' => $chave_locacao
		];
		foreach ( $tax_cidade as $slug_cidade => $data_cidade ) {
			$aliases_base = [ 'imoveis' , 'imobiliaria' ];
			foreach ( $aliases_base as $alias ) {
				$rule[] = [
					'regex' => ( '^' . $alias . '-' . $slug_cidade . '/page/( [0-9]{1,3} )/?$' )
					, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data_cidade[ 'tax' ] , $data_cidade[ 'slug' ] ) . '&' . $this->tupla( 'paged' , '$matches[1]' ) )
					, 'hierarchical' => 'top'
				];
				$rule[] = [
					'regex' => ( '^' . $alias . '-' . $slug_cidade . '/?$' )
					, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data_cidade[ 'tax' ] , $data_cidade[ 'slug' ] ) )
					, 'hierarchical' => 'top'
				];
			}
			foreach ( $tax_tipo_imovel as $slug_tipo => $data_tipo ) {
				$prefixo_pluralizado = $slug_tipo . 's?';
				$regra_base = self::INDEX_CACHE . '&' . $this->tupla( $data_tipo[ 'tax' ] , $data_tipo[ 'slug' ] ) . '&' . $this->tupla( $data_cidade[ 'tax' ] , $data_cidade[ 'slug' ] );
				$rule[] = [
					'regex' => ( '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/( [0-9]{1,3} )/?$' )
					, 'rule' => ( $regra_base . '&' . $this->tupla( 'paged' , '$matches[1]' ) )
					, 'hierarchical' => 'top'
				];
				$rule[] = [
					'regex' => ( '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$' )
					, 'rule' => ( $regra_base )
					, 'hierarchical' => 'top'
				];
				foreach ( $mapa_prefixos as $verbo => $alvo_contrato ) {
					$regra_intencao = $regra_base;
					if ( isset( $tax_contrato[ $alvo_contrato ] ) ) {
						$data_contrato = $tax_contrato[ $alvo_contrato ];
						$regra_intencao .= '&' . $this->tupla( $data_contrato[ 'tax' ] , $data_contrato[ 'slug' ] );
					}
					$rule[] = [
						'regex' => ( '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/( [0-9]{1,3} )/?$' )
						, 'rule' => ( $regra_intencao . '&' . $this->tupla( 'paged' , '$matches[1]' ) )
						, 'hierarchical' => 'top'
					];
					$rule[] = [
						'regex' => ( '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$' )
						, 'rule' => ( $regra_intencao )
						, 'hierarchical' => 'top'
					];
				}
			}
		}
		return $rule;
	}
	/**
	 * Helper padronizado para concatenar taxonomias curtas.
	 * 
	 * @param array $taxonomy_mapping Vetor referencial.
	 * @return array Set de endpoints.
	 */
	private function url_one( $taxonomy_mapping ) {
		$rule = [];
		foreach ( $taxonomy_mapping as $slug => $data ) {
			$rule[] = [
				'regex' => ( '^' . $slug . '/page/( [0-9]{1,3} )/?$' )
				, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data[ 'tax' ] , $data[ 'slug' ] ) . '&' . $this->tupla( 'paged' , '$matches[1]' ) )
				, 'hierarchical' => 'top'
			];
			$rule[] = [
				'regex' => ( '^' . $slug . '/?$' )
				, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data[ 'tax' ] , $data[ 'slug' ] ) )
				, 'hierarchical' => 'top'
			];
		}
		return $rule;
	}
	/**
	 * Helper duplo padronizado de mapeamento taxonomico relacional.
	 * 
	 * @param array $taxonomy_one Taxonomia 1.
	 * @param array $taxonomy_two Taxonomia 2.
	 * @return array Set gerado em Regex.
	 */
	private function url_two( $taxonomy_one , $taxonomy_two ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				if ( $data1[ 'tax' ] != $data2[ 'tax' ] ) {
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/page/( [0-9]{1,3} )/?$' )
						, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ] , $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ] , $data2[ 'slug' ] ) . '&' . $this->tupla( 'paged' , '$matches[1]' ) )
						, 'hierarchical' => 'top'
					];
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/?$' )
						, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ] , $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ] , $data2[ 'slug' ] ) )
						, 'hierarchical' => 'top'
					];
				}
			}
		}
		return $rule;
	}
	/**
	 * Monta chaves em formato URL Parameter.
	 * 
	 * @param string $parameter Parametro referencial HTTP.
	 * @param string $value Valor validado.
	 * @return string Padrao chave=valor.
	 */
	private function tupla( $parameter , $value ) {
		return ( $parameter . '=' . $value );
	}
	/**
	 * Helper cruzamento com 3 eixos taxonomicos validos diferentes.
	 * 
	 * @param array $taxonomy_one Param 1
	 * @param array $taxonomy_two Param 2
	 * @param array $taxonomy_tree Param 3
	 * @return array Endpoints unificados.
	 */
	private function url_tree( $taxonomy_one , $taxonomy_two , $taxonomy_tree ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				foreach ( $taxonomy_tree as $slug3 => $data3 ) {
					if ( $data1[ 'tax' ] != $data2[ 'tax' ] && $data1[ 'tax' ] != $data3[ 'tax' ] && $data2[ 'tax' ] != $data3[ 'tax' ] ) {
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/page/( [0-9]{1,3} )/?$' )
							, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ] , $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ] , $data2[ 'slug' ] ) . '&' . $this->tupla( $data3[ 'tax' ] , $data3[ 'slug' ] ) . '&' . $this->tupla( 'paged' , '$matches[1]' ) )
							, 'hierarchical' => 'top'
						];
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/?$' )
							, 'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ] , $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ] , $data2[ 'slug' ] ) . '&' . $this->tupla( $data3[ 'tax' ] , $data3[ 'slug' ] ) )
							, 'hierarchical' => 'top'
						];
					}
				}
			}
		}
		return $rule;
	}
	/**
	 * Helper cruzamento em 4 eixos (Max depth tree endpoint router).
	 * 
	 * @param array $taxonomy_one Param 1
	 * @param array $taxonomy_two Param 2
	 * @param array $taxonomy_tree Param 3
	 * @param array $taxonomy_four Param 4
	 * @return array Regras validadas WP Engine.
	 */
	private function url_four( $taxonomy_one , $taxonomy_two , $taxonomy_tree , $taxonomy_four ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				foreach ( $taxonomy_tree as $slug3 => $data3 ) {
					foreach ( $taxonomy_four as $slug4 => $data4 ) {
						if ( $data1[ 'tax' ] !== $data2[ 'tax' ] && $data1[ 'tax' ] !== $data3[ 'tax' ] && $data1[ 'tax' ] !== $data4[ 'tax' ] && $data2[ 'tax' ] !== $data3[ 'tax' ] && $data2[ 'tax' ] !== $data4[ 'tax' ] && $data3[ 'tax' ] !== $data4[ 'tax' ] ) {
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/page/( [0-9]{1,3} )/?$'
								, 'rule' => self::INDEX_CACHE . '&' . $data1[ 'tax' ] . '=' . $data1[ 'slug' ] . '&' . $data2[ 'tax' ] . '=' . $data2[ 'slug' ] . '&' . $data3[ 'tax' ] . '=' . $data3[ 'slug' ] . '&' . $data4[ 'tax' ] . '=' . $data4[ 'slug' ] . '&' . $this->tupla( 'paged' , '$matches[1]' )
								, 'hierarchical' => 'top'
							];
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/?$'
								, 'rule' => self::INDEX_CACHE . '&' . $data1[ 'tax' ] . '=' . $data1[ 'slug' ] . '&' . $data2[ 'tax' ] . '=' . $data2[ 'slug' ] . '&' . $data3[ 'tax' ] . '=' . $data3[ 'slug' ] . '&' . $data4[ 'tax' ] . '=' . $data4[ 'slug' ]
								, 'hierarchical' => 'top'
							];
						}
					}
				}
			}
		}
		return $rule;
	}
	/**
	 * Acopla nativamente regras consolidadas no hook interno de add_rewrite_rule.
	 * 
	 * @param array $rules Lista contendo Regex e Mapeamento query string nativo.
	 * @return void Registra rotas.
	 */
	private function register( $rules ) {
		foreach ( $rules as $rule ) {
			add_rewrite_rule( $rule[ 'regex' ] , $rule[ 'rule' ] , $rule[ 'hierarchical' ] );
		}
	}
	/**
	 * Metodo agregador final que une todos subniveis dinamicos validados em arvore linear de Regex.
	 * 
	 * @param array $tax_contrato
	 * @param array $tax_tipo_imovel
	 * @param array $tax_cidade
	 * @return array Estrutura pronta do router do plugin.
	 */
	private function cria( $tax_contrato , $tax_tipo_imovel , $tax_cidade ) {
		$rules = array_merge(
			$this->url_seo_landing_pages( $tax_contrato , $tax_tipo_imovel , $tax_cidade )
			, $this->url_one( $tax_contrato )
			, $this->url_one( $tax_tipo_imovel )
			, $this->url_one( $tax_cidade )
			, $this->url_two( $tax_contrato , $tax_tipo_imovel )
			, $this->url_two( $tax_contrato , $tax_cidade )
			, $this->url_two( $tax_tipo_imovel , $tax_contrato )
			, $this->url_two( $tax_tipo_imovel , $tax_cidade )
			, $this->url_two( $tax_cidade , $tax_contrato )
			, $this->url_two( $tax_cidade , $tax_tipo_imovel )
			, $this->url_tree( $tax_contrato , $tax_tipo_imovel , $tax_cidade )
			, $this->url_tree( $tax_contrato , $tax_cidade , $tax_tipo_imovel )
			, $this->url_tree( $tax_tipo_imovel , $tax_contrato , $tax_cidade )
			, $this->url_tree( $tax_tipo_imovel , $tax_cidade , $tax_contrato )
			, $this->url_tree( $tax_cidade , $tax_contrato , $tax_tipo_imovel )
			, $this->url_tree( $tax_cidade , $tax_tipo_imovel , $tax_contrato )
		);
		return $rules;
	}
	/**
	 * Deleta e tranca transients do banco de dados relacional ( limpa indexadores NLP antigos ).
	 * 
	 * @return void Destruicao de transients via interface Options WP.
	 */
	public function clear( ) {
		delete_transient( self::CACHE_URL_AMIGAVEL );
		delete_transient( 'CACHE_MAPA_HIERARQUICO_V4' );
	}
	/**
	 * Hook publico construtor - varre e insere lógicas de SEO dinâmicas para Flush.
	 * 
	 * @return void Inicializacao principal WP.
	 */
	public function do( ) {
		$forcar_reset = isset( $_GET[ 'forcar_reset' ] );
		if ( $forcar_reset ) {
			$this->clear( );
		}
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		$rules = false;
		if ( ! $em_desenvolvimento ) {
			$rules = get_transient( self::CACHE_URL_AMIGAVEL );
		}
		if ( $rules === false ) {
			$tax_contrato = [];
			foreach ( lista_contratos( ) as $contrato ) {
				$tax_contrato[ sanitize_title( $contrato->name ) ] = [ 'tax' => 'contrato' , 'slug' => $contrato->slug ];
			}
			$tax_tipo_imovel = [];
			foreach ( lista_tipo_imovel( ) as $tipo_imovel ) {
				$tax_tipo_imovel[ sanitize_title( $tipo_imovel->name ) ] = [ 'tax' => 'tipo-imovel' , 'slug' => $tipo_imovel->slug ];
			}
			$tax_cidade = [];
			foreach ( lista_cidade( ) as $cidade ) {
				$tax_cidade[ sanitize_title( $cidade->name ) ] = [ 'tax' => 'cidade' , 'slug' => $cidade->slug ];
			}
			if ( empty( $tax_contrato ) || empty( $tax_tipo_imovel ) || empty( $tax_cidade ) ) {
				return;
			}
			$hierarquia = self::get_mapa_hierarquico( );
			$rules = array_merge(
				$this->cria( $tax_contrato , $tax_tipo_imovel , $tax_cidade )
				, $this->url_hierarquia_regras( $hierarquia[ 'regioes_rules' ] , $tax_contrato , $tax_tipo_imovel )
			);
			if ( ! $em_desenvolvimento ) {
				set_transient( self::CACHE_URL_AMIGAVEL , $rules , WEEK_IN_SECONDS );
			}
		}
		$this->register( $rules );
		if ( $forcar_reset || $this->delete_cache === true ) {
			flush_rewrite_rules( false );
		}
	}
	/**
	 * Gera indexação em mapa de imoveis no frontend listando todas as combinatorias viavem.
	 * 
	 * @return array Dicionario aninhado hierarquicamente via MySQL Direct Query com JOINs complexos.
	 */
	public static function gerar_links_mapa_imoveis( ) {
		global $wpdb;
		$tipos_sinonimos = self::get_mapa_tipo_imovel( );
		$mapa_slug_para_canonico = [];
		$mapa_sinonimo_slug = [];
		foreach ( $tipos_sinonimos as $canonical => $sinonimos_array ) {
			foreach ( array_keys( $sinonimos_array ) as $sinonimo ) {
				$slug_sinonimo = sanitize_title( $sinonimo );
				$mapa_slug_para_canonico[ $slug_sinonimo ] = $canonical;
				$mapa_sinonimo_slug[ $slug_sinonimo ] = mb_convert_case( strtolower( trim( $sinonimo ) ) , MB_CASE_TITLE , 'UTF-8' );
			}
		}
		$mapa_labels_canonicos = [
			'apartamento' => 'Apartamentos'
			, 'imovel-comercial' => 'Imóveis Comerciais'
			, 'casa' => 'Casas'
			, 'rural' => 'Áreas Rurais'
			, 'lazer' => 'Propriedades de Lazer'
			, 'terreno-ou-area' => 'Terrenos e Áreas'
		];
		$cache_key = 'CACHE_MAPA_IMOVEIS_TREE_V2';
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		if ( ! $em_desenvolvimento ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) return $cached;
		}
		$sql_matriz = "SELECT 'venda' AS contrato, pm_tipo.meta_value AS tipo_imovel, pm_cidade.meta_value AS cidade, pm_regiao.meta_value AS regiao, COUNT( p.ID ) AS total_imoveis FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D' INNER JOIN {$wpdb->postmeta} pm_contrato ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarVenda' AND pm_contrato.meta_value = '1' LEFT JOIN {$wpdb->postmeta} pm_tipo ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome' LEFT JOIN {$wpdb->postmeta} pm_cidade ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade' LEFT JOIN {$wpdb->postmeta} pm_regiao ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao' WHERE p.post_type = 'imovel' AND p.post_status = 'publish' GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value UNION ALL SELECT 'locacao' AS contrato, pm_tipo.meta_value AS tipo_imovel, pm_cidade.meta_value AS cidade, pm_regiao.meta_value AS regiao, COUNT( p.ID ) AS total_imoveis FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D' INNER JOIN {$wpdb->postmeta} pm_contrato ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarLocacao' AND pm_contrato.meta_value = '1' LEFT JOIN {$wpdb->postmeta} pm_tipo ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome' LEFT JOIN {$wpdb->postmeta} pm_cidade ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade' LEFT JOIN {$wpdb->postmeta} pm_regiao ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao' WHERE p.post_type = 'imovel' AND p.post_status = 'publish' GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value UNION ALL SELECT 'lancamento' AS contrato, pm_tipo.meta_value AS tipo_imovel, pm_cidade.meta_value AS cidade, pm_regiao.meta_value AS regiao, COUNT( p.ID ) AS total_imoveis FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D' INNER JOIN {$wpdb->postmeta} pm_contrato ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarLancamento' AND pm_contrato.meta_value = '1' LEFT JOIN {$wpdb->postmeta} pm_tipo ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome' LEFT JOIN {$wpdb->postmeta} pm_cidade ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade' LEFT JOIN {$wpdb->postmeta} pm_regiao ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao' WHERE p.post_type = 'imovel' AND p.post_status = 'publish' GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value ORDER BY total_imoveis DESC;";
		$combinacoes = $wpdb->get_results( $sql_matriz , ARRAY_A );
		$arvore = [];
		if ( ! empty( $combinacoes ) ) {
			$mapa_nomes_contrato = [
				'venda' => 'Venda'
				, 'locacao' => 'Locação'
				, 'lancamento' => 'Lançamento'
			];
			foreach ( $combinacoes as $linha ) {
				if ( empty( $linha[ 'cidade' ] ) || empty( $linha[ 'tipo_imovel' ] ) ) continue;
				$c_slug = $linha[ 'contrato' ];
				$cid_slug = sanitize_title( $linha[ 'cidade' ] );
				$reg_slug = ! empty( $linha[ 'regiao' ] ) ? sanitize_title( self::limpar_faxina_bairro( $linha[ 'regiao' ] ) ) : '';
				$t_slug_db = sanitize_title( $linha[ 'tipo_imovel' ] );
				$canonical_slug = $mapa_slug_para_canonico[ $t_slug_db ] ?? $t_slug_db;
				$nome_contrato = $mapa_nomes_contrato[ $c_slug ];
				$nome_cidade = mb_convert_case( strtolower( trim( $linha[ 'cidade' ] ) ) , MB_CASE_TITLE , 'UTF-8' );
				$nome_regiao = ! empty( $linha[ 'regiao' ] ) ? mb_convert_case( strtolower( trim( $linha[ 'regiao' ] ) ) , MB_CASE_TITLE , 'UTF-8' ) : 'Centro e Principais Bairros';
				$nome_tipo_canonico = $mapa_labels_canonicos[ $canonical_slug ] ?? mb_convert_case( str_replace( '-' , ' ' , $canonical_slug ) , MB_CASE_TITLE , 'UTF-8' );
				if ( ! isset( $arvore[ $c_slug ] ) ) {
					$arvore[ $c_slug ] = [ 'nome' => $nome_contrato , 'tipos' => [] ];
				}
				if ( ! isset( $arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ] ) ) {
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ] = [ 'nome' => $nome_tipo_canonico , 'cidades' => [] ];
				}
				if ( ! isset( $arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ] ) ) {
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ] = [ 'nome' => $nome_cidade , 'regioes' => [] ];
				}
				if ( ! isset( $arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ] ) ) {
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ] = [ 'nome' => $nome_regiao , 'total' => 0 , 'sinonimos' => [] ];
				}
				$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ][ 'total' ] += (int) $linha[ 'total_imoveis' ];
				if ( isset( $tipos_sinonimos[ $canonical_slug ] ) ) {
					foreach ( array_keys( $tipos_sinonimos[ $canonical_slug ] ) as $synonimo ) {
						$t_slug_url = sanitize_title( $synonimo );
						$nome_tipo = $mapa_sinonimo_slug[ $t_slug_url ] ?? mb_convert_case( $synonimo , MB_CASE_TITLE , 'UTF-8' );
						$partes_url = array_filter( [ $c_slug , $t_slug_url , $cid_slug , $reg_slug ] );
						$url = "/" . implode( "/" , $partes_url ) . "/";
						$label = "{$nome_contrato} de {$nome_tipo} em {$nome_cidade}" . ( ! empty( $linha[ 'regiao' ] ) ? " - {$nome_regiao}" : "" );
						$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ][ 'sinonimos' ][ $t_slug_url ] = [
							'url' => $url
							, 'label' => $label
							, 'label_simples' => $nome_tipo
						];
					}
				} else {
					$t_slug_url = $t_slug_db;
					$nome_tipo = mb_convert_case( strtolower( trim( $linha[ 'tipo_imovel' ] ) ) , MB_CASE_TITLE , 'UTF-8' );
					$partes_url = array_filter( [ $c_slug , $t_slug_url , $cid_slug , $reg_slug ] );
					$url = "/" . implode( "/" , $partes_url ) . "/";
					$label = "{$nome_contrato} de {$nome_tipo} em {$nome_cidade}" . ( ! empty( $linha[ 'regiao' ] ) ? " - {$nome_regiao}" : "" );
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ][ 'sinonimos' ][ $t_slug_url ] = [
						'url' => $url
						, 'label' => $label
						, 'label_simples' => $nome_tipo
					];
				}
			}
		}
		if ( ! $em_desenvolvimento ) {
			set_transient( $cache_key , $arvore , 12 * HOUR_IN_SECONDS );
		}
		return $arvore;
	}
}