<?php
class PrettyUrl {
	const CACHE_URL_AMIGAVEL = 'CACHE_PRETTY_URL';
	const INDEX_CACHE = 'index.php?post_type=imovel&tipo_pesquisa_submit=imovel';
	private $delete_cache;

	public function __construct( $delete_cache = false ) {
		$this->delete_cache = $delete_cache;
		add_action( 'template_redirect', [$this, 'redirecionar_acentos'], 1 );

		// Mantém a query var liberada por segurança para a pesquisa fallback funcionar
		add_filter( 'query_vars', [$this, 'liberar_query_vars_consulta'] );
	}

	public function liberar_query_vars_consulta( $query_vars ) {
		$query_vars[] = 'referencia';
		$query_vars[] = 'tipo_pesquisa_submit';
		return $query_vars;
	}

	public static function get_mapa_tipo_imovel( ) {
		return [
			'apartamento' => array_flip( [
				'apartamento', 'flat', 'loft', 'duplex', 'triplex', 'apartamento flat', 'apartamento loft', 'apartamento duplex', 'apartamento triplex', 'cobertura', 'apartamento cobertura', 'kitnet', 'kit', 'studio', 'penthouse', 'garden'
			] ),
			'imovel-comercial' => array_flip( [
				'imovel comercial', 'loja', 'sobreloja', 'sobre loja', 'salão', 'sala', 'barracão', 'galpão', 'consultório', 'escritório', 'bar', 'bodega', 'restaurante', 'lanchonete', 'box', 'ponto comercial', 'depósito', 'pavilhão', 'prédio comercial', 'centro comercial', 'loja térrea'
			] ),
			'casa' => array_flip( [
				'casa', 'casa térra', 'sobrado', 'assobradado', 'assobradada', 'residência', 'casa em condomínio', 'mansão', 'edícula', 'casa de vila', 'bangalô', 'chalé', 'casa geminada'
			] ),
			'rural' => array_flip( [
				'rural', 'chácara', 'sítio', 'fazenda', 'propriedade', 'granja', 'estância', 'pomar', 'horta', 'haras', 'pousada', 'área rural'
			] ),
			'lazer' => array_flip( [
				'lazer', 'rancho', 'balneário', 'clubinho', 'clube', 'chácara', 'quadra', 'casa de campo', 'centro de eventos', 'área de lazer', 'salão de festas', 'retiro'
			] ),
			'terreno-ou-area' => array_flip( [
				'terreno ou área', 'área', 'área industrial', 'área incorporação', 'terreno', 'lote', 'gleba', 'eira', 'loteamento', 'área comercial', 'recinto', 'recinto de exposiçao', 'exposiçao', 'área para rodeio', 'estacionamento'
			] ),
		];
	}

	public static function get_mapa_contrato( ) {
		return [
			'venda' => array_flip( [
				'venda', 'vendas', 'compra', 'aquisição', 'aquisicao', 'investimento', 'negociação', 'comercialização',
				'comprar', 'vender', 'adquirir', 'adiquirir', 'investir', 'comprando', 'vendendo',
				'vende', 'compro', 'adquire', 'adiquire',
				'vende se', 'compra se'
			] ),
			'locacao' => array_flip( [
				'locação', 'locacao', 'locações', 'locacoes', 'aluguel', 'aluguéis', 'alugueis', 'aluguer', 'arrendamento',
				'alugar', 'locar', 'arrendar', 'alugando', 'locando',
				'aluga', 'alugo', 'loca',
				'aluga se', 'loca se'
			] )
		];
	}

	public static function get_ruidos( ) {
		return [
			'em', 'de', 'do', 'da', 'dos', 'das', 'no', 'na', 'nos', 'nas', 'com', 'para',
			'vila', 'vl', 'bairro', 'jardim', 'jd', 'residencial', 'res', 'condominio', 'cond',
			'parque', 'pq', 'conjunto', 'conj', 'cj', 'habitacional', 'hab', 'loteamento',
			'gleba', 'chacara', 'chacaras', 'sitio', 'fazenda', 'estancia', 'recanto'
		];
	}

	public static function limpar_faxina_bairro( $nome ) {
		$nome = remove_accents( strtolower( trim( $nome ) ) );
		$nome = preg_replace( '/^[0-9]+[a-z]?\s*( ?:-\s* )?/', '', $nome );
		$nome = str_replace( '.', ' ', $nome );
		$ruidos = self::get_ruidos( );
		foreach ( $ruidos as $ruido ) {
			$nome = preg_replace( '/\b' . $ruido . '\b/u', ' ', $nome );
		}
		return trim( preg_replace( '/\s+/', ' ', $nome ) );
	}

