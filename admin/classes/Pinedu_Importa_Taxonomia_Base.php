<?php

abstract class Pinedu_Importa_Taxonomia_Base {

    protected function salva( $key, $nome, $taxonomy ) {
        // 1. Força o slug para minúsculo. O WP exige isso para não criar loops de inserção falhos.
        $key = strtolower( trim( $key ) );

        $existing_terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'slug'       => $key
        ] );

        if ( !empty( $existing_terms ) && !is_wp_error( $existing_terms ) ) {
            $term_obj = $existing_terms[0];

            // Atualiza o termo
            $result = wp_update_term( $term_obj->term_id, $taxonomy, [
                'name'        => $nome,
                'slug'        => $key,
                'description' => $nome . '(s)'
            ] );

            // Se o update falhar por alguma restrição do WP, garantimos o retorno do ID original
            if ( is_wp_error( $result ) ) {
                return [ 'term_id' => $term_obj->term_id ];
            }
            return $result;

        } else {
            // Tenta inserir um novo termo
            $result = wp_insert_term( $nome, $taxonomy, [
                'slug'        => $key,
                'description' => $nome . '(s)'
            ] );

            // 2. Se falhar, tratamos o erro para não engolir a linha
            if ( is_wp_error( $result ) ) {
                $error_code = $result->get_error_code();

                // Se o erro for 'term_exists', significa que o NOME já foi usado por outro tipo de imóvel
                if ( $error_code === 'term_exists' ) {
                    // Modificamos levemente o nome para torná-lo único (Ex: "Lote (CV)")
                    $prefixo = strtoupper( explode('-', $key)[0] );
                    $nome_unico = $nome . ' (' . $prefixo . ')';

                    $result = wp_insert_term( $nome_unico, $taxonomy, [
                        'slug'        => $key,
                        'description' => $nome . '(s)'
                    ] );
                }

                // Se mesmo com a contingência ele retornar erro, evitamos que o script quebre
                if ( is_wp_error( $result ) ) {
                    error_log("Falha crítica ao importar: $nome - " . $result->get_error_message());
                    return [ 'term_id' => 0 ]; // Retorna 0 para os metadados rodarem no vazio, mas não travarem o loop
                }
            }

            return $result;
        }
    }

    protected function salva_child( $key, $nome, $taxonomy, $parent_key, $parent_property) {
        $key = strtolower( trim( $key ) );

        $existing_terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'slug'       => $key
        ] );

        if ( !empty( $existing_terms ) && !is_wp_error( $existing_terms ) ) {
            $term_obj = $existing_terms[0];
            $id_term = $term_obj->term_id;

            wp_update_term( $id_term, $taxonomy, [
                'name'        => $nome,
                'slug'        => $key,
                'description' => $nome . '(s)'
            ] );

            // O update_term_meta nativo já insere o dado caso não exista, e atualiza caso exista
            update_term_meta( $id_term, $parent_property, $parent_key );

        } else {
            $term = wp_insert_term( $nome, $taxonomy, [
                'slug'        => $key,
                'description' => $nome . '(s)'
            ] );

            if ( !is_wp_error( $term ) ) {
                update_term_meta( $term['term_id'], $parent_property, $parent_key );
            }
        }
    }

    public function limpa( $taxonomy ) {
        if ( !taxonomy_exists( $taxonomy ) ) {
            wp_send_json_error( ['message' => 'Taxonomia ' . $taxonomy . ' não existe!.'] );
            return;
        }
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]);
        if ( !is_wp_error( $terms ) && !empty( $terms ) ) {
            foreach ( $terms as $term ) {
                wp_delete_term( $term->term_id, $taxonomy );
            }
        }
    }
}