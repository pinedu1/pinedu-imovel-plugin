<?php
/**
 * Class PrettyUrl
 * 
 * Gerencia o roteamento amigavel, NLP para buscas imobiliarias, conversao de slugs numericos MUMPS e resolucao de contexto SEO.
 * Utiliza o padrao de projeto Strategy ( Closures ) para derivacao dinamica de escopo de SEO e unificacao canonica.
 * 
 * @author Eduardo Pinheiro da Silva
 */
class PrettyUrl {
	const CACHE_URL_AMIGAVEL = 'CACHE_PRETTY_URL_V4';
	const CACHE_MAPA_HIERARQUICO = 'CACHE_MAPA_HIERARQUICO_V6';
	const CACHE_MAPA_IMOVEIS = 'CACHE_MAPA_IMOVEIS_TREE_V4';
	const INDEX_CACHE = 'index.php?post_type=imovel&tipo_pesquisa_submit=imovel';
	private $delete_cache;
	public function __construct( $delete_cache = false ) {
		$this->delete_cache = $delete_cache;
	}
	public function obter_parametro_consulta( $chave ) {
		$valor = get_query_var( $chave );
		if ( empty( $valor ) && isset( $_GET[ $chave ] ) ) {
			$valor = sanitize_text_field( wp_unslash( $_GET[ $chave ] ) );
		}
		if ( empty( $valor ) && is_tax( $chave ) ) {
			$obj = get_queried_object( );
			if ( $obj && isset( $obj->slug ) ) {
				$valor = $obj->slug;
			}
		}
		return is_string( $valor ) ? trim( $valor ) : '';
	}
	public static function limpar_prefixo_numerico( $nome ) {
		return trim( preg_replace( '/^[0-9]+[a-z]?\s*(?:-\s*)?/i', '', trim( (string) $nome ) ) );
	}
	public function resolver_contexto_seo( ) {
		$contrato_raw = strtolower( $this->obter_parametro_consulta( 'contrato' ) );
		$tipo_raw = strtolower( $this->obter_parametro_consulta( 'tipo-imovel' ) );
		$cidade_raw = $this->obter_parametro_consulta( 'cidade' );
		$regiao_raw = $this->obter_parametro_consulta( 'regiao' );
		$contrato_canonico = '';
		if ( ! empty( $contrato_raw ) ) {
			if ( $contrato_raw === '1' || $contrato_raw === 'venda' || $contrato_raw === 'vendas' ) {
				$contrato_canonico = 'venda';
			} elseif ( $contrato_raw === '2' || $contrato_raw === 'locacao' || $contrato_raw === 'aluguel' ) {
				$contrato_canonico = 'locacao';
			} elseif ( $contrato_raw === '3' || $contrato_raw === 'lancamento' ) {
				$contrato_canonico = 'lancamento';
			} else {
				$term_contrato = get_term_by( 'slug', $contrato_raw, 'contrato' );
				$nome_c = ( $term_contrato && ! is_wp_error( $term_contrato ) ) ? remove_accents( strtolower( $term_contrato->name ) ) : $contrato_raw;
				$mapa_contrato = self::get_mapa_contrato( );
				foreach ( $mapa_contrato as $canon => $sinonimos ) {
					if ( $nome_c === $canon || isset( $sinonimos[ $nome_c ] ) ) {
						$contrato_canonico = $canon;
						break;
					}
				}
			}
		}
		$tipo_slug_canonico = '';
		$tipo_nome_termo = '';
		if ( ! empty( $tipo_raw ) ) {
			$term_tipo = get_term_by( 'slug', $tipo_raw, 'tipo-imovel' );
			if ( $term_tipo && ! is_wp_error( $term_tipo ) ) {
				$tipo_nome_termo = $term_tipo->name;
				$tipo_busca = remove_accents( strtolower( trim( $term_tipo->name ) ) );
			} else {
				$tipo_busca = str_replace( '-', ' ', remove_accents( $tipo_raw ) );
			}
			$mapa_tipo = self::get_mapa_tipo_imovel( );
			foreach ( $mapa_tipo as $canon => $sinonimos ) {
				$canon_espaco = str_replace( '-', ' ', $canon );
				if ( $tipo_busca === $canon || $tipo_busca === $canon_espaco || isset( $sinonimos[ $tipo_busca ] ) ) {
					$tipo_slug_canonico = $canon;
					break;
				}
			}
			if ( empty( $tipo_slug_canonico ) && ! empty( $tipo_nome_termo ) ) {
				$tipo_slug_canonico = sanitize_title( $tipo_nome_termo );
			}
		}
		$mapa_labels_tipo = [
			'casa' => [ 'singular' => 'Casa', 'plural' => 'Casas' ]
			, 'apartamento' => [ 'singular' => 'Apartamento', 'plural' => 'Apartamentos' ]
			, 'imovel-comercial' => [ 'singular' => 'Imóvel Comercial', 'plural' => 'Imóveis Comerciais' ]
			, 'terreno-ou-area' => [ 'singular' => 'Terreno ou Área', 'plural' => 'Terrenos e Áreas' ]
			, 'rural' => [ 'singular' => 'Imóvel Rural', 'plural' => 'Imóveis Rurais' ]
			, 'lazer' => [ 'singular' => 'Área de Lazer', 'plural' => 'Imóveis de Lazer' ]
		];
		$tipo_singular = 'Imóvel';
		$tipo_plural = 'Imóveis';
		if ( ! empty( $tipo_slug_canonico ) ) {
			if ( isset( $mapa_labels_tipo[ $tipo_slug_canonico ] ) ) {
				$tipo_singular = $mapa_labels_tipo[ $tipo_slug_canonico ][ 'singular' ];
				$tipo_plural = $mapa_labels_tipo[ $tipo_slug_canonico ][ 'plural' ];
			} else {
				$base_label = ! empty( $tipo_nome_termo ) ? $tipo_nome_termo : str_replace( '-', ' ', $tipo_slug_canonico );
				$tipo_singular = mb_convert_case( $base_label, MB_CASE_TITLE, 'UTF-8' );
				$tipo_plural = $tipo_singular . 's';
			}
		}
		$cidade_nome = '';
		$cidade_slug_url = '';
		if ( ! empty( $cidade_raw ) ) {
			$term_cidade = get_term_by( 'slug', $cidade_raw, 'cidade' );
			if ( $term_cidade && ! is_wp_error( $term_cidade ) ) {
				$cidade_nome = mb_convert_case( trim( $term_cidade->name ), MB_CASE_TITLE, 'UTF-8' );
				$cidade_slug_url = sanitize_title( $term_cidade->name );
			} else {
				$hierarquia = self::get_mapa_hierarquico( );
				$chave_cid = remove_accents( strtolower( str_replace( '-', ' ', $cidade_raw ) ) );
				if ( isset( $hierarquia[ 'cidades_nlp' ][ $chave_cid ] ) ) {
					$cidade_nome = mb_convert_case( $hierarquia[ 'cidades_nlp' ][ $chave_cid ][ 'nome' ], MB_CASE_TITLE, 'UTF-8' );
					$cidade_slug_url = $hierarquia[ 'cidades_nlp' ][ $chave_cid ][ 'slug_url' ];
				}
			}
		}
		$regiao_nome = '';
		$regiao_slug_url = '';
		if ( ! empty( $regiao_raw ) ) {
			$term_regiao = get_term_by( 'slug', $regiao_raw, 'regiao' );
			if ( $term_regiao && ! is_wp_error( $term_regiao ) ) {
				$reg_limpa = self::limpar_prefixo_numerico( $term_regiao->name );
				$regiao_nome = mb_convert_case( $reg_limpa, MB_CASE_TITLE, 'UTF-8' );
				$regiao_slug_url = sanitize_title( $reg_limpa );
				if ( empty( $cidade_nome ) ) {
					$parent_cid_slug = get_term_meta( $term_regiao->term_id, 'parent_id', true );
					if ( ! empty( $parent_cid_slug ) ) {
						$term_cid_pai = get_term_by( 'slug', $parent_cid_slug, 'cidade' );
						if ( $term_cid_pai && ! is_wp_error( $term_cid_pai ) ) {
							$cidade_nome = mb_convert_case( trim( $term_cid_pai->name ), MB_CASE_TITLE, 'UTF-8' );
							$cidade_slug_url = sanitize_title( $term_cid_pai->name );
						}
					}
				}
			}
		}
		$val_inicial = isset( $_GET[ 'valor-inicial' ] ) ? (float) $_GET[ 'valor-inicial' ] : 0.0;
		$val_final = isset( $_GET[ 'valor-final' ] ) ? (float) $_GET[ 'valor-final' ] : 0.0;
		if ( $val_inicial > 0 && $val_final > 0 && $val_inicial > $val_final ) {
			$temp = $val_inicial;
			$val_inicial = $val_final;
			$val_final = $temp;
		}
		$faixa_preco = '';
		if ( $val_inicial > 0 || $val_final > 0 ) {
			$str_ini = 'R$ ' . number_format( $val_inicial, 2, ',', '.' );
			$str_fim = 'R$ ' . number_format( $val_final, 2, ',', '.' );
			if ( $val_inicial > 0 && $val_final > 0 ) {
				$faixa_preco = "de {$str_ini} até {$str_fim}";
			} elseif ( $val_inicial > 0 ) {
				$faixa_preco = "a partir de {$str_ini}";
			} else {
				$faixa_preco = "até {$str_fim}";
			}
		}
		$partes_canonicas = [];
		if ( ! empty( $contrato_canonico ) ) {
			$partes_canonicas[] = $contrato_canonico;
		}
		if ( ! empty( $tipo_slug_canonico ) ) {
			$partes_canonicas[] = $tipo_slug_canonico;
		}
		if ( ! empty( $cidade_slug_url ) ) {
			$partes_canonicas[] = $cidade_slug_url;
		}
		if ( ! empty( $regiao_slug_url ) ) {
			$partes_canonicas[] = $regiao_slug_url;
		}
		$paged = max( 1, intval( get_query_var( 'paged' ) ) );
		$canonical_url = '';
		if ( ! empty( $partes_canonicas ) ) {
			$path_canon = '/' . implode( '/', $partes_canonicas ) . '/';
			if ( $paged > 1 ) {
				$path_canon .= 'page/' . $paged . '/';
			}
			$canonical_url = home_url( $path_canon );
		} else {
			$req_uri = isset( $_SERVER[ 'REQUEST_URI' ] ) ? strtok( $_SERVER[ 'REQUEST_URI' ], '?' ) : '/';
			$canonical_url = home_url( $req_uri );
		}
		return [
			'contrato_canonico' => $contrato_canonico
			, 'tipo_slug' => $tipo_slug_canonico
			, 'tipo_singular' => $tipo_singular
			, 'tipo_plural' => $tipo_plural
			, 'cidade_nome' => $cidade_nome
			, 'cidade_slug_url' => $cidade_slug_url
			, 'regiao_nome' => $regiao_nome
			, 'regiao_slug_url' => $regiao_slug_url
			, 'faixa_preco' => $faixa_preco
			, 'paged' => $paged
			, 'canonical_url' => $canonical_url
		];
	}
	public function obter_dados_seo_ativos( ) {
		if ( is_singular( ) ) {
			return null;
		}
		$referencia = $this->obter_parametro_consulta( 'referencia' );
		$tipo_pesquisa = $this->obter_parametro_consulta( 'tipo_pesquisa_submit' );
		$is_tax_imovel = is_tax( [ 'contrato', 'tipo-imovel', 'cidade', 'regiao' ] );
		if ( empty( $referencia ) && $tipo_pesquisa !== 'imovel' && ! $is_tax_imovel ) {
			return null;
		}
		$site_name = get_bloginfo( 'name' );
		$seo_strategies = [
			'referencia' => function( ) use ( $referencia, $site_name ) {
				$ref_safe = esc_html( $referencia );
				$req_uri = isset( $_SERVER[ 'REQUEST_URI' ] ) ? strtok( $_SERVER[ 'REQUEST_URI' ], '?' ) : '/';
				return [
					'title' => "Imóvel Referência {$ref_safe} em Birigui e Região | {$site_name}"
					, 'description' => "Veja os detalhes, fotos, valor e localização do imóvel referência {$ref_safe} na {$site_name}."
					, 'canonical' => home_url( $req_uri )
					, 'h1' => "Imóvel Referência {$ref_safe}"
					, 'subtitulo' => 'Detalhes e Localização'
					, 'descricao_busca' => "Confira todos os detalhes do imóvel referência {$ref_safe} na {$site_name}."
				];
			}
			, 'imovel' => function( ) use ( $site_name ) {
				$ctx = $this->resolver_contexto_seo( );
				$local_titulo = ! empty( $ctx[ 'cidade_nome' ] ) ? 'em ' . $ctx[ 'cidade_nome' ] : 'em Birigui e Região';
				if ( ! empty( $ctx[ 'regiao_nome' ] ) ) {
					$local_titulo .= ' - ' . $ctx[ 'regiao_nome' ];
				}
				$local_desc = ! empty( $ctx[ 'cidade_nome' ] ) ? 'em ' . $ctx[ 'cidade_nome' ] : 'em Birigui e região';
				if ( ! empty( $ctx[ 'regiao_nome' ] ) ) {
					$local_desc .= ' (' . $ctx[ 'regiao_nome' ] . ')';
				}
				$t_sing = $ctx[ 'tipo_singular' ];
				$t_plur = $ctx[ 'tipo_plural' ];
				$t_sing_lower = mb_convert_case( $t_sing, MB_CASE_LOWER, 'UTF-8' );
				$t_plur_lower = mb_convert_case( $t_plur, MB_CASE_LOWER, 'UTF-8' );
				$compl_preco = ! empty( $ctx[ 'faixa_preco' ] ) ? ' ' . $ctx[ 'faixa_preco' ] : '';
				if ( $ctx[ 'contrato_canonico' ] === 'venda' ) {
					$title_seo = "{$t_plur} à Venda {$local_titulo}{$compl_preco} - Comprar {$t_sing} | {$site_name}";
					$description_seo = "Procurando {$t_sing_lower} à venda {$local_desc}{$compl_preco}? Confira as melhores opções para compra e venda de {$t_plur_lower} {$local_desc} com segurança na {$site_name}.";
					$h1_seo = "{$t_plur} à Venda {$local_titulo}";
					$desc_busca_seo = "Confira as melhores opções de {$t_plur_lower} à venda {$local_desc}. Encontre o imóvel perfeito na {$site_name}.";
				} elseif ( $ctx[ 'contrato_canonico' ] === 'locacao' ) {
					$title_seo = "{$t_plur} para Alugar {$local_titulo}{$compl_preco} - Aluguel e Locação de {$t_sing} | {$site_name}";
					$description_seo = "Procurando {$t_sing_lower} para aluguel ou locação {$local_desc}{$compl_preco}? Confira as melhores opções de {$t_plur_lower} para alugar {$local_desc} na {$site_name}.";
					$h1_seo = "{$t_plur} para Alugar {$local_titulo}";
					$desc_busca_seo = "Confira as melhores opções de {$t_plur_lower} para alugar {$local_desc}. Encontre o imóvel perfeito na {$site_name}.";
				} elseif ( $ctx[ 'contrato_canonico' ] === 'lancamento' ) {
					$title_seo = "Lançamentos de {$t_plur} {$local_titulo}{$compl_preco} - Comprar {$t_sing} | {$site_name}";
					$description_seo = "Confira os melhores lançamentos de {$t_plur_lower} {$local_desc}{$compl_preco}. Oportunidades exclusivas para compra e investimento na {$site_name}.";
					$h1_seo = "Lançamentos de {$t_plur} {$local_titulo}";
					$desc_busca_seo = "Confira os melhores lançamentos de {$t_plur_lower} {$local_desc}. Oportunidades exclusivas na {$site_name}.";
				} else {
					$title_seo = "{$t_plur} à Venda e para Alugar {$local_titulo}{$compl_preco} - Compra e Locação | {$site_name}";
					$description_seo = "Confira as melhores opções de {$t_plur_lower} à venda (compra) e para alugar (aluguel e locação) {$local_desc}{$compl_preco} na {$site_name}.";
					$h1_seo = "{$t_plur} à Venda e para Alugar {$local_titulo}";
					$desc_busca_seo = "Confira as melhores opções de {$t_plur_lower} à venda e para alugar {$local_desc} na {$site_name}.";
				}
				if ( $ctx[ 'paged' ] > 1 ) {
					$title_seo = "Página {$ctx['paged']} - " . $title_seo;
				}
				return [
					'title' => $title_seo
					, 'description' => $description_seo
					, 'canonical' => $ctx[ 'canonical_url' ]
					, 'h1' => $h1_seo
					, 'subtitulo' => 'Encontre o imóvel ideal para você'
					, 'descricao_busca' => $desc_busca_seo
				];
			}
		];
		$estrategia_ativa = ! empty( $referencia ) ? 'referencia' : 'imovel';
		return $seo_strategies[ $estrategia_ativa ]( );
	}
	public function reescrever_term_link( $termlink, $term, $taxonomy ) {
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return $termlink;
		}
		if ( $taxonomy === 'cidade' ) {
			return home_url( '/' . sanitize_title( $term->name ) . '/' );
		}
		if ( $taxonomy === 'regiao' ) {
			$reg_slug = sanitize_title( self::limpar_prefixo_numerico( $term->name ) );
			$parent_cid_slug = get_term_meta( $term->term_id, 'parent_id', true );
			if ( ! empty( $parent_cid_slug ) ) {
				$term_cid = get_term_by( 'slug', $parent_cid_slug, 'cidade' );
				if ( $term_cid && ! is_wp_error( $term_cid ) ) {
					$cid_slug = sanitize_title( $term_cid->name );
					return home_url( '/' . $cid_slug . '/' . $reg_slug . '/' );
				}
			}
			return home_url( '/' . $reg_slug . '/' );
		}
		if ( $taxonomy === 'tipo-imovel' ) {
			return home_url( '/' . sanitize_title( $term->name ) . '/' );
		}
		if ( $taxonomy === 'contrato' ) {
			return home_url( '/' . sanitize_title( $term->name ) . '/' );
		}
		return $termlink;
	}
	public function liberar_query_vars_consulta( $query_vars ) {
		$query_vars[] = 'referencia';
		$query_vars[] = 'tipo_pesquisa_submit';
		$query_vars[] = 'contrato';
		$query_vars[] = 'tipo-imovel';
		$query_vars[] = 'cidade';
		$query_vars[] = 'regiao';
		return $query_vars;
	}
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
				, 'casa térrea'
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
	public static function limpar_faxina_bairro( $nome ) {
		$nome = remove_accents( strtolower( trim( (string) $nome ) ) );
		$nome = preg_replace( '/^[0-9]+[a-z]?\s*(?:-\s*)?/i', '', $nome );
		$nome = str_replace( '.', ' ', $nome );
		$ruidos = self::get_ruidos( );
		foreach ( $ruidos as $ruido ) {
			$nome = preg_replace( '/\b' . $ruido . '\b/u', ' ', $nome );
		}
		return trim( preg_replace( '/\s+/', ' ', $nome ) );
	}
	public static function get_mapa_hierarquico( ) {
		global $wpdb;
		$cache_key = self::CACHE_MAPA_HIERARQUICO;
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		$dados = false;
		if ( ! $em_desenvolvimento ) {
			$dados = get_transient( $cache_key );
		}
		if ( $dados !== false ) {
			return $dados;
		}
		$query = "SELECT c.name AS cidade_nome, c.slug AS cidade_slug, r.name AS regiao_nome, r.slug AS regiao_slug, b.name AS bairro_nome, b.slug AS bairro_slug FROM {$wpdb->terms} c INNER JOIN {$wpdb->term_taxonomy} tt_c ON c.term_id = tt_c.term_id AND tt_c.taxonomy = 'cidade' LEFT JOIN {$wpdb->termmeta} tm_r ON tm_r.meta_key = 'parent_id' AND tm_r.meta_value = c.slug LEFT JOIN {$wpdb->terms} r ON r.term_id = tm_r.term_id LEFT JOIN {$wpdb->term_taxonomy} tt_r ON r.term_id = tt_r.term_id AND tt_r.taxonomy = 'regiao' LEFT JOIN {$wpdb->termmeta} tm_b ON tm_b.meta_key = 'parent_id' AND tm_b.meta_value = r.slug LEFT JOIN {$wpdb->terms} b ON b.term_id = tm_b.term_id LEFT JOIN {$wpdb->term_taxonomy} tt_b ON b.term_id = tt_b.term_id AND tt_b.taxonomy = 'bairro' WHERE c.slug IS NOT NULL";
		$resultados = $wpdb->get_results( $query );
		$cidades_nlp = [];
		$regioes_nlp = [];
		$bairros_nlp = [];
		$regioes_rules = [];
		foreach ( $resultados as $row ) {
			$cidade_slug_url = sanitize_title( $row->cidade_nome );
			$cidade_nome_limpo = remove_accents( strtolower( trim( $row->cidade_nome ) ) );
			$cidades_nlp[ $cidade_nome_limpo ] = [ 'slug' => $row->cidade_slug, 'nome' => $row->cidade_nome, 'slug_url' => $cidade_slug_url ];
			if ( ! empty( $row->regiao_slug ) && ! empty( $row->regiao_nome ) ) {
				$regiao_sem_prefixo = self::limpar_prefixo_numerico( $row->regiao_nome );
				$regiao_slug_url = sanitize_title( $regiao_sem_prefixo );
				$regiao_nome_limpo = self::limpar_faxina_bairro( $row->regiao_nome );
				if ( ! empty( $regiao_nome_limpo ) ) {
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
				}
				if ( ! empty( $regiao_slug_url ) ) {
					$path_regiao = $cidade_slug_url . '/' . $regiao_slug_url;
					$regioes_rules[ $path_regiao ] = [ 'cidade' => $row->cidade_slug, 'regiao' => $row->regiao_slug ];
				}
				if ( ! empty( $row->bairro_slug ) && ! empty( $row->bairro_nome ) ) {
					$bairro_sem_prefixo = self::limpar_prefixo_numerico( $row->bairro_nome );
					$bairro_slug_url = sanitize_title( $bairro_sem_prefixo );
					$bairro_nome_limpo = self::limpar_faxina_bairro( $row->bairro_nome );
					if ( ! empty( $bairro_nome_limpo ) ) {
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
					}
				}
			}
		}
		$dados = [ 'cidades_nlp' => $cidades_nlp, 'regioes_nlp' => $regioes_nlp, 'bairros_nlp' => $bairros_nlp, 'regioes_rules' => $regioes_rules ];
		if ( ! $em_desenvolvimento ) {
			set_transient( $cache_key, $dados, 10 * MINUTE_IN_SECONDS );
		}
		return $dados;
	}
	public function redirecionar_acentos( ) {
		$uri = $_SERVER[ 'REQUEST_URI' ];
		$parsed = parse_url( $uri );
		$path = isset( $parsed[ 'path' ] ) ? ltrim( $parsed[ 'path' ], '/' ) : '';
		$path_digitado = urldecode( $path );
		$path_digitado_clean = trim( rtrim( $path_digitado, '/' ) );
		if ( preg_match( '/^vendas(\/.*)?$/i', $path_digitado_clean, $vendas_matches ) ) {
			$novo_p = 'venda' . ( $vendas_matches[ 1 ] ?? '' );
			$redirect_url = home_url( '/' . $novo_p . '/' );
			if ( ! empty( $parsed[ 'query' ] ) ) {
				$redirect_url .= '?' . $parsed[ 'query' ];
			}
			wp_redirect( $redirect_url, 301 );
			exit;
		}
		if ( preg_match( '/^(cidade|regiao|tipo-imovel|contrato)\/([^\/]+)(?:\/page\/([0-9]{1,3}))?$/i', $path_digitado_clean, $tax_matches ) ) {
			$tax_nome = strtolower( $tax_matches[ 1 ] );
			$term_slug = $tax_matches[ 2 ];
			$pag_num = isset( $tax_matches[ 3 ] ) ? intval( $tax_matches[ 3 ] ) : 1;
			$term_obj = get_term_by( 'slug', $term_slug, $tax_nome );
			if ( $term_obj && ! is_wp_error( $term_obj ) ) {
				$pretty_link = $this->reescrever_term_link( '', $term_obj, $tax_nome );
				if ( ! empty( $pretty_link ) ) {
					if ( $pag_num > 1 ) {
						$pretty_link = trailingslashit( $pretty_link ) . 'page/' . $pag_num . '/';
					}
					if ( ! empty( $parsed[ 'query' ] ) ) {
						parse_str( $parsed[ 'query' ], $query_params );
						$chaves_ignoradas = [ 'tipo_pesquisa_submit', 'post_type', 'contrato', 'tipo-imovel', 'cidade', 'regiao', 'paged' ];
						$query_limpa = [];
						foreach ( $query_params as $key => $val ) {
							if ( ! in_array( $key, $chaves_ignoradas ) && $val !== '' && $val !== '0' && $val !== 0 ) {
								$query_limpa[ $key ] = $val;
							}
						}
						if ( ! empty( $query_limpa ) ) {
							$pretty_link .= '?' . http_build_query( $query_limpa );
						}
					}
					wp_redirect( $pretty_link, 301 );
					exit;
				}
			}
		}
		if ( preg_match( '/(?:ref|referencia)[\-\/]([0-9]+)/i', $path_digitado_clean, $matches ) ) {
			$referencia_alvo = $matches[ 1 ];
			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'referencia' AND meta_value = %s LIMIT 1", $referencia_alvo ) );
			if ( $post_id ) {
				$novo_link = get_permalink( $post_id );
				$path_atual = rtrim( parse_url( $uri, PHP_URL_PATH ), '/' );
				$path_alvo = rtrim( parse_url( $novo_link, PHP_URL_PATH ), '/' );
				if ( $path_alvo !== $path_atual ) {
					wp_redirect( $novo_link, 301 );
					exit;
				}
			} else {
				if ( is_404( ) ) {
					return;
				}
			}
		}
		if ( is_404( ) ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
			$clean_path = remove_accents( strtolower( $path_digitado ) );
			$search_string = preg_replace( '/[\-\_\/\#\t\.]+/u', ' ', $clean_path );
			$ruidos = self::get_ruidos( );
			foreach ( $ruidos as $ruido ) {
				$search_string = preg_replace( '/\b' . $ruido . '\b/u', ' ', $search_string );
			}
			$search_string = preg_replace( '/\s+/', ' ', trim( $search_string ) );
			$string_inicial_debug = $search_string;
			$hierarquia = self::get_mapa_hierarquico( );
			$options = get_option( 'pinedu_imovel_options', [] );
			$cidade_padrao_slug = $options[ 'cidade' ] ?? '0001';
			$contrato_encontrado = $this->extrair_e_limpar_termo( $search_string, self::get_mapa_contrato( ) );
			$tipo_encontrado = $this->extrair_e_limpar_termo( $search_string, self::get_mapa_tipo_imovel( ) );
			$cidade_explicita_slug = null;
			$cidade_explicita_nome = null;
			$cidade_match_pattern = null;
			foreach ( $hierarquia[ 'cidades_nlp' ] as $sinonimo => $data ) {
				$pattern = '/\b' . preg_quote( $sinonimo, '/' ) . '\b/u';
				if ( preg_match( $pattern, $search_string ) ) {
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
			usort( $chaves_bairros, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
			foreach ( $chaves_bairros as $bairro_limpo ) {
				if ( empty( $bairro_limpo ) ) continue;
				$bairro_valido = null;
				if ( isset( $hierarquia[ 'bairros_nlp' ][ $bairro_limpo ][ $cidade_alvo ] ) ) {
					$bairro_valido = $hierarquia[ 'bairros_nlp' ][ $bairro_limpo ][ $cidade_alvo ];
				} elseif ( ! $cidade_explicita_slug ) {
					$bairro_valido = reset( $hierarquia[ 'bairros_nlp' ][ $bairro_limpo ] );
				}
				if ( ! $bairro_valido ) continue;
				$pattern = '/\b' . preg_quote( $bairro_limpo, '/' ) . '\b/u';
				if ( preg_match( $pattern, $search_string ) ) {
					$search_string = preg_replace( $pattern, ' ', $search_string );
					$bairro_encontrado_nome = $bairro_valido[ 'bairro_nome' ];
					$regiao_encontrada_slug_url = $bairro_valido[ 'regiao_slug_url' ];
					$cidade_final_slug_url = $bairro_valido[ 'cidade_slug_url' ];
					$cidade_final_nome = $bairro_valido[ 'cidade_nome' ];
					$pat_reg = '/\b' . preg_quote( self::limpar_faxina_bairro( $bairro_valido[ 'regiao_nome' ] ), '/' ) . '\b/u';
					$search_string = preg_replace( $pat_reg, ' ', $search_string );
					break;
				}
			}
			if ( ! $bairro_encontrado_nome ) {
				$chaves_regioes = array_keys( $hierarquia[ 'regioes_nlp' ] );
				usort( $chaves_regioes, function( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
				foreach ( $chaves_regioes as $regiao_limpa ) {
					if ( empty( $regiao_limpa ) ) continue;
					$regiao_valida = null;
					if ( isset( $hierarquia[ 'regioes_nlp' ][ $regiao_limpa ][ $cidade_alvo ] ) ) {
						$regiao_valida = $hierarquia[ 'regioes_nlp' ][ $regiao_limpa ][ $cidade_alvo ];
					} elseif ( ! $cidade_explicita_slug ) {
						$regiao_valida = reset( $hierarquia[ 'regioes_nlp' ][ $regiao_limpa ] );
					}
					if ( ! $regiao_valida ) continue;
					$pattern = '/\b' . preg_quote( $regiao_limpa, '/' ) . '\b/u';
					if ( preg_match( $pattern, $search_string ) ) {
						$search_string = preg_replace( $pattern, ' ', $search_string );
						$regiao_encontrada_slug_url = $regiao_valida[ 'regiao_slug_url' ];
						$cidade_final_slug_url = $regiao_valida[ 'cidade_slug_url' ];
						$cidade_final_nome = $regiao_valida[ 'cidade_nome' ];
						break;
					}
				}
			}
			if ( $cidade_match_pattern ) {
				$search_string = preg_replace( $cidade_match_pattern, ' ', $search_string );
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
			$nova_rota = implode( '/', $partes );
			$search_string = preg_replace( '/\s+/', ' ', trim( $search_string ) );
			if ( ! empty( $search_string ) ) {
				$sobras = str_replace( ' ', '-', $search_string );
				$nova_rota .= empty( $nova_rota ) ? $sobras : '/' . $sobras;
			}
			$nova_rota_clean = trim( $nova_rota, '/' );
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
				wp_die( '<pre style="' . $css_fullscreen . '">' . print_r( $relatorio, true ) . '</pre>', 'Painel de Debug NLP' );
			}
			if ( $nova_rota_clean !== $path_digitado_clean && ! empty( $nova_rota_clean ) ) {
				$new_url = home_url( '/' . $nova_rota_clean . '/' );
				if ( ! empty( $parsed[ 'query' ] ) ) {
					parse_str( $parsed[ 'query' ], $query_params );
					$chaves_ignoradas = [ 'tipo_pesquisa_submit', 'post_type', 'contrato', 'tipo-imovel', 'cidade', 'regiao', 'paged' ];
					$query_limpa = [];
					foreach ( $query_params as $key => $val ) {
						if ( ! in_array( $key, $chaves_ignoradas ) && $val !== '' && $val !== '0' && $val !== 0 ) {
							$query_limpa[ $key ] = $val;
						}
					}
					if ( ! empty( $query_limpa ) ) {
						$new_url .= '?' . http_build_query( $query_limpa );
					}
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
				$sinonimos_achatados[ remove_accents( strtolower( $sinonimo ) ) ] = $canonical;
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
	public static function gerar_links_from_search( $params ) {
		$links = [];
		$partes_url = [];
		$nomes = [];
		if ( ! empty( $params[ 'contrato' ] ) ) {
			$val = $params[ 'contrato' ];
			$term = get_term_by( 'slug', $val, 'contrato' );
			if ( $val == '1' || ( $term && strtolower( $term->name ) == 'venda' ) ) {
				$c_slug = 'venda';
				$nomes[ 'contrato' ] = 'Venda';
			} elseif ( $val == '2' || ( $term && strtolower( $term->name ) == 'locacao' ) ) {
				$c_slug = 'locacao';
				$nomes[ 'contrato' ] = 'Locação';
			} elseif ( $term && ! is_wp_error( $term ) ) {
				$c_slug = sanitize_title( $term->name );
				$nomes[ 'contrato' ] = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
			}
			if ( isset( $c_slug ) ) {
				$partes_url[] = $c_slug;
				$links[] = [ 'url' => '/' . implode( '/', $partes_url ) . '/', 'label' => $nomes[ 'contrato' ] ];
			}
		}
		if ( ! empty( $params[ 'tipo-imovel' ] ) ) {
			$term = get_term_by( 'slug', $params[ 'tipo-imovel' ], 'tipo-imovel' );
			if ( $term && ! is_wp_error( $term ) ) {
				$t_slug = sanitize_title( $term->name );
				$nomes[ 'tipo' ] = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
				$partes_url[] = $t_slug;
				$links[] = [ 'url' => '/' . implode( '/', $partes_url ) . '/', 'label' => $nomes[ 'tipo' ] ];
			}
		}
		if ( ! empty( $params[ 'cidade' ] ) ) {
			$term = get_term_by( 'slug', $params[ 'cidade' ], 'cidade' );
			if ( $term && ! is_wp_error( $term ) ) {
				$cid_slug = sanitize_title( $term->name );
				$nomes[ 'cidade' ] = mb_convert_case( $term->name, MB_CASE_TITLE, 'UTF-8' );
				$partes_url[] = $cid_slug;
				$links[] = [ 'url' => '/' . implode( '/', $partes_url ) . '/', 'label' => $nomes[ 'cidade' ] ];
			}
		}
		if ( ! empty( $params[ 'regiao' ] ) ) {
			$term = get_term_by( 'slug', $params[ 'regiao' ], 'regiao' );
			if ( $term && ! is_wp_error( $term ) ) {
				$reg_nome = self::limpar_prefixo_numerico( $term->name );
				$reg_slug = sanitize_title( $reg_nome );
				$nomes[ 'regiao' ] = mb_convert_case( $reg_nome, MB_CASE_TITLE, 'UTF-8' );
				$partes_url[] = $reg_slug;
				$links[] = [ 'url' => '/' . implode( '/', $partes_url ) . '/', 'label' => $nomes[ 'regiao' ] ];
			}
		}
		return $links;
	}
	public static function gerar_links_from_post( $post ) {
		if ( ! $post || $post->post_type !== 'imovel' ) return [];
		$cidade_slug = sanitize_title( $post->cidade );
		$regiao_slug = sanitize_title( self::limpar_prefixo_numerico( $post->regiao ) );
		$tipo_slug = sanitize_title( $post->tipoImovelNome );
		$links = [];
		$finalidades = [];
		if ( ( 1 == intval( $post->ativarVenda ) ) || ( 1 == intval( $post->ativarLancamento ) ) ) $finalidades[] = 'venda';
		if ( ( 1 == intval( $post->ativarLocacao ) ) ) $finalidae[] = 'locacao';
		foreach ( $finalidades as $contrato ) {
			$lbl_base = ( $contrato == 'venda' ? 'Venda' : 'Locação' );
			$links[] = [ 'url' => "/{$contrato}/", 'label' => $lbl_base ];
			if ( ! empty( $tipo_slug ) ) {
				$raw_tipo = $post->tipoImovelNome ?? str_replace( '-', ' ', $tipo_slug );
				$nome_tipo = mb_convert_case( $raw_tipo, MB_CASE_TITLE, 'UTF-8' );
				$links[] = [ 'url' => "/{$contrato}/{$tipo_slug}/", 'label' => "{$lbl_base} - {$nome_tipo}" ];
				if ( ! empty( $cidade_slug ) ) {
					$raw_cidade = $post->cidade ?? str_replace( '-', ' ', $cidade_slug );
					$nome_cidade = mb_convert_case( $raw_cidade, MB_CASE_TITLE, 'UTF-8' );
					$acao = ( $contrato == 'venda' ? 'Venda' : 'Locação' );
					$links[] = [ 'url' => "/{$contrato}/{$tipo_slug}/{$cidade_slug}/", 'label' => "{$nome_tipo} à {$acao} em {$nome_cidade}" ];
					if ( ! empty( $regiao_slug ) ) {
						$raw_regiao = ! empty( $post->regiao ) ? self::limpar_prefixo_numerico( $post->regiao ) : str_replace( '-', ' ', $regiao_slug );
						$nome_regiao = mb_convert_case( $raw_regiao, MB_CASE_TITLE, 'UTF-8' );
						$links[] = [ 'url' => "/{$contrato}/{$tipo_slug}/{$cidade_slug}/{$regiao_slug}/", 'label' => "{$nome_tipo} à {$acao} em {$nome_cidade} - {$nome_regiao}" ];
					}
				}
			}
		}
		return $links;
	}
	private function url_hierarquia_regras( $hierarquia_rules, $tax_contrato, $tax_tipo_imovel ) {
		$rules = [];
		foreach ( $hierarquia_rules as $path => $data ) {
			$base_rule = self::INDEX_CACHE . '&cidade=' . $data[ 'cidade' ] . '&regiao=' . $data[ 'regiao' ];
			$rules[] = [ 'regex' => '^' . $path . '/page/([0-9]{1,3})/?$', 'rule' => $base_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
			$rules[] = [ 'regex' => '^' . $path . '/?$', 'rule' => $base_rule, 'hierarchical' => 'top' ];
			foreach ( $tax_tipo_imovel as $nome_tipo => $t_data ) {
				$t_path = $nome_tipo . '/' . $path;
				$t_rule = $base_rule . '&tipo-imovel=' . $t_data[ 'slug' ];
				$rules[] = [ 'regex' => '^' . $t_path . '/page/([0-9]{1,3})/?$', 'rule' => $t_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
				$rules[] = [ 'regex' => '^' . $t_path . '/?$', 'rule' => $t_rule, 'hierarchical' => 'top' ];
			}
			foreach ( $tax_contrato as $nome_contrato => $c_data ) {
				$c_path = $nome_contrato . '/' . $path;
				$c_rule = $base_rule . '&contrato=' . $c_data[ 'slug' ];
				$rules[] = [ 'regex' => '^' . $c_path . '/page/([0-9]{1,3})/?$', 'rule' => $c_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
				$rules[] = [ 'regex' => '^' . $c_path . '/?$', 'rule' => $c_rule, 'hierarchical' => 'top' ];
				foreach ( $tax_tipo_imovel as $nome_tipo => $t_data ) {
					$ct_path = $nome_contrato . '/' . $nome_tipo . '/' . $path;
					$ct_rule = $c_rule . '&tipo-imovel=' . $t_data[ 'slug' ];
					$rules[] = [ 'regex' => '^' . $ct_path . '/page/([0-9]{1,3})/?$', 'rule' => $ct_rule . '&paged=$matches[1]', 'hierarchical' => 'top' ];
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
			'venda' => $chave_venda
			, 'comprar' => $chave_venda
			, 'compra' => $chave_venda
			, 'vender' => $chave_venda
			, 'aluguel' => $chave_locacao
			, 'alugar' => $chave_locacao
			, 'locar' => $chave_locacao
			, 'locação' => $chave_locacao
			, 'locacao' => $chave_locacao
			, 'arrendar' => $chave_locacao
		];
		foreach ( $tax_cidade as $slug_cidade => $data_cidade ) {
			$aliases_base = [ 'imoveis', 'imobiliaria' ];
			foreach ( $aliases_base as $alias ) {
				$rule[] = [ 'regex' => '^' . $alias . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data_cidade[ 'tax' ], $data_cidade[ 'slug' ] ) . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
				$rule[] = [ 'regex' => '^' . $alias . '-' . $slug_cidade . '/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data_cidade[ 'tax' ], $data_cidade[ 'slug' ] ), 'hierarchical' => 'top' ];
			}
			foreach ( $tax_tipo_imovel as $slug_tipo => $data_tipo ) {
				$prefixo_pluralizado = $slug_tipo . 's?';
				$regra_base = self::INDEX_CACHE . '&' . $this->tupla( $data_tipo[ 'tax' ], $data_tipo[ 'slug' ] ) . '&' . $this->tupla( $data_cidade[ 'tax' ], $data_cidade[ 'slug' ] );
				$rule[] = [ 'regex' => '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$', 'rule' => $regra_base . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
				$rule[] = [ 'regex' => '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$', 'rule' => $regra_base, 'hierarchical' => 'top' ];
				foreach ( $mapa_prefixos as $verbo => $alvo_contrato ) {
					$regra_intencao = $regra_base;
					if ( isset( $tax_contrato[ $alvo_contrato ] ) ) {
						$data_contrato = $tax_contrato[ $alvo_contrato ];
						$regra_intencao .= '&' . $this->tupla( $data_contrato[ 'tax' ], $data_contrato[ 'slug' ] );
					}
					$rule[] = [ 'regex' => '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$', 'rule' => $regra_intencao . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
					$rule[] = [ 'regex' => '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$', 'rule' => $regra_intencao, 'hierarchical' => 'top' ];
					$rule[] = [ 'regex' => '^' . $prefixo_pluralizado . '-' . $verbo . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$', 'rule' => $regra_intencao . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
					$rule[] = [ 'regex' => '^' . $prefixo_pluralizado . '-' . $verbo . '-' . $slug_cidade . '/?$', 'rule' => $regra_intencao, 'hierarchical' => 'top' ];
				}
			}
		}
		return $rule;
	}
	private function url_one( $taxonomy_mapping ) {
		$rule = [];
		foreach ( $taxonomy_mapping as $slug => $data ) {
			$rule[] = [ 'regex' => '^' . $slug . '/page/([0-9]{1,3})/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data[ 'tax' ], $data[ 'slug' ] ) . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
			$rule[] = [ 'regex' => '^' . $slug . '/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data[ 'tax' ], $data[ 'slug' ] ), 'hierarchical' => 'top' ];
		}
		return $rule;
	}
	private function url_two( $taxonomy_one, $taxonomy_two ) {
		$rule = [];
		foreach ( $taxonomy_one as $slug1 => $data1 ) {
			foreach ( $taxonomy_two as $slug2 => $data2 ) {
				if ( $data1[ 'tax' ] != $data2[ 'tax' ] ) {
					$rule[] = [ 'regex' => '^' . $slug1 . '/' . $slug2 . '/page/([0-9]{1,3})/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ], $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ], $data2[ 'slug' ] ) . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
					$rule[] = [ 'regex' => '^' . $slug1 . '/' . $slug2 . '/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ], $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ], $data2[ 'slug' ] ), 'hierarchical' => 'top' ];
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
					if ( $data1[ 'tax' ] != $data2[ 'tax' ] && $data1[ 'tax' ] != $data3[ 'tax' ] && $data2[ 'tax' ] != $data3[ 'tax' ] ) {
						$rule[] = [ 'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/page/([0-9]{1,3})/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ], $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ], $data2[ 'slug' ] ) . '&' . $this->tupla( $data3[ 'tax' ], $data3[ 'slug' ] ) . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
						$rule[] = [ 'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/?$', 'rule' => self::INDEX_CACHE . '&' . $this->tupla( $data1[ 'tax' ], $data1[ 'slug' ] ) . '&' . $this->tupla( $data2[ 'tax' ], $data2[ 'slug' ] ) . '&' . $this->tupla( $data3[ 'tax' ], $data3[ 'slug' ] ), 'hierarchical' => 'top' ];
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
						if ( $data1[ 'tax' ] !== $data2[ 'tax' ] && $data1[ 'tax' ] !== $data3[ 'tax' ] && $data1[ 'tax' ] !== $data4[ 'tax' ] && $data2[ 'tax' ] !== $data3[ 'tax' ] && $data2[ 'tax' ] !== $data4[ 'tax' ] && $data3[ 'tax' ] !== $data4[ 'tax' ] ) {
							$rule[] = [ 'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/page/([0-9]{1,3})/?$', 'rule' => self::INDEX_CACHE . '&' . $data1[ 'tax' ] . '=' . $data1[ 'slug' ] . '&' . $data2[ 'tax' ] . '=' . $data2[ 'slug' ] . '&' . $data3[ 'tax' ] . '=' . $data3[ 'slug' ] . '&' . $data4[ 'tax' ] . '=' . $data4[ 'slug' ] . '&' . $this->tupla( 'paged', '$matches[1]' ), 'hierarchical' => 'top' ];
							$rule[] = [ 'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/?$', 'rule' => self::INDEX_CACHE . '&' . $data1[ 'tax' ] . '=' . $data1[ 'slug' ] . '&' . $data2[ 'tax' ] . '=' . $data2[ 'slug' ] . '&' . $data3[ 'tax' ] . '=' . $data3[ 'slug' ] . '&' . $data4[ 'tax' ] . '=' . $data4[ 'slug' ], 'hierarchical' => 'top' ];
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
			$this->url_seo_landing_pages( $tax_contrato, $tax_tipo_imovel, $tax_cidade )
			, $this->url_one( $tax_contrato )
			, $this->url_one( $tax_tipo_imovel )
			, $this->url_one( $tax_cidade )
			, $this->url_two( $tax_contrato, $tax_tipo_imovel )
			, $this->url_two( $tax_contrato, $tax_cidade )
			, $this->url_two( $tax_tipo_imovel, $tax_contrato )
			, $this->url_two( $tax_tipo_imovel, $tax_cidade )
			, $this->url_two( $tax_cidade, $tax_contrato )
			, $this->url_two( $tax_cidade, $tax_tipo_imovel )
			, $this->url_tree( $tax_contrato, $tax_tipo_imovel, $tax_cidade )
			, $this->url_tree( $tax_contrato, $tax_cidade, $tax_tipo_imovel )
			, $this->url_tree( $tax_tipo_imovel, $tax_contrato, $tax_cidade )
			, $this->url_tree( $tax_tipo_imovel, $tax_cidade, $tax_contrato )
			, $this->url_tree( $tax_cidade, $tax_contrato, $tax_tipo_imovel )
			, $this->url_tree( $tax_cidade, $tax_tipo_imovel, $tax_contrato )
		);
		return $rules;
	}
	public function clear( ) {
		delete_transient( self::CACHE_URL_AMIGAVEL );
		delete_transient( self::CACHE_MAPA_HIERARQUICO );
		delete_transient( self::CACHE_MAPA_IMOVEIS );
		delete_transient( 'CACHE_PRETTY_URL_V3' );
		delete_transient( 'CACHE_PRETTY_URL_V2' );
		delete_transient( 'CACHE_MAPA_HIERARQUICO_V5' );
		delete_transient( 'CACHE_MAPA_IMOVEIS_TREE_V3' );
		delete_transient( 'CACHE_PRETTY_URL' );
		delete_transient( 'CACHE_MAPA_HIERARQUICO_V4' );
		delete_transient( 'CACHE_MAPA_IMOVEIS_TREE_V2' );
	}
	public function do( ) {
		$forcar_reset = isset( $_GET[ 'forcar_reset' ] );
		if ( $forcar_reset ) {
			$this->clear( );
		}
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		$rules = false;
		$reconstruiu_cache = false;
		if ( ! $em_desenvolvimento ) {
			$rules = get_transient( self::CACHE_URL_AMIGAVEL );
		}
		if ( $rules === false ) {
			$tax_contrato = [];
			foreach ( lista_contratos( ) as $contrato ) {
				$tax_contrato[ sanitize_title( $contrato->name ) ] = [ 'tax' => 'contrato', 'slug' => $contrato->slug ];
			}
			$tax_tipo_imovel = [];
			foreach ( lista_tipo_imovel( ) as $tipo_imovel ) {
				$tax_tipo_imovel[ sanitize_title( $tipo_imovel->name ) ] = [ 'tax' => 'tipo-imovel', 'slug' => $tipo_imovel->slug ];
			}
			$tax_cidade = [];
			foreach ( lista_cidade( ) as $cidade ) {
				$tax_cidade[ sanitize_title( $cidade->name ) ] = [ 'tax' => 'cidade', 'slug' => $cidade->slug ];
			}
			if ( empty( $tax_contrato ) || empty( $tax_tipo_imovel ) || empty( $tax_cidade ) ) {
				return;
			}
			$hierarquia = self::get_mapa_hierarquico( );
			$rules = array_merge(
				$this->cria( $tax_contrato, $tax_tipo_imovel, $tax_cidade )
				, $this->url_hierarquia_regras( $hierarquia[ 'regioes_rules' ], $tax_contrato, $tax_tipo_imovel )
			);
			$reconstruiu_cache = true;
			if ( ! $em_desenvolvimento ) {
				set_transient( self::CACHE_URL_AMIGAVEL, $rules, WEEK_IN_SECONDS );
			}
		}
		$this->register( $rules );
		if ( $forcar_reset || $this->delete_cache === true || $reconstruiu_cache ) {
			flush_rewrite_rules( false );
		}
	}
	public static function gerar_links_mapa_imoveis( ) {
		global $wpdb;
		$tipos_sinonimos = self::get_mapa_tipo_imovel( );
		$mapa_slug_para_canonico = [];
		$mapa_sinonimo_slug = [];
		foreach ( $tipos_sinonimos as $canonical => $sinonimos_array ) {
			foreach ( array_keys( $sinonimos_array ) as $sinonimo ) {
				$slug_sinonimo = sanitize_title( $sinonimo );
				$mapa_slug_para_canonico[ $slug_sinonimo ] = $canonical;
				$mapa_sinonimo_slug[ $slug_sinonimo ] = mb_convert_case( strtolower( trim( $sinonimo ) ), MB_CASE_TITLE, 'UTF-8' );
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
		$cache_key = self::CACHE_MAPA_IMOVEIS;
		$em_desenvolvimento = ( function_exists( 'is_development_mode' ) && is_development_mode( ) );
		if ( ! $em_desenvolvimento ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) return $cached;
		}
		$sql_matriz = "SELECT 'venda' AS contrato, pm_tipo.meta_value AS tipo_imovel, pm_cidade.meta_value AS cidade, pm_regiao.meta_value AS regiao, COUNT( p.ID ) AS total_imoveis FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D' INNER JOIN {$wpdb->postmeta} pm_contrato ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarVenda' AND pm_contrato.meta_value = '1' LEFT JOIN {$wpdb->postmeta} pm_tipo ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome' LEFT JOIN {$wpdb->postmeta} pm_cidade ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade' LEFT JOIN {$wpdb->postmeta} pm_regiao ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao' WHERE p.post_type = 'imovel' AND p.post_status = 'publish' GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value UNION ALL SELECT 'locacao' AS contrato, pm_tipo.meta_value AS tipo_imovel, pm_cidade.meta_value AS cidade, pm_regiao.meta_value AS regiao, COUNT( p.ID ) AS total_imoveis FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D' INNER JOIN {$wpdb->postmeta} pm_contrato ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarLocacao' AND pm_contrato.meta_value = '1' LEFT JOIN {$wpdb->postmeta} pm_tipo ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome' LEFT JOIN {$wpdb->postmeta} pm_cidade ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade' LEFT JOIN {$wpdb->postmeta} pm_regiao ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao' WHERE p.post_type = 'imovel' AND p.post_status = 'publish' GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value UNION ALL SELECT 'lancamento' AS contrato, pm_tipo.meta_value AS tipo_imovel, pm_cidade.meta_value AS cidade, pm_regiao.meta_value AS regiao, COUNT( p.ID ) AS total_imoveis FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D' INNER JOIN {$wpdb->postmeta} pm_contrato ON p.ID = pm_contrato.post_id AND pm_contrato.meta_key = 'ativarLancamento' AND pm_contrato.meta_value = '1' LEFT JOIN {$wpdb->postmeta} pm_tipo ON p.ID = pm_tipo.post_id AND pm_tipo.meta_key = 'tipoImovelNome' LEFT JOIN {$wpdb->postmeta} pm_cidade ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade' LEFT JOIN {$wpdb->postmeta} pm_regiao ON p.ID = pm_regiao.post_id AND pm_regiao.meta_key = 'regiao' WHERE p.post_type = 'imovel' AND p.post_status = 'publish' GROUP BY pm_tipo.meta_value, pm_cidade.meta_value, pm_regiao.meta_value ORDER BY total_imoveis DESC;";
		$combinacoes = $wpdb->get_results( $sql_matriz, ARRAY_A );
		$arvore = [];
		if ( ! empty( $combinacoes ) ) {
			$mapa_nomes_contrato = [ 'venda' => 'Venda', 'locacao' => 'Locação', 'lancamento' => 'Lançamento' ];
			foreach ( $combinacoes as $linha ) {
				if ( empty( $linha[ 'cidade' ] ) || empty( $linha[ 'tipo_imovel' ] ) ) continue;
				$c_slug = $linha[ 'contrato' ];
				$cid_slug = sanitize_title( $linha[ 'cidade' ] );
				$reg_limpa = ! empty( $linha[ 'regiao' ] ) ? self::limpar_prefixo_numerico( $linha[ 'regiao' ] ) : '';
				$reg_slug = ! empty( $reg_limpa ) ? sanitize_title( $reg_limpa ) : '';
				$t_slug_db = sanitize_title( $linha[ 'tipo_imovel' ] );
				$canonical_slug = $mapa_slug_para_canonico[ $t_slug_db ] ?? $t_slug_db;
				$nome_contrato = $mapa_nomes_contrato[ $c_slug ];
				$nome_cidade = mb_convert_case( strtolower( trim( $linha[ 'cidade' ] ) ), MB_CASE_TITLE, 'UTF-8' );
				$nome_regiao = ! empty( $reg_limpa ) ? mb_convert_case( strtolower( $reg_limpa ), MB_CASE_TITLE, 'UTF-8' ) : 'Centro e Principais Bairros';
				$nome_tipo_canonico = $mapa_labels_canonicos[ $canonical_slug ] ?? mb_convert_case( str_replace( '-', ' ', $canonical_slug ), MB_CASE_TITLE, 'UTF-8' );
				if ( ! isset( $arvore[ $c_slug ] ) ) {
					$arvore[ $c_slug ] = [ 'nome' => $nome_contrato, 'tipos' => [] ];
				}
				if ( ! isset( $arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ] ) ) {
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ] = [ 'nome' => $nome_tipo_canonico, 'cidades' => [] ];
				}
				if ( ! isset( $arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ] ) ) {
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ] = [ 'nome' => $nome_cidade, 'regioes' => [] ];
				}
				if ( ! isset( $arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ] ) ) {
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ] = [ 'nome' => $nome_regiao, 'total' => 0, 'sinonimos' => [] ];
				}
				$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ][ 'total' ] += (int) $linha[ 'total_imoveis' ];
				if ( isset( $tipos_sinonimos[ $canonical_slug ] ) ) {
					foreach ( array_keys( $tipos_sinonimos[ $canonical_slug ] ) as $synonimo ) {
						$t_slug_url = sanitize_title( $synonimo );
						$nome_tipo = $mapa_sinonimo_slug[ $t_slug_url ] ?? mb_convert_case( $synonimo, MB_CASE_TITLE, 'UTF-8' );
						$partes_url = array_filter( [ $c_slug, $t_slug_url, $cid_slug, $reg_slug ] );
						$url = '/' . implode( '/', $partes_url ) . '/';
						$label = "{$nome_contrato} de {$nome_tipo} em {$nome_cidade}" . ( ! empty( $reg_limpa ) ? " - {$nome_regiao}" : '' );
						$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ][ 'sinonimos' ][ $t_slug_url ] = [ 'url' => $url, 'label' => $label, 'label_simples' => $nome_tipo ];
					}
				} else {
					$t_slug_url = $t_slug_db;
					$nome_tipo = mb_convert_case( strtolower( trim( $linha[ 'tipo_imovel' ] ) ), MB_CASE_TITLE, 'UTF-8' );
					$partes_url = array_filter( [ $c_slug, $t_slug_url, $cid_slug, $reg_slug ] );
					$url = '/' . implode( '/', $partes_url ) . '/';
					$label = "{$nome_contrato} de {$nome_tipo} em {$nome_cidade}" . ( ! empty( $reg_limpa ) ? " - {$nome_regiao}" : '' );
					$arvore[ $c_slug ][ 'tipos' ][ $canonical_slug ][ 'cidades' ][ $cid_slug ][ 'regioes' ][ $reg_slug ][ 'sinonimos' ][ $t_slug_url ] = [ 'url' => $url, 'label' => $label, 'label_simples' => $nome_tipo ];
				}
			}
		}
		if ( ! $em_desenvolvimento ) {
			set_transient( $cache_key, $arvore, 12 * HOUR_IN_SECONDS );
		}
		return $arvore;
	}
}