	public static function get_mapa_hierarquico( ) {
		global $wpdb;
		$cache_key = 'CACHE_MAPA_HIERARQUICO_V4';
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		$dados = false;
		if ( !$em_desenvolvimento ) {
			$dados = get_transient( $cache_key );
		}
		if ( $dados !== false ) {
			return $dados;
		}
		$query = "SELECT
			b.name AS bairro_nome, b.slug AS bairro_slug,
			r.name AS regiao_nome, r.slug AS regiao_slug,
			c.name AS cidade_nome, c.slug AS cidade_slug
		FROM {$wpdb->terms} b
		INNER JOIN {$wpdb->term_taxonomy} tt_b ON b.term_id = tt_b.term_id AND tt_b.taxonomy = 'bairro'
		LEFT JOIN {$wpdb->termmeta} tm_b ON b.term_id = tm_b.term_id AND tm_b.meta_key = 'parent_id'
		LEFT JOIN {$wpdb->terms} r ON r.slug = tm_b.meta_value
		INNER JOIN {$wpdb->term_taxonomy} tt_r ON r.term_id = tt_r.term_id AND tt_r.taxonomy = 'regiao'
		LEFT JOIN {$wpdb->termmeta} tm_r ON r.term_id = tm_r.term_id AND tm_r.meta_key = 'parent_id'
		LEFT JOIN {$wpdb->terms} c ON c.slug = tm_r.meta_value
		INNER JOIN {$wpdb->term_taxonomy} tt_c ON c.term_id = tt_c.term_id AND tt_c.taxonomy = 'cidade'
		WHERE c.slug IS NOT NULL";
		$resultados = $wpdb->get_results( $query );
		$cidades_nlp = [];
		$regioes_nlp = [];
		$bairros_nlp = [];
		$regioes_rules = [];
		foreach ( $resultados as $row ) {
			$cidade_slug_url = sanitize_title( $row->cidade_nome );
			$regiao_sem_prefixo = preg_replace( '/^[0-9]+[a-z]?\s*( ?:-\s* )?/i', '', $row->regiao_nome );
			$regiao_slug_url = sanitize_title( $regiao_sem_prefixo );
			$bairro_sem_prefixo = preg_replace( '/^[0-9]+[a-z]?\s*( ?:-\s* )?/i', '', $row->bairro_nome );
			$bairro_slug_url = sanitize_title( $bairro_sem_prefixo );
			$cidade_nome_limpo = remove_accents( strtolower( trim( $row->cidade_nome ) ) );
			$cidades_nlp[$cidade_nome_limpo] = [
				'slug' => $row->cidade_slug,
				'nome' => $row->cidade_nome,
				'slug_url' => $cidade_slug_url
			];
			$regiao_nome_limpo = self::limpar_faxina_bairro( $row->regiao_nome );
			if ( !isset( $regioes_nlp[$regiao_nome_limpo] ) ) $regioes_nlp[$regiao_nome_limpo] = [];
			$regioes_nlp[$regiao_nome_limpo][$row->cidade_slug] = [
				'regiao_slug' => $row->regiao_slug,
				'regiao_nome' => $row->regiao_nome,
				'regiao_slug_url' => $regiao_slug_url,
				'cidade_slug' => $row->cidade_slug,
				'cidade_nome' => $row->cidade_nome,
				'cidade_slug_url' => $cidade_slug_url
			];
			$bairro_nome_limpo = self::limpar_faxina_bairro( $row->bairro_nome );
			if ( !isset( $bairros_nlp[$bairro_nome_limpo] ) ) $bairros_nlp[$bairro_nome_limpo] = [];
			$bairros_nlp[$bairro_nome_limpo][$row->cidade_slug] = [
				'bairro_slug' => $row->bairro_slug,
				'bairro_nome' => $row->bairro_nome,
				'bairro_slug_url' => $bairro_slug_url,
				'regiao_slug' => $row->regiao_slug,
				'regiao_nome' => $row->regiao_nome,
				'regiao_slug_url' => $regiao_slug_url,
				'cidade_slug' => $row->cidade_slug,
				'cidade_nome' => $row->cidade_nome,
				'cidade_slug_url' => $cidade_slug_url
			];
			$path_regiao = $cidade_slug_url . '/' . $regiao_slug_url;
			$regioes_rules[$path_regiao] = [
				'cidade' => $row->cidade_slug,
				'regiao' => $row->regiao_slug
			];
		}
		$dados = [
			'cidades_nlp' => $cidades_nlp,
			'regioes_nlp' => $regioes_nlp,
			'bairros_nlp' => $bairros_nlp,
			'regioes_rules' => $regioes_rules
		];
		if ( !$em_desenvolvimento ) {
			set_transient( $cache_key, $dados, 10 * MINUTE_IN_SECONDS );
		}
		return $dados;
	}

	public function redirecionar_acentos( ) {
		if ( is_404( ) ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
			$uri = $_SERVER['REQUEST_URI'];
			$parsed = parse_url( $uri );
			$path = isset( $parsed['path'] ) ? ltrim( $parsed['path'], '/' ) : '';
			$path_digitado = urldecode( $path );
			$path_digitado_clean = trim( rtrim( $path_digitado, '/' ) );

			// =================================================================
			// TRAPACEANDO O TRAPACEADOR - REDIRECIONAMENTO DE REFERÊNCIA
			// =================================================================
			// A MÁSCARA ATUALIZADA: captura traço [-] ou barra [/] e pega o ID.
			// Vai identificar: ref-15590, referencia-15590, ref/15590, referencia/15590
			if ( preg_match('/(?:ref|referencia)[\-\/]([0-9]+)/i', $path_digitado_clean, $matches) ) {
				$referencia_alvo = $matches[1];
				global $wpdb;

				// Busca o ID interno do imóvel
				$post_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'referencia' AND meta_value = %s LIMIT 1",
					$referencia_alvo
				));

				if ( $post_id ) {
					// Imóvel existe: Envia direto para a URL bonita gerada pelo slug do post!
					wp_redirect( get_permalink( $post_id ), 301 );
					exit;
				} else {
					// Imóvel não existe: Envia EXATAMENTE para a URL de consulta raiz que você testou e funciona
					wp_redirect( home_url( '/index.php?post_type=imovel&tipo_pesquisa_submit=imovel&referencia=' . $referencia_alvo ), 301 );
					exit;
				}
			}
			// =================================================================

