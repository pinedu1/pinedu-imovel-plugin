<?php
require_once plugin_dir_path(__FILE__) . 'class-pinedu-foto-util.php';

class Pinedu_Imovel_Importa_Imovel {
	private $imoveis_importados = 0;
	public function __construct( ) {
	}
	public function getImoveisImportados(): int {
		return $this->imoveis_importados;
	}
	public function setImoveisImportados( int $imoveis_importados ): void {
		$this->imoveis_importados = $imoveis_importados;
	}
	public function trata_excluidos( $array_referencias ): bool {
        if (empty($array_referencias)) {
            return true;
        }
		$referencias = array();
		foreach ( $array_referencias as $ref ) {
			$referencias[] = $ref['referencia'];
		}
		$post_ids = $this->busca_excluidos_from_referencia_array( $referencias );
        if (empty($post_ids)) {
            return true;
        }
        $this->trata_excluidos_post_ids( $post_ids );
        return true;
	}
    public function trata_excluidos_from_referecia_array( $array_referencias ): bool {
        if (empty($array_referencias)) {
            return true;
        }
        $post_ids = $this->busca_excluidos_from_referencia_array( $array_referencias );
        if (empty($post_ids)) {
            return true;
        }
        $this->trata_excluidos_post_ids( $post_ids );
        return true;
    }
    public function busca_excluidos_from_referencia_array( $array_referencias ): array {
        $args = array(
            'post_type'      => 'imovel',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'referencia',
                    'value'   => $array_referencias,
                    'compare' => 'IN',
                ),
            ),
        );
        $query = new WP_Query($args);
        $post_ids = $query->posts;
        wp_reset_postdata();
        return $post_ids;
    }

    public function trata_excluidos_post_ids( $post_ids ) {
        if (!empty($post_ids)) {
            foreach ($post_ids as $post_id) {
                $this->excluir( $post_id );
            }
        }
    }
	public function importa_imoveis( $imoveis ) {
		if ( ! post_type_exists( 'imovel' ) ) {
			wp_send_json_error( ['message' => 'Post Type Imóvel não existe!.'] );
			return;
		}
		foreach ( $imoveis as $imovel ) {
			//error_log( $imovel['referencia'] );
			$this->importa_imovel( $imovel );
		}
	}
	private function importa_imovel( $imovel ) {
		$args = array( 
			'meta_key' => 'referencia'
			, 'meta_value' => $imovel['referencia']
			, 'post_type' => 'imovel'
			, 'post_status' => 'any'
			, 'numberposts' => 1
		 );
		$post = get_posts( $args );
		if ( empty( $post ) ) {
			$post_id = $this->salvar( $imovel );
		} else {
			$post = $post[0];
			$post_id = $this->atualizar( $post->ID, $imovel );
		}

		wp_reset_postdata( );
		$this->setImoveisImportados( $this->getImoveisImportados() + 1 );
	}
	private function salvar( $imovel ) {
		$post_data = array( 
			'post_title' => $this->resolve_slug( $imovel )
			, 'post_content' => $this->resolve_anuncio( $imovel )??''
			, 'post_status' => 'publish'
			, 'post_type' => 'imovel'
			, 'post_date' => current_time( 'mysql' )
		 );
		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			wp_die( $post_id->get_error_messages( ) );
			return false;
		}

		$importa_taxonomias = new Pinedu_Imovel_Importa_Taxonomias( $post_id, $imovel );
		$importa_taxonomias->salvar( );

		$importa_metadados = new Pinedu_Imovel_Importa_Metadados( $post_id, $imovel );
		$importa_metadados->salvar( );

        $importar_fotos = true;
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( isset( $options['fotos_demanda'] ) && $options['fotos_demanda'] === 'on' ) {
            $importar_fotos = false;
        }
        $importa_fotos = new Pinedu_Imovel_Importa_Foto($post_id, $imovel);
        $importa_fotos->salva_imagem_destaque();
        $importa_fotos->salvar_fotografias();

		return $post_id;
	}
	private function excluir( $post_id ) {
		$post = get_post($post_id);
		if (!is_null($post)) {
			return false;
		}

		$fotografias_post = get_post_meta( $post_id, 'fotografias', false );

		$importa_taxonomias = new Pinedu_Imovel_Importa_Taxonomias( $post_id, array() );
		$importa_taxonomias->excluir();

		$importa_metadados = new Pinedu_Imovel_Importa_Metadados( $post_id, array() );
		$importa_metadados->excluir( );

		$importa_fotos = new Pinedu_Imovel_Importa_Foto( $post_id, array() );
		$importa_fotos->exclui_imagem_destaque( );
		$importa_fotos->excluir_fotografias( $fotografias_post );

		wp_delete_post($post_id, true);
	}
	private function atualizar( $post_id, $imovel ) {
		$post_data = array( 
			'post_title'   => $this->resolve_slug( $imovel )
			, 'post_content' => $this->resolve_anuncio( $imovel )??''
			, 'post_status'  => 'publish'
			, 'post_type'    => 'imovel'
			, 'ID'           => $post_id
		 );
		$post_id = wp_update_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			error_log( 'Erro ao atualizar imóvel: ' . $post_id->get_error_message( ) );
			return false;
		}
		$fotografias_post = get_post_meta( $post_id, 'fotografias', false );

		$importa_taxonomias = new Pinedu_Imovel_Importa_Taxonomias( $post_id, $imovel );
		$importa_taxonomias->atualizar( );

		$importa_metadados = new Pinedu_Imovel_Importa_Metadados( $post_id, $imovel );
		$importa_metadados->atualizar( );

		$importa_fotos = new Pinedu_Imovel_Importa_Foto( $post_id, $imovel );
		$importa_fotos->atualiza_imagem_destaque( );
		$importa_fotos->atualizar_fotografias( $fotografias_post );

		return $post_id;
	}
	private function resolve_slug( $imovel ) {
		$referencia = $imovel['referencia'];
		$slug_imovel = '';
		if ( isset( $imovel['vendaSlug'] ) && !empty( trim( $imovel['vendaSlug'] ) ) ) {
			$slug_imovel = trim( $imovel['vendaSlug'] );
		}
		if ( isset( $imovel['locacaoSlug'] ) && !empty( trim( $imovel['locacaoSlug'] ) ) ) {
			if ( empty( $slug_imovel ) ) {
				$slug_imovel = trim( $imovel['locacaoSlug'] );
			} else {
				$slug_imovel += ' / ' . trim( $imovel['locacaoSlug'] );
			}
		}
		if ( isset( $imovel['lancamentoSlug'] ) && !empty( trim( $imovel['lancamentoSlug'] ) ) ) {
			if ( empty( $slug_imovel ) ) {
				$slug_imovel = $imovel['lancamentoSlug'];
			} else {
				$slug_imovel += ' / ' . trim( $imovel['lancamentoSlug'] );
			}
		}
		if ( empty( $slug_imovel ) ) {
			$slug_imovel = 'Referência: ' . $imovel['referencia'];
		}
		if ( !str_ends_with( $slug_imovel, $referencia ) ) {
			$slug_imovel .= ' Ref: ' . $referencia;
		}
		return $slug_imovel;
	}
	private function resolve_anuncio( $imovel ) {
		return $imovel['anuncioRenderizado'];
	}
}
class Pinedu_Imovel_Importa_Metadados {
	const PROPRIEDADES = ['id', 'referencia', 'anoConstrucao', 'anuncio', 'anuncioRenderizado', 'ativarLancamento', 'ativarLocacao', 'ativarVenda', 'bairro', 'bairroCorretagem', 'captadorPrincipalId', 'captadorPrincipalNome', 'captadorPrincipalPessoaId', 'carteira', 'cep', 'chaves_id', 'cidade', 'cidadeCorretagem', 'condominio_id', 'custoAnuncio', 'dataCaptacao', 'dateCreated', 'descricaoChaves', 'desocupacao', 'edificio_id', 'enderecoRenderizado', 'enviarWeb', 'estado', 'estado_id', 'finalidade', 'finalidadeNome', 'horarioVisita', 'lancamento', 'lancamentoDataAtualizacao', 'lancamentoProxAtualizacao', 'lancamentoNome', 'lancamentoPromocao', 'lancamentoSlug', 'lancamentoValor', 'lastUpdated', 'latitude', 'logradouroDNE', 'loja_id', 'longitude', 'matAgua', 'matEner', 'matGaz', 'matIPTU', 'memorialDescritivo', 'nomeUsuCriador', 'novo', 'observacoes', 'obsLocal', 'padraoConstrucao', 'permiteIntermediacao', 'permitePlaca', 'permiteUnidades', 'placa_id', 'pontoReferencia', 'proprietario_id', 'regiao', 'regiaoCorretagem', 'segmento_id', 'statusImovel', 'tipoImovel_id', 'tipoOcupacao', 'tipoOcupacaoNome', 'tituloEdificio', 'version', 'zoneamento'];
	const TIPO_DEPENDENCIA = array(
		'tipDep_descricao' => 'descricao'
		, 'tipDep_nome' => 'nome'
		, 'tipDep_posicao' => 'posicao'
		, 'tipDep_relativo' => 'relativo'
		, 'tipDep_sigla' => 'sigla'
		, 'tipDep_tipoCampo' => 'tipoCampo'
		, 'valorBoolean' => 'boolean'
		, 'valorFloat' => 'float'
		, 'valorInteiro' => 'inteiro'
		, 'valorTexto' => 'texto'
	);
	private $imovel;
	private $post_id;
	public function __construct( $post_id, $imovel ) {
		$this->imovel = $imovel;
		$this->post_id = $post_id;
	}
	public function salvar( ) {
		$this->salvar_metadados_imovel( );
	}
	public function excluir( ) {
		$this->apagar_metadados_imovel( );
	}
	public function atualizar( ) {
		$this->apagar_metadados_imovel( );
		$this->salvar_metadados_imovel( );
	}
	private function salvar_dependencias( $dependencias = [] ) {
		if ( empty( $dependencias ) ) return false;
		foreach( $dependencias as $dependencia ) {
			$descricao = $dependencia['tipDep_descricao'];
			$nome =  $dependencia['tipDep_nome'];
			$posicao = $dependencia['tipDep_posicao'];
			$relativo =  $dependencia['tipDep_relativo'];
			$sigla = $dependencia['tipDep_sigla'];
			$tipoCampo = $dependencia['tipDep_tipoCampo'];
			$boolean =  $dependencia['valorBoolean'];
			$float =  $dependencia['valorFloat'];
			$inteiro =  $dependencia['valorInteiro'];
			$texto =  $dependencia['valorTexto'];
			$valor = null;
			switch ( $tipoCampo ) {
				case 'TEXTO':
					$valor = $texto;
					if ( !empty( $valor ) ) {
						add_post_meta( $this->post_id, $sigla, $valor, true );
						add_post_meta( $this->post_id, ( $sigla . 'Nome' ), $nome, true );
					}
					break;
				case 'INTEIRO':
				case 'INTEIRO_TEXTO':
					$valor = (int)$inteiro;
					if ( $valor > 0 ) {
						add_post_meta( $this->post_id, $sigla, $valor, true );
						add_post_meta( $this->post_id, ( $sigla . 'Nome' ), $nome, true );
                        add_post_meta( $this->post_id, ( $sigla . 'Relativo' ), $relativo, true );
					}
					break;
				case 'FLOAT':
				case 'FLOAT_TEXTO':
					$valor = (float)$float;
					if ( $valor > 0 ) {
						add_post_meta( $this->post_id, $sigla, $valor, true );
						add_post_meta( $this->post_id, ( $sigla . 'Nome' ), $nome, true );
						add_post_meta( $this->post_id, ( $sigla . 'Relativo' ), $relativo, true );
					}
					break;
				case 'BOOLEAN':
				case 'BOOLEAN_TEXTO':
					$valor = (boolean)$boolean;
					if ( $valor === true ) {
						add_post_meta( $this->post_id, $sigla, $valor, true );
						add_post_meta( $this->post_id, ( $sigla . 'Nome' ), $nome, true );
						add_post_meta( $this->post_id, ( $sigla . 'Relativo' ), $relativo, true );
					}
					break;
				default:
					break;
			}
		}
	}
	/**
	 * Salva metadasdos do contrato venda
	 * É monótono e manual, mas não achei maneira melhor de fazer isto, visto que preciso da propriedade no POST_TYPE para ordenação
	 * @param $properties
	 * @return array
     */
	private function salva_contrato_venda( &$properties ): array {
		$vendaValor = (float)$properties[ 'vendaValor' ] ?? 0;
		unset( $properties[ 'vendaValor' ] );
		$vendaDataAtualizacao = $properties[ 'vendaDataAtualizacao' ] ?? '1980-01-01T00:00:00Z';
		unset( $properties[ 'vendaDataAtualizacao' ] );
		$vendaNome = $properties[ 'vendaNome' ] ?? '';
		unset( $properties[ 'vendaNome' ] );
		$vendaPromocao = $properties[ 'vendaPromocao' ] ?? '';
		unset( $properties[ 'vendaPromocao' ] );
		$vendaProxAtualizacao = $properties[ 'vendaProxAtualizacao' ] ?? '';
		unset( $properties[ 'vendaProxAtualizacao' ] );
		$vendaSlug = $properties[ 'vendaSlug' ] ?? '';
		unset( $properties[ 'vendaSlug' ] );
		add_post_meta( $this->post_id, 'vendaValor', (float)$vendaValor, true );
		add_post_meta( $this->post_id, 'vendaDataAtualizacao', $vendaDataAtualizacao, true );
		add_post_meta( $this->post_id, 'vendaNome', $vendaNome, true );
		add_post_meta( $this->post_id, 'vendaPromocao', $vendaPromocao, true );
		add_post_meta( $this->post_id, 'vendaProxAtualizacao', $vendaProxAtualizacao, true );
		add_post_meta( $this->post_id, 'vendaSlug', $vendaSlug, true );
		return ['valor' => $vendaValor, 'data' => new DateTime( $vendaDataAtualizacao ) ];
	}
	/**
	 * Salva metadasdos do contrato venda
	 * É monótono e manual, mas não achei maneira melhor de fazer isto, visto que preciso da propriedade no POST_TYPE para ordenação
	 * @param $properties
	 * @return void
	 */
	private function salva_contrato_locacao( &$properties ): array {
		$locacaoValor = (float)$properties[ 'locacaoValor' ] ?? 0;
		unset( $properties[ 'locacaoValor' ] );
		$locacaoDataAtualizacao = $properties[ 'locacaoDataAtualizacao' ] ?? '1980-01-01T00:00:00Z';
		unset( $properties[ 'locacaoDataAtualizacao' ] );
		$locacaoNome = $properties[ 'locacaoNome' ] ?? '';
		unset( $properties[ 'locacaoNome' ] );
		$locacaoPromocao = $properties[ 'locacaoPromocao' ] ?? '';
		unset( $properties[ 'locacaoPromocao' ] );
		$locacaoProxAtualizacao = $properties[ 'locacaoProxAtualizacao' ] ?? '';
		unset( $properties[ 'locacaoProxAtualizacao' ] );
		$locacaoSlug = $properties[ 'locacaoSlug' ] ?? '';
		unset( $properties[ 'locacaoSlug' ] );
		add_post_meta( $this->post_id, 'locacaoValor', (float)$locacaoValor, true );
		add_post_meta( $this->post_id, 'locacaoDataAtualizacao', $locacaoDataAtualizacao, true );
		add_post_meta( $this->post_id, 'locacaoNome', $locacaoNome, true );
		add_post_meta( $this->post_id, 'locacaoPromocao', $locacaoPromocao, true );
		add_post_meta( $this->post_id, 'locacaoProxAtualizacao', $locacaoProxAtualizacao, true );
		add_post_meta( $this->post_id, 'locacaoSlug', $locacaoSlug, true );
		return ['valor' => $locacaoValor, 'data' => new DateTime( $locacaoDataAtualizacao ) ];
	}
	private function salvar_metadados_imovel( ) {
		$properties = $this->recolhe_propriedades( $this->imovel );
		$dados_contrato = $this->salva_contrato_venda( $this->imovel );

		$valorCondominio = (float)$this->imovel[ 'valorCondominio' ];
		unset( $this->imovel[ 'valorCondominio' ] );
		$valorIptu = (float)$this->imovel[ 'valorIptu' ];
		unset( $this->imovel[ 'valorIptu' ] );
		/*
		 * Ajusta Condominio e IPTU para numerico
		*/
		add_post_meta( $this->post_id, 'valorCondominio', $valorCondominio, true );
		add_post_meta( $this->post_id, 'valorIptu', $valorIptu, true );
		/**/

		$valor = $dados_contrato['valor'];
		$data = $dados_contrato['data'];
		$dados_contrato = $this->salva_contrato_locacao( $this->imovel );
		if ( $dados_contrato['valor'] > $valor ) {
			$valor = $dados_contrato['valor'];
		}
		if ( $dados_contrato['data'] > $data ) {
			$data = $dados_contrato['data'];
		}
		if ( !isset( $properties['visitas'] ) ) {
			$properties['visitas'] = 0;
		}
		/* Quando o sistema pesquisar por todos os contratos
		 * precisa desta coluna para ordenar
		 * Caso contrário: Organiza pelo campo do contrato
		 * Aka: vendaValor
		*/
		add_post_meta( $this->post_id, 'valor', $valor, true );
		add_post_meta( $this->post_id, 'data', $data->format('Y-m-d H:i:s'), true );
		/**/
		$properties['clicks'] = $properties['visitas'];
        $latitude = 0;
        if ( isset( $properties['latitude'] ) ) {
            $latitude = (float)$properties['latitude'];
        }
        $longitude = 0;
        if ( isset( $properties['longitude'] ) ) {
            $longitude = (float)$properties['longitude'];
        }
		//

		foreach( $properties as $key => $value ) {
			if ( is_string( $value ) && trim( $value ) === '' ) {
				continue;
			}
			if ( is_numeric( $value ) && $value <= 0 ) {
				continue;
			}
			if ( ( is_array( $value ) || is_object( $value ) ) && empty( $value ) ) {
				continue;
			}
			add_post_meta( $this->post_id, $key, $value, true );
		}
		if ( $latitude != 0 && $longitude != 0 ) {
			add_post_meta( $this->post_id, 'latitude', $latitude, true );
			add_post_meta( $this->post_id, 'longitude', $longitude, true );
		}
		if ( isset( $this->imovel['tipoImovel'] ) ) {
			$tipoImovel = $this->imovel['tipoImovel'];
			if ( isset( $tipoImovel['nome'] ) )	add_post_meta( $this->post_id, 'tipoImovelNome', $tipoImovel['nome'], true );
		}
		/* Varre Dependencias */
		if ( isset( $this->imovel['dependencias'] ) ) {
			$dependencias = $this->imovel['dependencias'];
			$this->salvar_dependencias( $dependencias );
		}
	}
	private function apagar_metadados_imovel( ) {
		global $wpdb;
		if ( !get_post( $this->post_id ) ) {
			return 0;
		}
		$count = $wpdb->get_var( $wpdb->prepare( 
			"SELECT COUNT( * ) FROM $wpdb->postmeta WHERE post_id = %d",
			$this->post_id
		 ) );
		$wpdb->delete( 
			$wpdb->postmeta,
			array( 'post_id' => $this->post_id ),
			array( '%d' )
		 );
	}
	private function recolhe_propriedades( ) {
		$properties = array( );
		$set_propriedades = self::PROPRIEDADES;
		foreach ( $set_propriedades as $propriedade ) {
			if ( isset( $this->imovel[$propriedade] ) ) {
				$properties[ $propriedade ] = $this->imovel[ $propriedade ];
			}
		}
		return $properties;
	}
}
class Pinedu_Imovel_Importa_Taxonomias {
	const TERMOS = ['contrato', 'cidade', 'regiao', 'bairro', 'tipo-imovel'];
	private $imovel;
	private $post_id;
	public function __construct( $post_id, $imovel ) {
		$this->imovel = $imovel;
		$this->post_id = $post_id;
	}
	public function excluir( ) {
		$this->remove_post_taxonomias( );
	}
	public function salvar( ) {
		//
		$importa_contratos = new Pinedu_Imovel_Importa_Contratos( $this->post_id, $this->imovel );
		$importa_contratos->salvar( );
		//
		if ( isset( $this->imovel['tipoImovel_id'] ) ) {
			$tipo_imovel_id = (string)$this->imovel['tipoImovel_id'];
			unset( $this->imovel['tipoImovel_id'] );
			wp_set_object_terms( $this->post_id, $tipo_imovel_id, 'tipo-imovel', false );
		}
		if ( isset( $this->imovel['cidadeCorretagem'] ) ) {
			$cidadeCorretagem = (string)$this->imovel['cidadeCorretagem'];
			unset( $this->imovel['cidadeCorretagem'] );
			wp_set_object_terms( $this->post_id, $cidadeCorretagem, 'cidade', false );
		}
		if ( isset( $this->imovel['regiaoCorretagem'] ) ) {
			$regiaoCorretagem = (string)$this->imovel['regiaoCorretagem'];
			unset( $this->imovel['regiaoCorretagem'] );
			wp_set_object_terms( $this->post_id, $regiaoCorretagem, 'regiao', false );
		}
		if ( isset( $this->imovel['bairroCorretagem'] ) ) {
			$bairroCorretagem = (string)$this->imovel['bairroCorretagem'];
			unset( $this->imovel['bairroCorretagem'] );
			wp_set_object_terms( $this->post_id, $bairroCorretagem, 'bairro', false );
		}
	}
	public function atualizar( ) {
		$this->remove_post_taxonomias( );
		$this->salvar( );
	}
	private function recolhe_taxonomias( ) {
		$taxonomias = array( );
		$set_taxonomias = self::TERMOS;
		foreach ( $set_taxonomias as $taxonomia ) {
			if ( isset( $this->imovel[$taxonomia] ) ) {
				$taxonomias[ $taxonomia ] = $this->imovel[ $taxonomia ];
			}
		}
		return $taxonomias;
	}
	private function remove_post_taxonomias( ) {
		wp_delete_object_term_relationships($this->post_id, get_taxonomies());
	}
	function remove_all_term_meta_for_taxonomy($taxonomy) {
		if (!taxonomy_exists($taxonomy)) {
			error_log("Taxonomia '{$taxonomy}' não existe.");
			return 0;
		}
		$terms = get_terms(array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // Inclui termos mesmo sem posts
			'fields'     => 'ids', // Retorna apenas IDs (para eficiência)
		));
		if (is_wp_error($terms) || empty($terms)) {
			return 0;
		}
		$total_removed = 0;
		foreach ($terms as $term_id) {
			$meta_keys = get_term_meta($term_id); // Pega todas as chaves de meta
			if (!empty($meta_keys)) {
				foreach (array_keys($meta_keys) as $meta_key) {
					if (delete_term_meta($term_id, $meta_key)) {
						$total_removed++;
					}
				}
			}
		}
		return $total_removed;
	}
}
class Pinedu_Imovel_Importa_Contratos {
	private $imovel;
	private $post_id;

	public function __construct( $post_id, $imovel ) {
		$this->imovel = $imovel;
		$this->post_id = $post_id;
	}
	public function atualizar( ) {
		wp_remove_object_terms( $this->post_id, '1', 'contrato' );
		wp_remove_object_terms( $this->post_id, '2', 'contrato' );
		wp_remove_object_terms( $this->post_id, '3', 'contrato' );
		$this->salvar();
	}
	public function salvar( ) {
		if ( $this->imovel['ativarVenda'] === true ) {
			wp_set_object_terms( $this->post_id, '1', 'contrato', true );
			unset( $this->imovel['ativarVenda'] );
		}
		if ( $this->imovel['ativarLocacao'] === true ) {
			wp_set_object_terms( $this->post_id, '2', 'contrato', true );
			unset( $this->imovel['ativarLocacao'] );
		}
		if ( $this->imovel['ativarLancamento'] === true ) {
			wp_set_object_terms( $this->post_id, '3', 'contrato', true );
			unset( $this->imovel['ativarLancamento'] );
		}
	}
}
