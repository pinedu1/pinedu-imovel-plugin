<?php
require_once plugin_dir_path( __FILE__ ) . 'Pinedu_Importa_Taxonomia_Base.php';
/**
 * Classe responsável pela importação da taxonomia 'bairro' vinculada à região pai.
 *
 * @author Eduardo Pinheiro da Silva
 */
class Pinedu_Imovel_Importa_Bairro extends Pinedu_Importa_Taxonomia_Base {
	private $ids_afetados = [ ];
	/**
	 * Construtor padrão da classe de importação de bairros.
	 */
	public function __construct( ) {
		//$this->limpa('bairro');
	}
	/**
	 * Importa a lista de bairros vinculando o metadado 'parent_id' à região pai correspondente.
	 *
	 * @param array $bairros Lista de bairros pertencentes à região.
	 * @param string $regiao_pai Identificador (slug/key) da região pai.
	 * @return bool Retorna true ao concluir o processamento do lote de bairros ou false se a taxonomia não existir.
	 */
	public function importa_bairros( $bairros, $regiao_pai ) {
		if ( ! taxonomy_exists( 'bairro' ) ) {
			wp_send_json_error( [ 'message' => 'Taxonomia Bairro não existe!.' ] );
			return false;
		}
		if ( empty( $bairros ) || ! is_array( $bairros ) ) {
			return false;
		}
		foreach ( $bairros as $bairro ) {
			$key = ( string ) $bairro['id'];
			$nome = sanitize_text_field( $bairro['nome'] );
			$term = $this->salva( $key, $nome, 'bairro' );
			if ( ! is_wp_error( $term ) ) {
				$id_term = isset( $term['term_id'] ) ? ( int ) $term['term_id'] : ( int ) $term;
				if ( $id_term > 0 ) {
					$this->ids_afetados[] = $id_term;
					update_term_meta( $id_term, 'parent_id', $regiao_pai );
				}
			}
		}
		return true;
	}
	/**
	 * Executa a limpeza consolidada do cache de termos da taxonomia 'bairro' após o término da transação principal.
	 *
	 * @return void
	 */
	public function limpar_cache_termos( ) {
		if ( ! empty( $this->ids_afetados ) ) {
			clean_term_cache( array_unique( $this->ids_afetados ), 'bairro' );
			$this->ids_afetados = [ ];
		}
	}
}