			$clean_path = remove_accents( strtolower( $path_digitado ) );
			$search_string = preg_replace( '/[\-\_\/\#\t\.]+/u', ' ', $clean_path );
			$ruidos = self::get_ruidos( );
			foreach( $ruidos as $ruido ) {
				$search_string = preg_replace( '/\b' . $ruido . '\b/u', ' ', $search_string );
			}
			$search_string = preg_replace( '/\s+/', ' ', trim( $search_string ) );
			$string_inicial_debug = $search_string;
			$hierarquia = self::get_mapa_hierarquico( );
			$options = get_option( 'pinedu_imovel_options', [] );
			$cidade_padrao_slug = $options['cidade'] ?? '0001';
			$contrato_encontrado = $this->extrair_e_limpar_termo( $search_string, self::get_mapa_contrato( ) );
			$tipo_encontrado	 = $this->extrair_e_limpar_termo( $search_string, self::get_mapa_tipo_imovel( ) );
			$cidade_explicita_slug = null;
			$cidade_explicita_nome = null;
			$cidade_match_pattern = null;
			foreach ( $hierarquia['cidades_nlp'] as $sinonimo => $data ) {
				$pattern = '/\b' . preg_quote( $sinonimo, '/' ) . '\b/u';
				if ( preg_match( $pattern, $search_string ) ) {
					$cidade_explicita_slug = $data['slug'];
					$cidade_explicita_nome = $data['nome'];
					$cidade_match_pattern = $pattern;
					break;
				}
			}
			$cidade_alvo = $cidade_explicita_slug ? $cidade_explicita_slug : $cidade_padrao_slug;
			$bairro_encontrado_nome = null;
			$regiao_encontrada_slug_url = null;
			$cidade_final_slug_url = null;
			$cidade_final_nome = null;
			// BAIRROS
			$chaves_bairros = array_keys( $hierarquia['bairros_nlp'] );
			usort( $chaves_bairros, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
			foreach ( $chaves_bairros as $bairro_limpo ) {
				if ( empty( $bairro_limpo ) ) continue;
				$bairro_valido = null;
				if ( isset( $hierarquia['bairros_nlp'][$bairro_limpo][$cidade_alvo] ) ) {
					$bairro_valido = $hierarquia['bairros_nlp'][$bairro_limpo][$cidade_alvo];
				} elseif ( !$cidade_explicita_slug ) {
					$bairro_valido = reset( $hierarquia['bairros_nlp'][$bairro_limpo] );
				}
				if ( !$bairro_valido ) continue;
				$pattern = '/\b' . preg_quote( $bairro_limpo, '/' ) . '\b/u';
				if ( preg_match( $pattern, $search_string ) ) {
					$search_string = preg_replace( $pattern, ' ', $search_string );
					$bairro_encontrado_nome = $bairro_valido['bairro_nome'];
					$regiao_encontrada_slug_url = $bairro_valido['regiao_slug_url'];
					$cidade_final_slug_url = $bairro_valido['cidade_slug_url'];
					$cidade_final_nome = $bairro_valido['cidade_nome'];
					$pat_reg = '/\b' . preg_quote( self::limpar_faxina_bairro( $bairro_valido['regiao_nome'] ), '/' ) . '\b/u';
					$search_string = preg_replace( $pat_reg, ' ', $search_string );
					break;
				}
			}
			// REGIÕES
			if ( !$bairro_encontrado_nome ) {
				$chaves_regioes = array_keys( $hierarquia['regioes_nlp'] );
				usort( $chaves_regioes, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
				foreach ( $chaves_regioes as $regiao_limpa ) {
					if ( empty( $regiao_limpa ) ) continue;
					$regiao_valida = null;
					if ( isset( $hierarquia['regioes_nlp'][$regiao_limpa][$cidade_alvo] ) ) {
						$regiao_valida = $hierarquia['regioes_nlp'][$regiao_limpa][$cidade_alvo];
					} elseif ( !$cidade_explicita_slug ) {
						$regiao_valida = reset( $hierarquia['regioes_nlp'][$regiao_limpa] );
					}
					if ( !$regiao_valida ) continue;
					$pattern = '/\b' . preg_quote( $regiao_limpa, '/' ) . '\b/u';
					if ( preg_match( $pattern, $search_string ) ) {
						$search_string = preg_replace( $pattern, ' ', $search_string );
						$regiao_encontrada_slug_url = $regiao_valida['regiao_slug_url'];
						$cidade_final_slug_url = $regiao_valida['cidade_slug_url'];
						$cidade_final_nome = $regiao_valida['cidade_nome'];
						break;
					}
				}
			}
			if ( $cidade_match_pattern ) {
				$search_string = preg_replace( $cidade_match_pattern, ' ', $search_string );
			}
			if ( !$bairro_encontrado_nome && !$regiao_encontrada_slug_url && $cidade_explicita_slug ) {
				$cidade_final_slug_url = sanitize_title( $cidade_explicita_nome );
				$cidade_final_nome = $cidade_explicita_nome;
			}
			$partes = [];
			if ( $contrato_encontrado ) $partes[] = $contrato_encontrado;
			if ( $tipo_encontrado ) $partes[] = $tipo_encontrado;
			if ( $cidade_final_slug_url ) $partes[] = $cidade_final_slug_url;
			if ( $regiao_encontrada_slug_url ) $partes[] = $regiao_encontrada_slug_url;
			$nova_rota = '';
			if ( $contrato_encontrado && $tipo_encontrado && $cidade_final_slug_url && !$regiao_encontrada_slug_url ) {
				$nova_rota = $contrato_encontrado . '-' . $tipo_encontrado . '-' . $cidade_final_slug_url;
			} else {
				$nova_rota = implode( '/', $partes );
			}
			$search_string = preg_replace( '/\s+/', ' ', trim( $search_string ) );
			if ( !empty( $search_string ) ) {
				$sobras = str_replace( ' ', '-', $search_string );
				$nova_rota .= empty( $nova_rota ) ? $sobras : '/' . $sobras;
			}
			$nova_rota_clean = trim( $nova_rota, '/' );
			if ( isset( $_GET['debug_url'] ) ) {
				$relatorio = [
					'01_URI_DIGITADA' => $path_digitado_clean,
					'02_TEXTO_LIMPO' => $string_inicial_debug,
					'03_BAIRRO_IDENTIFICADO' => $bairro_encontrado_nome ?: 'NENHUM',
					'04_REGIAO_CRAVADA_NA_URL' => $regiao_encontrada_slug_url ?: 'NENHUMA',
					'05_CIDADE_CRAVADA_NA_URL' => $cidade_final_nome ?: 'NENHUMA',
					'06_ROTA_GERADA' => $nova_rota_clean,
					'07_VAI_REDIRECIONAR?' => ( $nova_rota_clean !== $path_digitado_clean && !empty( $nova_rota_clean ) ) ? 'SIM' : 'NÃO ( Impede o Loop Infinito )'
				];
				$css_fullscreen = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:#111; color:#0f0; padding:20px; font-size:13px; z-index:999999; margin:0; overflow:auto; box-sizing:border-box; font-family:monospace;';
				wp_die( '<pre style="' . $css_fullscreen . '">' . print_r( $relatorio, true ) . '</pre>', 'Painel de Debug NLP' );
			}
			if ( $nova_rota_clean !== $path_digitado_clean && !empty( $nova_rota_clean ) ) {
				$new_url = home_url( '/' . $nova_rota_clean . '/' );
				if ( !empty( $parsed['query'] ) ) {
					$new_url .= '?' . $parsed['query'];
				}
				wp_redirect( $new_url, 301 );
				exit;
			}
		}
	}

