<?php

class PrettyUrl {
	const CACHE_URL_AMIGAVEL = 'CACHE_PRETTY_URL';
	const INDEX_CACHE = 'index.php?post_type=imovel&tipo_pesquisa_submit=imovel';
	private $delete_cache = false;
    // Troque a const por este método estático
    public static function get_mapa_tipo_imovel() {
        return [
            'apartamento' => array_flip([
                'apartamento', 'flat', 'loft', 'duplex', 'triplex', 'apartamento flat', 'apartamento loft', 'apartamento duplex', 'apartamento triplex', 'cobertura', 'apartamento cobertura', 'kitnet', 'kit', 'studio', 'penthouse', 'garden'
            ]),
            'imovel-comercial' => array_flip([
                'imovel comercial', 'loja', 'sobreloja', 'sobre loja', 'salão', 'sala', 'barracão', 'galpão', 'consultório', 'escritório', 'bar', 'bodega', 'restaurante', 'lanchonete', 'box', 'ponto comercial', 'depósito', 'pavilhão', 'prédio comercial', 'centro comercial', 'loja térrea'
            ]),
            'casa' => array_flip([
                'casa', 'casa térra', 'sobrado', 'assobradado', 'assobradada', 'residência', 'casa em condomínio', 'mansão', 'edícula', 'casa de vila', 'bangalô', 'chalé', 'casa geminada'
            ]),
            'rural' => array_flip([
                'rural', 'chácara', 'sítio', 'fazenda', 'propriedade', 'granja', 'estância', 'pomar', 'horta', 'haras', 'pousada', 'área rural'
            ]),
            'lazer' => array_flip([
                'lazer', 'rancho', 'balneário', 'clubinho', 'clube', 'chácara', 'quadra', 'casa de campo', 'centro de eventos', 'área de lazer', 'salão de festas', 'retiro'
            ]),
            'terreno-ou-area' => array_flip([
                'terreno ou área', 'área', 'área industrial', 'área incorporação', 'terreno', 'lote', 'gleba', 'loteamento', 'área comercial', 'recinto', 'recinto de exposiçao', 'área para rodeio', 'estacionamento'
            ]),
        ];
    }
	public function __construct( $delete_cache = false ) {
		if ( is_development_mode() ) {
			$delete_cache = true;
		}
		$this->delete_cache = $delete_cache;
		if ( $delete_cache === true )	{
			$this->clear();
		}
		add_action( 'template_redirect', [$this, 'redirecionar_acentos'] );
	}

	public function redirecionar_acentos() {
		// Só atua se o WordPress não encontrar a página (Erro 404)
		if ( is_404() ) {
			$uri = $_SERVER['REQUEST_URI'];
			$parsed = parse_url($uri);
			$path = isset($parsed['path']) ? $parsed['path'] : '';

			// Decodifica a URL (Ex: transforma %C3%A7atuba de volta para araçatuba)
			$decoded_path = urldecode($path);

			// Remove os acentos e cedilhas (Ex: transforma araçatuba em aracatuba)
			$clean_path = remove_accents($decoded_path);
			$clean_path = strtolower( str_replace([' ', '%20'], '-', $clean_path) );

			// Se o caminho limpo for diferente do que foi digitado, faz o redirecionamento 301
			if ( $clean_path !== strtolower($decoded_path) ) {
				$new_url = home_url( $clean_path );
				if ( !empty($parsed['query']) ) {
					$new_url .= '?' . $parsed['query'];
				}
				wp_redirect( $new_url, 301 );
				exit;
			}
		}
	}

