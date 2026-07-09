<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';

class Pinedu_Imovel_Importa_Tipo_Dependencia extends Pinedu_Importa_Taxonomia_Base {
	public function __construct() {
		//$this->limpa('faixa_valor');
	}
	public function importa( $tipo_dependencia ) {
		if ( !taxonomy_exists( 'tipo-dependencia' ) ) {
			wp_send_json_error( ['message' => 'Taxonomia Tipo de Dependencia não existe!.'] );
			return;
		}
		foreach ( $tipo_dependencia as $tipo ) {
			$sigla = $tipo['sigla'];
			$nome = $tipo['nome'];
			$descricao = $tipo['descricao'];
			$relativo = $tipo['relativo'];
			$ordem = $tipo['ordem'];
			$tipo_campo = $tipo['tipo'];
            $pai = strtolower( trim( $tipo['pai'] ) );
			$key = $sigla;
			$nome = sanitize_text_field( $nome );
			$term = $this->salva( $key, $nome, 'tipo-dependencia' );
			if ( isset( $term['term_id'] ) ) {
				$id_term = $term[ 'term_id' ];
			} else {
				$id_term = $term;
			}

            $result = update_term_meta( $id_term, 'nome', $nome );
            $result = update_term_meta( $id_term, 'sigla', $sigla );
            $result = update_term_meta( $id_term, 'relativo', $relativo );
            $result = update_term_meta( $id_term, 'descricao', $descricao );
            $result = update_term_meta( $id_term, 'ordem', $ordem );
            $result = update_term_meta( $id_term, 'tipo', $tipo_campo );
            $result = add_term_meta( $id_term, 'tipo', $tipo_campo );
            $result = add_term_meta($id_term, 'tipo-imovel-pai', $pai);
		}
	}
    public static function list( $relativo = '' ) {
        if ( !taxonomy_exists( 'tipo-dependencia' ) ) {
           wp_send_json_error( ['message' => 'Taxonomia Tipo de dependencia não existe!.'] );
           return false;
        }

        $args = [
            'taxonomy'   => 'tipo-dependencia'
            , 'hide_empty' => false
            , 'orderby'    => 'meta_value'
            , 'meta_key'   => 'ordem'
            , 'order'      => 'ASC'
            , 'update_post_meta_cache' => true
            , 'update_post_term_cache' => true
        ];

        if ( !empty( $relativo ) ) {
           $args[ 'meta_query' ] = [
               [ 'key' => 'relativo', 'value' => $relativo, 'compare' => '=' ]
           ];
        }

        // 1. Gera uma chave única baseada nos argumentos exatos da busca
        $transient_key = 'pnd_dep_' . md5( serialize( $args ) );

        // 2. Tenta recuperar o resultado do cache
        $cached_terms = get_transient( $transient_key );

        // Se o cache existir (diferente de false), retorna ele imediatamente
        if ( false !== $cached_terms ) {
            return $cached_terms;
        }

        // 3. Executa a query no banco de dados, já que não tem cache
        $terms = get_terms( $args );

        // 4. Salva no cache por 125 minutos (evitando salvar erros do WP)
        if ( !is_wp_error( $terms ) ) {
            set_transient( $transient_key, $terms, 50 * MINUTE_IN_SECONDS );
        }

        return $terms;
    }
    /**
	 * Recolhe a Taxonomia tipo-dependencia, e devolve separada por RELATIVO
	 * @return array( 'CARACTERISTICAS' => $caracteristicas, 'CONDOMINIO' => $condominio, 'EDIFICIO' => $edificio, 'INFRAEXTRUTURA' => $infraextrutura );
	 */
	public static function get_tipo_dependencias() {
		$tipo_dependencias = self::list();

		$edificio = [];
		$condominio = [];
		$caracteristicas = [];
		$infraextrutura = [];

		// ==============================================================
		// A MÁGICA DO "1 HIT": Pré-carregamento em Lote (Eager Load) para Termos
		// ==============================================================
		if ( ! empty( $tipo_dependencias ) && ! is_wp_error( $tipo_dependencias ) ) {
			// 1. Extrai apenas a coluna 'term_id' de todos os objetos de termo
			// wp_list_pluck é uma função nativa e hiper-rápida do WP para isso.
			$term_ids = wp_list_pluck( $tipo_dependencias, 'term_id' );

			// 2. Comandamos o Eager Load dos metadados
			if ( ! empty( $term_ids ) ) {
				// Esta linha puxa a tabela wp_termmeta inteira de uma só vez
				// para todos os IDs da lista e joga no Object Cache (RAM).
				// Resultado: Zero consultas ao banco dentro do foreach abaixo!
				update_termmeta_cache( $term_ids );
			}
		}
		// ==============================================================

		foreach ( $tipo_dependencias as $tipo_dependencia ) {
			$meta = get_term_meta( $tipo_dependencia->term_id );

            // Utilizando "?? ''" (Null Coalescing) para evitar warnings do PHP
            // caso algum termo seja salvo acidentalmente sem um desses metadados
			$relativo = $meta['relativo'][0] ?? '';

			$td = [
                'sigla'     => $meta['sigla'][0] ?? '',
                'nome'      => $meta['nome'][0] ?? '',
                'descricao' => $meta['descricao'][0] ?? '',
                'relativo'  => $relativo,
                'tipo'      => $meta['tipo'][0] ?? ''
            ];

			switch ( strtoupper( $relativo ) ) { // strtoupper garante que não vai falhar por case-sensitive
				case 'CARACTERISTICAS':
					$caracteristicas[] = $td;
					break;
				case 'CONDOMINIO':
					$condominio[] = $td;
					break;
				case 'EDIFICIO':
					$edificio[] = $td;
					break;
				case 'INFRAEXTRUTURA':
					$infraextrutura[] = $td;
					break;
				default:
					break;
			}
		}

		return array(
            'CARACTERISTICAS' => $caracteristicas,
            'CONDOMINIO'      => $condominio,
            'EDIFICIO'        => $edificio,
            'INFRAEXTRUTURA'  => $infraextrutura
        );
	}
}
