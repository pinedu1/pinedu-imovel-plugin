<?php

abstract class Pinedu_Importa_Taxonomia_Base {
	protected function salva( $key, $nome, $taxonomy ) {
		//error_log('salva: ' . $key . ' - ' . $nome . ' - ' .$taxonomy );
		$existing_terms = get_terms( [
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'slug' => $key
		] );
		if ( !empty( $existing_terms ) && !is_wp_error( $existing_terms ) ) {
			$term = $existing_terms[0];
			//$term = $term->term_id;
			// Atualiza o termo
			$term = wp_update_term( $term->term_id, $taxonomy, [
				'name' => $nome, 'slug' => $key, 'description' => $nome . '(s)'
			] );
		} else {
			$term = wp_insert_term( $nome, $taxonomy, [
				'name' => $nome, 'slug' => $key, 'description' => $nome . '(s)'
			] );
		}
		return $term;
	}
	protected function salva_child( $key, $nome, $taxonomy, $parent_key, $parent_property) {
		$existing_terms = get_terms( [
			'taxonomy' => $taxonomy
			, 'hide_empty' => false
			, 'slug' => $key
		] );
		if ( !empty( $existing_terms ) && !is_wp_error( $existing_terms ) ) {
			$term = $existing_terms[0];
			$id_term = $term->term_id;

			// Atualiza o termo
			$term = wp_update_term( $id_term, $taxonomy, [
				'name' => $nome,
				'slug' => $key,
				'description' => $nome . '(s)'
			] );

			if ( !is_wp_error( $term ) ) {
				if ( !metadata_exists( 'term', $id_term, $parent_property ) ) {
					$result = update_term_meta( $id_term, $parent_property, $parent_key, $parent_property );
				} else {
					$result = add_term_meta( $id_term, $parent_property, $parent_key );
				}
			}
		} else {
			$term = wp_insert_term( $nome, $taxonomy, [
				'slug' => $key,
				'description' => $nome . '(s)'
			] );

			if ( !is_wp_error( $term ) ) {
				$id_term = $term['term_id'];
				$result = add_term_meta( $id_term, $parent_property, $parent_key );
			}
		}
	}
	public function limpa($taxonomy) {
		if ( !taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( ['message' => 'Taxonomia ' . $taxonomy . ' não existe!.'] );
			return;
		}
		$terms = get_terms([
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
		]);
		if (!is_wp_error($terms) && !empty($terms)) {
			foreach ($terms as $term) {
				wp_delete_term($term->term_id, $taxonomy);
			}
		}
	}
}