    // =========================================================================
    // ROTA DE SEO: Gera URLs de Alta Intenção de Compra e Locação
    // =========================================================================
    private function url_seo_landing_pages( $tax_contrato, $tax_tipo_imovel, $tax_cidade ) {
        $rule = [];

        $chave_venda = 'venda';
        $chave_locacao = 'locacao';

        foreach (array_keys($tax_contrato) as $key) {
            if (strpos($key, 'vend') !== false || strpos($key, 'compr') !== false) $chave_venda = $key;
            if (strpos($key, 'loca') !== false || strpos($key, 'alug') !== false) $chave_locacao = $key;
        }

        $mapa_prefixos = [
            'venda'   => $chave_venda,
            'comprar' => $chave_venda,
            'vender'  => $chave_venda,
            'aluguel' => $chave_locacao,
            'alugar'  => $chave_locacao,
            'locar'   => $chave_locacao,
            'locação' => $chave_locacao,
            'arrendar' => $chave_locacao
        ];

        foreach ($tax_cidade as $slug_cidade => $data_cidade) {

            $aliases_base = ['imoveis', 'imobiliaria'];
            foreach ($aliases_base as $alias) {
                $rule[] = [
                    'regex' => ( '^' . $alias . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$' ),
                    'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data_cidade['tax'], $data_cidade['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
                    'hierarchical' => 'top'
                ];
                $rule[] = [
                    'regex' => ( '^' . $alias . '-' . $slug_cidade . '/?$' ),
                    'rule' => ( self::INDEX_CACHE . '&' . $this->tupla( $data_cidade['tax'], $data_cidade['slug'] ) ),
                    'hierarchical' => 'top'
                ];
            }

            foreach ($tax_tipo_imovel as $slug_tipo => $data_tipo) {
                $prefixo_pluralizado = $slug_tipo . 's?';

                $regra_base = self::INDEX_CACHE
                            . '&' . $this->tupla( $data_tipo['tax'], $data_tipo['slug'] )
                            . '&' . $this->tupla( $data_cidade['tax'], $data_cidade['slug'] );

                $rule[] = [
                    'regex' => ( '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$' ),
                    'rule' => ( $regra_base . '&' . $this->tupla( 'paged', '$matches[1]' ) ),
                    'hierarchical' => 'top'
                ];
                $rule[] = [
                    'regex' => ( '^' . $prefixo_pluralizado . '-' . $slug_cidade . '/?$' ),
                    'rule' => ( $regra_base ),
                    'hierarchical' => 'top'
                ];

                foreach ($mapa_prefixos as $verbo => $alvo_contrato) {
                    $regra_intencao = $regra_base;

                    if ( isset( $tax_contrato[$alvo_contrato] ) ) {
                        $data_contrato = $tax_contrato[$alvo_contrato];
                        $regra_intencao .= '&' . $this->tupla( $data_contrato['tax'], $data_contrato['slug'] );
                    }

                    $rule[] = [
                        'regex' => ( '^' . $verbo . '-' . $prefixo_pluralizado . '-' . $slug_cidade . '/page/([0-9]{1,3})/?$' ),
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
		foreach ($taxonomy_mapping as $slug => $data) {
			$rule[] = [
				'regex' => ( '^' . $slug . '/page/([0-9]{1,3})/?$' )
				, 'rule' => (
					self::INDEX_CACHE
					. '&' . $this->tupla( $data['tax'], $data['slug'] ) . '&' . $this->tupla( 'paged', '$matches[1]' )
				)
				, 'hierarchical' => 'top'
			];
			$rule[] = [
				'regex' => ( '^' . $slug . '/?$' )
				, 'rule' => (
					self::INDEX_CACHE
					. '&' . $this->tupla( $data['tax'], $data['slug'] )
				)
				, 'hierarchical' => 'top'
			];
		}
		return $rule;
	}

	private function url_two( $taxonomy_one, $taxonomy_two ) {
		$rule = [];
		foreach ($taxonomy_one as $slug1 => $data1) {
			foreach ($taxonomy_two as $slug2 => $data2) {
				if ($data1['tax'] != $data2['tax']) {
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/page/([0-9]{1,3})/?$' )
						, 'rule' => (
							self::INDEX_CACHE
							. '&'. $this->tupla( $data1['tax'], $data1['slug'] )
							. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
							. '&' . $this->tupla( 'paged', '$matches[1]' )
						)
						, 'hierarchical' => 'top'
					];
					$rule[] = [
						'regex' => ( '^' . $slug1 . '/' . $slug2 . '/?$' )
						, 'rule' => (
							self::INDEX_CACHE
							. '&'. $this->tupla( $data1['tax'], $data1['slug'] )
							. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
						)
						, 'hierarchical' => 'top'
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
		foreach ($taxonomy_one as $slug1 => $data1) {
			foreach ($taxonomy_two as $slug2 => $data2) {
				foreach ($taxonomy_tree as $slug3 => $data3) {
					if ($data1['tax'] != $data2['tax'] &&
						$data1['tax'] != $data3['tax'] &&
						$data2['tax'] != $data3['tax']) {
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/page/([0-9]{1,3})/?$' )
							, 'rule' => (
								self::INDEX_CACHE
								. '&' . $this->tupla( $data1['tax'], $data1['slug'] )
								. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
								. '&' . $this->tupla( $data3['tax'], $data3['slug'] )
								. '&' . $this->tupla( 'paged', '$matches[1]' )
							)
							, 'hierarchical' => 'top'
						];
						$rule[] = [
							'regex' => ( '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/?$' )
							, 'rule' => (
								self::INDEX_CACHE
								. '&' . $this->tupla( $data1['tax'], $data1['slug'] )
								. '&' . $this->tupla( $data2['tax'], $data2['slug'] )
								. '&' . $this->tupla( $data3['tax'], $data3['slug'] )
							)
							, 'hierarchical' => 'top'
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
							$data1['tax'] !== $data2['tax'] &&
							$data1['tax'] !== $data3['tax'] &&
							$data1['tax'] !== $data4['tax'] &&
							$data2['tax'] !== $data3['tax'] &&
							$data2['tax'] !== $data4['tax'] &&
							$data3['tax'] !== $data4['tax']
						) {
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/page/([0-9]{1,3})/?$'
								, 'rule' => self::INDEX_CACHE
									. $data1['tax'] . '=' . $data1['slug']
									. '&' . $data2['tax'] . '=' . $data2['slug']
									. '&' . $data3['tax'] . '=' . $data3['slug']
									. '&' . $data4['tax'] . '=' . $data4['slug']
									. '&' . $this->tupla( 'paged', '$matches[1]' )
								, 'hierarchical' => 'top'
							];
							$rule[] = [
								'regex' => '^' . $slug1 . '/' . $slug2 . '/' . $slug3 . '/' . $slug4 . '/?$',
								'rule'  => self::INDEX_CACHE
									. $data1['tax'] . '=' . $data1['slug']
									. '&' . $data2['tax'] . '=' . $data2['slug']
									. '&' . $data3['tax'] . '=' . $data3['slug']
									. '&' . $data4['tax'] . '=' . $data4['slug'],
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
		foreach ($rules as $rule) {
			add_rewrite_rule( $rule[ 'regex' ], $rule[ 'rule' ], $rule[ 'hierarchical' ] );
		}
		if ( $this->delete_cache === true ) {
			flush_rewrite_rules( );
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

	public function do() {
		$rules = get_transient( self::CACHE_URL_AMIGAVEL );

		if ( $rules === false ) {
			$tax_contrato = [];
			foreach (lista_contratos() as $contrato) {
				$tax_contrato[ sanitize_title( $contrato->name ) ] = ['tax' => 'contrato', 'slug' => $contrato->slug];
			}
			$tax_tipo_imovel = [];
			foreach (lista_tipo_imovel() as $tipo_imovel) {
				$tax_tipo_imovel[ sanitize_title( $tipo_imovel->name ) ] = ['tax' => 'tipo-imovel', 'slug' => $tipo_imovel->slug];
			}
			$tax_cidade = [];
			foreach (lista_cidade() as $cidade) {
				$tax_cidade[ sanitize_title( $cidade->name ) ] = ['tax' => 'cidade', 'slug' => $cidade->slug];
			}
			$rules = $this->cria( $tax_contrato, $tax_tipo_imovel, $tax_cidade );
			set_transient( self::CACHE_URL_AMIGAVEL, $rules, WEEK_IN_SECONDS );
		}

		$this->register( $rules );
	}

	public function clear() {
		delete_transient( self::CACHE_URL_AMIGAVEL );
	}
}