	private function extrair_e_limpar_termo( &$string_busca, $mapa_agrupado ) {
		if ( empty( $mapa_agrupado ) ) return null;
		$sinonimos_achatados = [];
		foreach ( $mapa_agrupado as $canonical => $sinonimos ) {
			foreach ( array_keys( $sinonimos ) as $sinonimo ) {
				$sinonimos_achatados[remove_accents( strtolower( $sinonimo ) )] = $canonical;
			}
		}
		uksort( $sinonimos_achatados, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		foreach ( $sinonimos_achatados as $sinonimo => $canonical ) {
			$pattern = '/\b' . preg_quote( $sinonimo, '/' ) . '\b/u';
			if ( preg_match( $pattern, $string_busca ) ) {
				$string_busca = preg_replace( $pattern, ' ', $string_busca );
				return $canonical;
			}
		}
		return null;
	}

	// =========================================================================
	// ISCAS DE SEO ( INTERNAL LINKING )
	// =========================================================================

	public static function gerar_links_from_search( $params ) {
		$links = [];
		$partes_url = [];
		$nomes = [];
		// 1. CONTRATO
		if ( !empty( $params['contrato'] ) ) {
			$val = $params['contrato'];
			$term = get_term_by( 'slug', $val, 'contrato' );
			if ( $val == '1' || ( $term && strtolower( $term->name ) == 'venda' ) ) {
				$c_slug = 'venda';
				$nomes['contrato'] = 'Venda';
			} elseif ( $val == '2' || ( $term && strtolower( $term->name ) == 'locacao' ) ) {
				$c_slug = 'locacao';
				$nomes['contrato'] = 'Locação';
			} elseif ( $term && !is_wp_error( $term ) ) {
				$c_slug = sanitize_title( $term->name );
				$nomes['contrato'] = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
			}
			if ( isset( $c_slug ) ) {
				$partes_url[] = $c_slug;
				$links[] = [
					'url'   => "/" . implode( "/", $partes_url ) . "/",
					'label' => $nomes['contrato']
				];
			}
		}
		// 2. TIPO DE IMÓVEL
		if ( !empty( $params['tipo-imovel'] ) ) {
			$term = get_term_by( 'slug', $params['tipo-imovel'], 'tipo-imovel' );
			if ( $term && !is_wp_error( $term ) ) {
				$t_slug = sanitize_title( $term->name );
				$nomes['tipo'] = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
				$partes_url[] = $t_slug;
				$label = $nomes['tipo'];
				$links[] = [
					'url'   => "/" . implode( "/", $partes_url ) . "/",
					'label' => $label
				];
			}
		}
		// 3. CIDADE
		if ( !empty( $params['cidade'] ) ) {
			$term = get_term_by( 'slug', $params['cidade'], 'cidade' );
			if ( $term && !is_wp_error( $term ) ) {
				$cid_slug = sanitize_title( $term->name );
				$nomes['cidade'] = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
				$partes_url[] = $cid_slug;
				$label = $nomes['cidade'];
				$links[] = [
					'url'   => "/" . implode( "/", $partes_url ) . "/",
					'label' => $label
				];
			}
		}
		// 4. REGIÃO
		if ( !empty( $params['regiao'] ) ) {
			$term = get_term_by( 'slug', $params['regiao'], 'regiao' );
			if ( $term && !is_wp_error( $term ) ) {
				$reg_nome = self::limpar_faxina_bairro( $term->name );
				$reg_slug = sanitize_title( $reg_nome );
				$nomes['regiao'] = mb_convert_case( $reg_nome, MB_CASE_TITLE, 'UTF-8' );
				$partes_url[] = $reg_slug;
				$label = $nomes['regiao'];
				$links[] = [
					'url'   => "/" . implode( "/", $partes_url ) . "/",
					'label' => $label
				];
			}
		}
		return $links;
	}

	public static function gerar_links_from_post( $post ) {
		if ( ! $post || $post->post_type !== 'imovel' ) return [];
		$cidade_slug = sanitize_title( $post->cidade );
		$regiao_slug = sanitize_title( self::limpar_faxina_bairro( $post->regiao ) );
		$tipo_slug   = sanitize_title( $post->tipoImovelNome );
		$links = [];
		$finalidades = [];
		if ( ( 1 == intval( $post->ativarVenda ) ) || ( 1 == intval( $post->ativarLancamento ) ) ) $finalidades[] = 'venda';
		if ( ( 1 == intval( $post->ativarLocacao ) ) ) $finalidades[] = 'locacao';

        foreach ( $finalidades as $contrato ) {
            $lbl_base = ( $contrato == 'venda' ? "Venda" : "Locação" );

            // a) Somente contrato (ex: /venda/)
            $links[] = [
                'url'   => "/{$contrato}/",
                'label' => $lbl_base
            ];

            // b) Somente contrato / tipoimovel (ex: /venda/casa/)
            if ( ! empty( $tipo_slug ) ) {
                $raw_tipo = $post->tipoImovelNome ?? str_replace( '-', ' ', $tipo_slug );
                $nome_tipo = mb_convert_case( $raw_tipo, MB_CASE_TITLE, 'UTF-8' );

                $links[] = [
                    'url'   => "/{$contrato}/{$tipo_slug}/",
                    'label' => "{$lbl_base} - {$nome_tipo}"
                ];

                // c) Somente contrato / tipoimovel / cidade (ex: /venda/casa/santo-andre/)
                if ( ! empty( $cidade_slug ) ) {
                    $raw_cidade = $post->cidade ?? str_replace( '-', ' ', $cidade_slug );
                    $nome_cidade = mb_convert_case( $raw_cidade, MB_CASE_TITLE, 'UTF-8' );

                    $acao = ( $contrato == 'venda' ? 'Venda' : 'Locação' );

                    $links[] = [
                        'url'   => "/{$contrato}/{$tipo_slug}/{$cidade_slug}/",
                        'label' => "{$nome_tipo} à {$acao} em {$nome_cidade}"
                    ];

                    // d) O completo (ex: /venda/casa/santo-andre/centro/)
                    if ( ! empty( $regiao_slug ) ) {
                        $raw_regiao = $post->regiao ?? str_replace( '-', ' ', $regiao_slug );
                        $nome_regiao = mb_convert_case( $raw_regiao, MB_CASE_TITLE, 'UTF-8' );

                        $links[] = [
                            'url'   => "/{$contrato}/{$tipo_slug}/{$cidade_slug}/{$regiao_slug}/",
                            'label' => "{$nome_tipo} à {$acao} em {$nome_cidade} - {$nome_regiao}"
                        ];
                    }
                }
            }
        }
		return $links;
	}

	// =========================================================================
	// GERADOR DE REWRITE RULES HIERÁRQUICAS
	// =========================================================================

	private function url_hierarquia_regras( $hierarquia_rules, $tax_contrato, $tax_tipo_imovel ) {
		$rules = [];
		foreach ( $hierarquia_rules as $path => $data ) {
			$base_rule = self::INDEX_CACHE . '&cidade=' . $data['cidade'] . '&regiao=' . $data['regiao'];
			$rules[] = [ 'regex' => '^' . $path . '/page/( [0-9]{1,3} )/?$', 'rule' => $base_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
			$rules[] = [ 'regex' => '^' . $path . '/?$', 'rule' => $base_rule, 'hierarchical' => 'top' ];
			foreach ( $tax_contrato as $nome_contrato => $c_data ) {
				$c_path = $nome_contrato . '/' . $path;
				$c_rule = $base_rule . '&contrato=' . $c_data['slug'];
				$rules[] = [ 'regex' => '^' . $c_path . '/page/( [0-9]{1,3} )/?$', 'rule' => $c_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
				$rules[] = [ 'regex' => '^' . $c_path . '/?$', 'rule' => $c_rule, 'hierarchical' => 'top' ];
				foreach ( $tax_tipo_imovel as $nome_tipo => $t_data ) {
					$ct_path = $nome_contrato . '/' . $nome_tipo . '/' . $path;
					$ct_rule = $c_rule . '&tipo-imovel=' . $t_data['slug'];
					$rules[] = [ 'regex' => '^' . $ct_path . '/page/( [0-9]{1,3} )/?$', 'rule' => $ct_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
					$rules[] = [ 'regex' => '^' . $ct_path . '/?$', 'rule' => $ct_rule, 'hierarchical' => 'top' ];
				}
			}
		}
		return $rules;
	}

	private function url_seo_landing_pages( $tax_contrato, $tax_tipo_imovel, $tax_cidade ) {
		$rule = [];
		$chave_venda = 'venda';
		$chave_locacao = 'locacao';
		foreach ( array_keys( $tax_contrato ) as $key ) {
			if ( strpos( $key, 'vend' ) !== false || strpos( $key, 'compr' ) !== false ) $chave_venda = $key;
			if ( strpos( $key, 'loca' ) !== false || strpos( $key, 'alug' ) !== false ) $chave_locacao = $key;
		}
		$mapa_prefixos = [
			'venda'   => $chave_venda,
			'comprar' => $chave_venda,
			'vender'  => $chave_venda,
			'aluguel' => $chave_locacao,
			'alugar'  => $chave_locacao,
			'locar'   => $chave_locacao,
			'locação' => $chave_locacao,
			'locacao' => $chave_locacao,
			'arrendar' => $chave_locacao
		];
		foreach ( $tax_cidade as $slug_cidade => $data_cidade ) {
			$aliases_base = ['imoveis', 'imobiliaria'];
			foreach ( $aliases_base as $alias ) {
				$rule[] = [
					'regex' => ( '^' . $alias . '-' . $slug_cidade . '/page/( [0-9]{1,3} )/?$' ),
					'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data_cidade['tax'], $data_cidade['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
					'hierarchical' => 'top'
				];
				$rule[] = [
					'regex' => ( '^' . $alias . '-' . $slug_cidade . '/?$' ),
					'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data_cidade['tax'], $data_cidade['slug'] ) ),
					'hierarchical' => 'top'
				];
			}
			foreach ( $tax_tipo_imovel as $slug_tipo => $data_tipo ) {
				$prefixo_pluralizado = $slug_tipo . 's?';
				$regra_base = self::INDEX_CACHE
							. '&' . $this->tupla( $data_tipo['tax'], $data_tipo['slug'] )
							. '&' . $this->tupla( $data_cidade['tax'], $data_cidade['slug'] );
				$rule[] = [
					'regex' => ( '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/( [0-9]{1,3} )/?$' ),
					'rule' => ( $regra_base . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
					'hierarchical' => 'top'
				];
				$rule[] = [
					'regex' => ( '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$' ),
					'rule' => ( $regra_base ),
					'hierarchical' => 'top'
				];
				foreach ( $mapa_prefixos as $verbo => $alvo_contrato ) {
					$regra_intencao = $regra_base;
					if ( isset( $tax_contrato[$alvo_contrato] ) ) {
						$data_contrato = $tax_contrato[$alvo_contrato];
						$regra_intencao .= '&' . $this->tupla( $data_contrato['tax'], $data_contrato['slug'] );
					}
					$rule[] = [
						'regex' => ( '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/( [0-9]{1,3} )/?$' ),
						'rule' => ( $regra_intencao . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
						'hierarchical' => 'top'
					];
					$rule[] = [
						'regex' => ( '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$' ),
						'rule' => ( $regra_intencao ),
						'hierarchical' => 'top'
					];
				}
			}
		}
		return $rule;
	}

	private function url_one( $taxonomy_mapping ) {
		$rule = [];
		foreach ( $taxonomy_mapping as $slug => $data ) {
			$rule[] = [
				'regex' => ( '^' . $slug . '/page/( [0-9]{1,3} )/?$' ),
				'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data['tax'], $data['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
				'hierarchical' => 'top'
			];
			$rule[] = [
				'regex' => ( '^' . $slug . '/?$' ),
				'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data['tax'], $data['slug'] ) ),
				'hierarchical' => 'top'
			];
		}
		return $rule;
	}

	private function url_two( $taxonomy_one, $taxonomy_two ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				if ( $data1['tax'] != $data2['tax'] ) {
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/page/( [0-9]{1,3} )/?$' ),
						'rule' => ( self::INDEX_CACHE . '&'. $this->tupla( $data1['tax'], $data1['slug'] ) . '&' . $this->tupla( $data2['tax'], $data2['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
						'hierarchical' => 'top'
					];
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/?$' ),
						'rule' => ( self::INDEX_CACHE . '&'. $this->tupla( $data1['tax'], $data1['slug'] ) . '&' . $this->tupla( $data2['tax'], $data2['slug'] ) ),
						'hierarchical' => 'top'
					];
				}
			}
		}
		return $rule;
	}

	private function tupla( $parameter, $value ) {
		return ( $parameter . '=' . $value );
	}

	private function url_tree( $taxonomy_one, $taxonomy_two, $taxonomy_tree ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				foreach ( $taxonomy_tree as $slug3 => $data3 ) {
					if ( $data1['tax'] != $data2['tax'] && $data1['tax'] != $data3['tax'] && $data2['tax'] != $data3['tax'] ) {
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/page/( [0-9]{1,3} )/?$' ),
							'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data1['tax'], $data1['slug'] ) . '&' . $this->tupla( $data2['tax'], $data2['slug'] ) . '&' . $this->tupla( $data3['tax'], $data3['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
							'hierarchical' => 'top'
						];
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/?$' ),
							'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data1['tax'], $data1['slug'] ) . '&' . $this->tupla( $data2['tax'], $data2['slug'] ) . '&' . $this->tupla( $data3['tax'], $data3['slug'] ) ),
							'hierarchical' => 'top'
						];
					}
				}
			}
		}
		return $rule;
	}

	private function url_four( $taxonomy_one, $taxonomy_two, $taxonomy_tree, $taxonomy_four ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				foreach ( $taxonomy_tree as $slug3 => $data3 ) {
					foreach ( $taxonomy_four as $slug4 => $data4 ) {
						if (
							$data1['tax'] !== $data2['tax'] && $data1['tax'] !== $data3['tax'] && $data1['tax'] !== $data4['tax'] &&
							$data2['tax'] !== $data3['tax'] && $data2['tax'] !== $data4['tax'] && $data3['tax'] !== $data4['tax']
						 ) {
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/page/( [0-9]{1,3} )/?$',
								'rule' => self::INDEX_CACHE . '&' . $data1['tax'] . '=' . $data1['slug'] . '&' . $data2['tax'] . '=' . $data2['slug'] . '&' . $data3['tax'] . '=' . $data3['slug'] . '&' . $data4['tax'] . '=' . $data4['slug'] . '&' . $this->tupla( 'paged', '$matches[1]' ),
								'hierarchical' => 'top'
							];
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/?$',
								'rule'  => self::INDEX_CACHE . '&' . $data1['tax'] . '=' . $data1['slug'] . '&' . $data2['tax'] . '=' . $data2['slug'] . '&' . $data3['tax'] . '=' . $data3['slug'] . '&' . $data4['tax'] . '=' . $data4['slug'],
								'hierarchical' => 'top'
							];
						}
					}
				}
			}
		}
		return $rule;
	}

	private function register( $rules ) {
		foreach ( $rules as $rule ) {
			add_rewrite_rule( $rule[ 'regex' ], $rule[ 'rule' ], $rule[ 'hierarchical' ] );
		}
	}

	private function cria( $tax_contrato, $tax_tipo_imovel, $tax_cidade ) {
		$rules = array_merge(
			$this->url_seo_landing_pages( $tax_contrato, $tax_tipo_imovel, $tax_cidade ),
			$this->url_one( $tax_contrato ),
			$this->url_one( $tax_tipo_imovel ),
			$this->url_one( $tax_cidade ),
			$this->url_two( $tax_contrato, $tax_tipo_imovel ),
			$this->url_two( $tax_contrato, $tax_cidade ),
			$this->url_two( $tax_tipo_imovel, $tax_contrato ),
			$this->url_two( $tax_tipo_imovel, $tax_cidade ),
			$this->url_two( $tax_cidade, $tax_contrato ),
			$this->url_two( $tax_cidade, $tax_tipo_imovel ),
			$this->url_tree( $tax_contrato, $tax_tipo_imovel, $tax_cidade ),
			$this->url_tree( $tax_contrato, $tax_cidade, $tax_tipo_imovel ),
			$this->url_tree( $tax_tipo_imovel, $tax_contrato, $tax_cidade ),
			$this->url_tree( $tax_tipo_imovel, $tax_cidade, $tax_contrato ),
			$this->url_tree( $tax_cidade, $tax_contrato, $tax_tipo_imovel ),
			$this->url_tree( $tax_cidade, $tax_tipo_imovel, $tax_contrato )
		 );
		return $rules;
	}

	public function clear( ) {
		delete_transient( self::CACHE_URL_AMIGAVEL );
		delete_transient( 'CACHE_MAPA_HIERARQUICO_V4' );
		// Removido o flush daqui para evitar salvar banco antes da hora
	}

	public function do( ) {
		$forcar_reset = isset( $_GET['forcar_reset'] );

		if ( $forcar_reset ) {
			$this->clear( );
		}

		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		$rules = false;

		if ( !$em_desenvolvimento ) {
			$rules = get_transient( self::CACHE_URL_AMIGAVEL );
		}

		if ( $rules === false ) {
			$tax_contrato = [];
			foreach ( lista_contratos( ) as $contrato ) {
				$tax_contrato[ sanitize_title( $contrato->name ) ] = ['tax' => 'contrato', 'slug' => $contrato->slug];
			}
			$tax_tipo_imovel = [];
			foreach ( lista_tipo_imovel( ) as $tipo_imovel ) {
				$tax_tipo_imovel[ sanitize_title( $tipo_imovel->name ) ] = ['tax' => 'tipo-imovel', 'slug' => $tipo_imovel->slug];
			}
			$tax_cidade = [];
			foreach ( lista_cidade( ) as $cidade ) {
				$tax_cidade[ sanitize_title( $cidade->name ) ] = ['tax' => 'cidade', 'slug' => $cidade->slug];
			}
			if ( empty( $tax_contrato ) || empty( $tax_tipo_imovel ) || empty( $tax_cidade ) ) {
				return;
			}
			$hierarquia = self::get_mapa_hierarquico( );

			$rules = array_merge(
				$this->cria( $tax_contrato, $tax_tipo_imovel, $tax_cidade ),
				$this->url_hierarquia_regras( $hierarquia['regioes_rules'], $tax_contrato, $tax_tipo_imovel )
			);

			if ( !$em_desenvolvimento ) {
				set_transient( self::CACHE_URL_AMIGAVEL, $rules, WEEK_IN_SECONDS );
			}
		}

		$this->register( $rules );

		// Flush CORRETO: Só salva no banco depois que as regras foram geradas e registradas!
		if ( $forcar_reset || $this->delete_cache === true ) {
			flush_rewrite_rules( false );
		}
	}

	public static function gerar_links_mapa_imoveis( ) {
		global $wpdb;

		$cache_key = 'CACHE_MAPA_IMOVEIS_LINKS';
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );

		if ( !$em_desenvolvimento ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$sql_matriz = "
			SELECT
				'venda' AS contrato,
				pm_tipo.meta_value AS tipo_imovel,
				pm_cidade.meta_value AS cidade,
				pm_regiao.meta_value AS regiao,
				COUNT( p.ID ) AS total_imoveis
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_status
				ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D'
			INNER JOIN {$wpdb->postmeta} pm_contrato
				ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarVenda' AND pm_contrato.meta_value = '1'
			LEFT JOIN {$wpdb->postmeta} pm_tipo
				ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome'
			LEFT JOIN {$wpdb->postmeta} pm_cidade
				ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade'
			LEFT JOIN {$wpdb->postmeta} pm_regiao
				ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao'
			WHERE p.post_type = 'imovel' AND p.post_status = 'publish'
			GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value

			UNION ALL

			SELECT
				'locacao' AS contrato,
				pm_tipo.meta_value AS tipo_imovel,
				pm_cidade.meta_value AS cidade,
				pm_regiao.meta_value AS regiao,
				COUNT( p.ID ) AS total_imoveis
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_status
				ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D'
			INNER JOIN {$wpdb->postmeta} pm_contrato
				ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarLocacao' AND pm_contrato.meta_value = '1'
			LEFT JOIN {$wpdb->postmeta} pm_tipo
				ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome'
			LEFT JOIN {$wpdb->postmeta} pm_cidade
				ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade'
			LEFT JOIN {$wpdb->postmeta} pm_regiao
				ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao'
			WHERE p.post_type = 'imovel' AND p.post_status = 'publish'
			GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value

			UNION ALL

			SELECT
				'lancamento' AS contrato,
				pm_tipo.meta_value AS tipo_imovel,
				pm_cidade.meta_value AS cidade,
				pm_regiao.meta_value AS regiao,
				COUNT( p.ID ) AS total_imoveis
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_status
				ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D'
			INNER JOIN {$wpdb->postmeta} pm_contrato
				ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarLancamento' AND pm_contrato.meta_value = '1'
			LEFT JOIN {$wpdb->postmeta} pm_tipo
				ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome'
			LEFT JOIN {$wpdb->postmeta} pm_cidade
				ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade'
			LEFT JOIN {$wpdb->postmeta} pm_regiao
				ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao'
			WHERE p.post_type = 'imovel' AND p.post_status = 'publish'
			GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value
			ORDER BY total_imoveis DESC;
		";

		$combinacoes_ativas = $wpdb->get_results( $sql_matriz, ARRAY_A );

		$links = [];

		if ( !empty( $combinacoes_ativas ) ) {

			$mapa_nomes_contrato = [
				'venda'      => 'Venda',
				'locacao'    => 'Locação',
				'lancamento' => 'Lançamento'
			];

			foreach ( $combinacoes_ativas as $linha ) {
				if ( empty( $linha['cidade'] ) || empty( $linha['tipo_imovel'] ) ) {
					continue;
				}
				$c_slug   = $linha['contrato'];
				$t_slug   = sanitize_title( $linha['tipo_imovel'] );
				$cid_slug = sanitize_title( $linha['cidade'] );
				$reg_slug = !empty( $linha['regiao'] ) ? sanitize_title( self::limpar_faxina_bairro( $linha['regiao'] ) ) : '';
				$partes_url = array_filter( [$c_slug, $t_slug, $cid_slug, $reg_slug] );
				$url = "/" . implode( "/", $partes_url ) . "/";

				$nome_contrato = $mapa_nomes_contrato[$c_slug];
				$nome_tipo     = mb_convert_case( strtolower( trim( $linha['tipo_imovel'] ) ), MB_CASE_TITLE, 'UTF-8' );
				$nome_cidade   = mb_convert_case( strtolower( trim( $linha['cidade'] ) ), MB_CASE_TITLE, 'UTF-8' );
				$nome_regiao   = !empty( $linha['regiao'] ) ? mb_convert_case( strtolower( trim( $linha['regiao'] ) ), MB_CASE_TITLE, 'UTF-8' ) : '';

				$label = "{$nome_contrato} de {$nome_tipo} em {$nome_cidade}";
				if ( $nome_regiao ) {
					$label .= " - {$nome_regiao}";
				}

				$links[] = [
					'url'           => $url,
					'label'         => $label,
					'cidade_slug'   => $cid_slug,
					'cidade_nome'   => $nome_cidade,
					'total_imoveis' => (int) $linha['total_imoveis']
				];
			}
		}
		if ( !$em_desenvolvimento ) {
			set_transient( $cache_key, $links, 12 * HOUR_IN_SECONDS );
		}
		return $links;
	}
}