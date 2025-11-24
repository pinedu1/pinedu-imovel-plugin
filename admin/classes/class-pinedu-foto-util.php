<?php
require_once plugin_dir_path(__FILE__) . 'class-pinedu-foto-util.php';
class Pinedu_Imovel_Importa_Foto_Loja extends Pinedu_Foto_Util {
	private $loja;
	private $post_id;
	public function __construct( $post_id, $loja ) {
		$this->loja = $loja;
		$this->post_id = $post_id;
	}
	public function salva_imagem_destaque( ) {
		$imagem_destaque = $this->resolve_imagem_destaque( );
		if ( $imagem_destaque && ! empty( $imagem_destaque ) ) {
			$this->salva_post_imagem( $imagem_destaque );
		}
	}
	public function atualiza_imagem_destaque( ) {
		$this->delete_post_thumbnail( $this->post_id );
		$imagem_destaque = $this->resolve_imagem_destaque( );
		if ( $imagem_destaque && ! empty( $imagem_destaque ) ) {
			$this->salva_post_imagem( $imagem_destaque );
		}
	}
	private function resolve_imagem_destaque( ) {
		return $this->loja['logo'];
	}
	private function salva_post_imagem( $imagem_destaque ) {
		$image_ulr = $this->loja['logo'];
		$alt_text = $this->loja['p_fotoPath'];
		$title = $this->loja['p_fotoNome'];
		$description = $this->loja['p_fotoNome'];
		$label = $this->loja['p_fotoNome'];
		$attachment_id = $this->importa_foto( $image_ulr, $this->loja['p_fotoNome'], $title, $alt_text );
		if ( $this->post_id && $attachment_id ) {
			set_post_thumbnail( $this->post_id, $attachment_id );
		}
	}
	private function delete_post_thumbnail( ) {
		$thumbnail_id = get_post_thumbnail_id( $this->post_id );
		if ( $thumbnail_id ) {
			$removeu_thumbnail = delete_post_thumbnail( $this->post_id );
			if ( !$removeu_thumbnail ) {
				return new WP_Error( 'falha_remocao', 'Falha ao remover o featured image do post.' );
			}
			$deletou_midia = wp_delete_attachment( $thumbnail_id, true );
			if ( !$deletou_midia ) {
				return new WP_Error( 'falha_delecao', 'O featured image foi removido do post, mas houve falha ao deletar o arquivo de mídia.' );
			}
			return true;
		}
		return false;
	}
}
class Pinedu_Imovel_Importa_Foto_Corretor extends Pinedu_Foto_Util {
	private $corretor;
	private $post_id;
	public function __construct( $post_id, $corretor ) {
		$this->corretor = $corretor;
		$this->post_id = $post_id;
	}
	public function salva_imagem_destaque( ) {
		$imagem_destaque = $this->resolve_imagem_destaque( );
		if ( $imagem_destaque && ! empty( $imagem_destaque ) ) {
			$this->salva_post_imagem( $imagem_destaque );
		}
	}
	public function atualiza_imagem_destaque( ) {
		$this->delete_post_thumbnail( $this->post_id );
		$imagem_destaque = $this->resolve_imagem_destaque( );
		if ( $imagem_destaque && ! empty( $imagem_destaque ) ) {
			$this->salva_post_imagem( $imagem_destaque );
		}
	}
	private function resolve_imagem_destaque( ) {
		return $this->corretor['foto'];
	}
	private function salva_post_imagem( $imagem_destaque ) {
		$image_ulr = $this->corretor['fotoNormal'];
		$alt_text = $this->corretor['p_fotoPath'];
		$title = $this->corretor['p_fotoNome'];
		$description = $this->corretor['p_fotoNome'];
		$label = $this->corretor['p_fotoNome'];
		$attachment_id = $this->importa_foto( $image_ulr, $this->corretor['p_fotoNome'], $title, $alt_text );
		if ( $this->post_id && $attachment_id ) {
			set_post_thumbnail( $this->post_id, $attachment_id );
		}
	}
	private function delete_post_thumbnail( ) {
		$thumbnail_id = get_post_thumbnail_id( $this->post_id );
		if ( $thumbnail_id ) {
			$removeu_thumbnail = delete_post_thumbnail( $this->post_id );
			if ( !$removeu_thumbnail ) {
				return new WP_Error( 'falha_remocao', 'Falha ao remover o featured image do post.' );
			}
			$deletou_midia = wp_delete_attachment( $thumbnail_id, true );
			if ( !$deletou_midia ) {
				return new WP_Error( 'falha_delecao', 'O featured image foi removido do post, mas houve falha ao deletar o arquivo de mídia.' );
			}
			return true;
		}
		return false;
	}
}
class Pinedu_Imovel_Importa_Foto_Batch extends Pinedu_Foto_Util {
    private $post_id;
    public function salva_imagens_destaque( $apagar_destaque = true ) {
        $args = array(
            'post_type'  => 'imovel',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query' => array(
                array(
                    'key'     => 'imagem_destaque',
                    'compare' => 'EXISTS', // Apenas verifica a existência da chave
                ),
            ),
        );
        $query = new WP_Query( $args );
        $this->salva_imagens_destaque_query( $query, $apagar_destaque );
        wp_reset_postdata();
    }
    public function salva_imagens_destaque_query( $query, $apagar_destaque = true ) {
        if ( ! $query->have_posts( ) ) {
            if (is_development_mode()) {
                error_log('Conjunto de Destaques Vazio:');
            }
            return true;
        }
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $this->post_id = $post->ID;
                if ( has_post_thumbnail( $post->ID ) ) {
                    continue;
                }
                $imagem_meta = get_post_meta( $this->post_id, 'imagem_destaque', true );
                if ( !is_array( $imagem_meta ) || empty( $imagem_meta ) ) {
                    if (is_development_mode()) {
                        error_log( 'Imagem_Meta_Post_id Vazia:' . $post->ID . ' / ' . print_r( $imagem_meta, true ) );
                    }
                    $imagem_meta = get_post_meta( $this->post_id, 'fotos', true );
                    if ( !is_array( $imagem_meta ) || empty( $imagem_meta ) ) {
                        if (is_development_mode()) {
                            error_log( 'Imagem_Meta_Post_id Vazia_1:' . $post->ID . ' / ' . print_r( $imagem_meta, true ) );
                        }
                        continue;
                    }
                }
                //error_log( 'Imagem_Meta' . print_r( $imagem_meta, true ) );
                $foto = [
                    'nome' => $imagem_meta['title'],
                    'descricao' => $imagem_meta['description'],
                    'big'   => $imagem_meta['url'],
                ];
                $destaque_id = $this->salva_post_imagem( $foto );
                if ( $destaque_id ) {
                    if ($apagar_destaque === true) $this->delete_destaque_term( $this->post_id );
                } else {
                    error_log( 'Erro ao salvar post imagem' . print_r( $foto, true ) );
                }
            }
        }
    }
    public function salva_imagens_fotos( ) {
        $args = array(
            'post_type'      => 'imovel',
            'posts_per_page' => -1, // Retorna todos os posts
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'fotos',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $query = new WP_Query( $args );
        //error_log( '$query->have_posts()' . print_r( $query->have_posts(), true ) );
        if ( $query->have_posts( ) ) {
            foreach ( $query->posts as $post ) {
                $this->post_id = $post->ID;
                $this->importa_fotos_post( $post );
            }
        }
        wp_reset_postdata();
    }
    public function importa_fotos_destaque( $post, $delete_fotos = true ) {
        $success = true;
        $this->post_id = $post->ID;
        $fotos_array = get_post_meta( $this->post_id, 'fotos', false );
        if ( is_array( $fotos_array ) && ! empty( $fotos_array ) ) {
            foreach ( $fotos_array as $foto_meta ) {
                if ( $foto_meta && is_array( $foto_meta ) ) {
                    $success = $success && $this->salvar_fotografia( $foto_meta );
                }
            }
            if ( ( $success === true ) && ( $delete_fotos === true ) ) {
                $ret = delete_post_meta( $this->post_id, 'fotos' );
            }
        }
        return $success;
    }
    public function importa_fotos_post( $post, $delete_fotos = true ) {
        $success = true;
        $this->post_id = $post->ID;
        $fotos_array = get_post_meta( $this->post_id, 'fotos', false );
        if ( is_array( $fotos_array ) && ! empty( $fotos_array ) ) {
            foreach ( $fotos_array as $foto_meta ) {
                if ( $foto_meta && is_array( $foto_meta ) ) {
                    $success = $success && $this->salvar_fotografia( $foto_meta );
                }
            }
            if ( ( $success === true ) && ( $delete_fotos === true ) ) {
                $ret = delete_post_meta( $this->post_id, 'fotos' );
            }
        }
        return $success;
    }
    private function delete_image( $image_id ) {
        $deletou_midia = wp_delete_attachment( $image_id, true );
        if ( !$deletou_midia ) {
            return new WP_Error( 'falha_delecao_foto', 'A imagem foi removida do post, mas houve falha ao deletar o arquivo de mídia.' );
        }
        return true;
    }

    /**
     * Exclui o único meta campo de destaque ('imagem_destaque') do post.
     * A exclusão só ocorre se o meta campo existir.
     *
     * @return bool Retorna true em caso de sucesso na exclusão, false se não existir ou falha no DB.
     */
    private function delete_destaque_term( $post_id ) {
        $meta_key_slug = 'imagem_destaque';
        if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
            if (is_development_mode()) {
                error_log('Erro: ID do Post inválido ou ausente em delete_destaque_term.');
            }
            return false;
        }
        $existe_meta = get_post_meta( $post_id, $meta_key_slug, true );
        if (is_development_mode()) {
            error_log('Existe Meta: ' . print_r($existe_meta, true) . ', post-id' . $post_id);
        }
        if ( empty( $existe_meta ) ) {
            if (is_development_mode()) {
                error_log('Aviso: Meta campo fixo "' . $meta_key_slug . '" não existe no post. Exclusão não necessária., post-id' . $post_id);
            }
            return false;
        }
        $resultado = delete_post_meta(
            $post_id, // ID do Post
            $meta_key_slug  // A chave FIXA: 'imagem_destaque'
        );
        if ( $resultado === true ) {
            return true;
        }
        error_log( 'Erro DB: Falha na exclusão do meta campo "' . $meta_key_slug . '" (Post ID: ' . $this->post_id . ').' );
        return false;
    }

    private function salva_post_imagem( $imagem_destaque ) {
        $image_ulr = $imagem_destaque['big'];
        $alt_text = $imagem_destaque['nome'];
        $title = $imagem_destaque['nome'];
        $attachment_id = $this->importa_foto( $image_ulr, $imagem_destaque['nome'], $title, $alt_text );
        if ( $this->post_id && $attachment_id ) {
            $thumbnail_id = set_post_thumbnail( $this->post_id, $attachment_id );
            if ($thumbnail_id === false) {
                error_log( 'Erro: Falha ao definir a imagem como thumbnail do post (Post ID: ' . $this->post_id . ').' );
                return false;
            }
        }
        return $attachment_id;
    }
    private function salvar_fotografia( $foto ):bool {
        $foto_id = $foto[ 'id' ];
        $nome = $foto[ 'nome' ];
        $descricao = $foto[ 'descricao' ];
        $ordem= $foto[ 'ordem' ];
        $fachada = false;
        //
        $image_ulr = $foto['big'];
        $alt_text = $nome;
        $title = $nome;
        $attachment_id = $this->importa_foto( $image_ulr, $foto['nome'], $title, $alt_text );
        if ( $attachment_id ) {
            $valor = [ 'nome' => $nome, 'descricao' => $descricao, 'ordem' => $ordem, 'fachada' => $fachada, 'foto_id' => $foto_id, 'id' => $attachment_id];
            $pm = add_post_meta( $this->post_id, 'fotografias', $valor, false );
            $x=$pm;
            if (is_development_mode()) {
                error_log('Foto: ' . $foto['nome'] . ' - ' . print_r($pm, true));
            }
            return true;
        }
        return false;
    }

}
class Pinedu_Imovel_Importa_Foto extends Pinedu_Foto_Util {
	private $imovel;
	private $post_id;
	public function __construct( $post_id, $imovel ) {
		$this->imovel = $imovel;
		$this->post_id = $post_id;
	}

    public function salva_imagem_destaque( $silent_mode = false ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( is_development_mode() ) {
            error_log('Salva Imagem Destaque: ' . print_r( $options, true ));
        }
        $imagem_destaque = $this->resolve_imagem_destaque( );
        if ( $imagem_destaque ) {
            if ($imagem_destaque && !empty($imagem_destaque)) {
                if (!isset($options['fotos_demanda']) || (!$options['fotos_demanda'])) {
                    $this->salva_post_imagem($imagem_destaque, $silent_mode);
                } else {
                    $this->salva_destaque_term($imagem_destaque);
                }
            }
        }
    }
    public function salvar_fotografias( $silent_mode = false ) {
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( is_development_mode() ) {
            error_log('Salva Imagem Destaque: ' . print_r( $options, true ));
        }
        $fotografias = $this->imovel[ 'fotos' ];
        if ( empty( $fotografias ) ) return false;
        foreach( $fotografias as $foto ) {
            if ( ! isset( $options['fotos_demanda'] ) || ( ! $options['fotos_demanda'] ) ) {
                $this->salvar_fotografia( $foto, $silent_mode );
            } else {
                $this->salvar_fotografia_term( $foto );
            }
        }
    }

    /**
     * Salva o único meta campo de destaque no post, usando add_post_meta().
     * ESTA FUNÇÃO SÓ ADICIONARÁ O CAMPO NA PRIMEIRA VEZ (meta_key única).
     *
     * @param array $imagem_destaque Array associativo contendo os dados do destaque.
     * @return bool Retorna true em caso de sucesso (adicionado), false se o campo já existe ou falha.
     */
    private function salva_destaque_term( $imagem_destaque ) {
        $meta_key_slug = 'imagem_destaque';
        if ( empty( $this->post_id ) || ! is_numeric( $this->post_id ) ) {
            if (is_development_mode()) {
                error_log('Erro: ID do Post inválido ou ausente em salva_destaque_term.');
            }
            return false;
        }
        if ( ! is_array( $imagem_destaque ) || empty( $imagem_destaque['big'] ) || empty( $imagem_destaque['id'] ) ) {
            if (is_development_mode()) {
                error_log('Erro: Dados de destaque (array) inválidos, URL principal (big) ou ID (id) ausentes.');
            }
            return false;
        }
        $imagem_url_sanitizada = esc_url_raw( $imagem_destaque['big'] );
        $imagem_id_sanitizado  = sanitize_key( $imagem_destaque['id'] );
        if ( empty( $imagem_url_sanitizada ) || empty( $imagem_id_sanitizado ) ) {
            if (is_development_mode()) {
                error_log('Erro: URL ou ID da imagem inválidos após a sanitização.');
            }
            return false;
        }
        $dados_para_salvar = array(
            'id'          => $imagem_id_sanitizado,
            'url'         => $imagem_url_sanitizada,
            'alt_text'    => sanitize_text_field( $imagem_destaque['nome'] ),
            'title'       => sanitize_text_field( $imagem_destaque['nome'] ),
            'description' => sanitize_textarea_field( $imagem_destaque['descricao'] ),
            'label'       => sanitize_text_field( $imagem_destaque['nome'] ),
        );
        $meta_id = add_post_meta(
            $this->post_id,         // ID do Post
            $meta_key_slug,         // A chave FIXA: 'imagem_destaque'
            $dados_para_salvar,     // O array de dados (serializado automaticamente)
            true                    // TRUE: Garante que esta chave é única
        );
        if ( $meta_id === false ) {
            // Falha: O meta campo já existe ou houve um erro no DB.
            // Se ele já existe, isso é esperado ao usar TRUE como quarto parâmetro.
            error_log( 'Aviso: Meta campo fixo "' . $meta_key_slug . '" já existe ou falha na adição (Post ID: ' . $this->post_id . ').' );
            return false;
        }
        return true;
    }
    /**
     * Salva/Atualiza o único meta campo de destaque no post (Upsert).
     *
     * @param array $imagem_destaque Array associativo contendo os dados do destaque.
     * @return bool Retorna true em caso de sucesso (adicionado ou atualizado), false em caso de falha.
     */
    private function update_destaque_term( $imagem_destaque ) {
        $meta_key_slug = 'imagem_destaque';
        if ( empty( $this->post_id ) || ! is_numeric( $this->post_id ) ) {
            error_log( 'Erro: ID do Post inválido ou ausente em update_destaque_term.' );
            return false;
        }
        if ( ! is_array( $imagem_destaque ) || empty( $imagem_destaque['big'] ) || empty( $imagem_destaque['id'] ) ) {
            error_log( 'Erro: Dados de destaque (array) inválidos, URL principal (big) ou ID (id) ausentes.' );
            return false;
        }
        $existe_meta = get_post_meta( $this->post_id, $meta_key_slug, true );
        if ( empty( $existe_meta ) ) {
            return $this->salva_destaque_term( $imagem_destaque );
        }
        $imagem_id_sanitizado = sanitize_key( $imagem_destaque['id'] );
        $imagem_url_sanitizada = esc_url_raw( $imagem_destaque['big'] );
        if ( empty( $imagem_url_sanitizada ) || empty( $imagem_id_sanitizado ) ) {
            error_log( 'Erro: URL ou ID da imagem inválidos após a sanitização.' );
            return false;
        }
        $dados_para_salvar = array(
            'id'          => $imagem_id_sanitizado,
            'url'         => $imagem_url_sanitizada,
            'alt_text'    => sanitize_text_field( $imagem_destaque['nome'] ),
            'title'       => sanitize_text_field( $imagem_destaque['nome'] ),
            'description' => sanitize_textarea_field( $imagem_destaque['descricao'] ),
            'label'       => sanitize_text_field( $imagem_destaque['nome'] ),
        );
        $resultado = update_post_meta(
            $this->post_id,
            $meta_key_slug, // A chave FIXA: 'imagem_destaque'
            $dados_para_salvar
        );
        if ( $resultado === false ) {
            error_log( 'Erro DB: Falha ao executar update_post_meta para a chave "' . $meta_key_slug . '" (Post ID: ' . $this->post_id . ').' );
            return false;
        }
        return true; // Sucesso na adição ou atualização
    }
    /**
     * Exclui o único meta campo de destaque ('imagem_destaque') do post.
     * A exclusão só ocorre se o meta campo existir.
     *
     * @return bool Retorna true em caso de sucesso na exclusão, false se não existir ou falha no DB.
     */
    private function delete_destaque_term( $post_id ) {
        $meta_key_slug = 'imagem_destaque';
        if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
            error_log( 'Erro: ID do Post inválido ou ausente em delete_destaque_term.' );
            return false;
        }
        $existe_meta = get_post_meta( $post_id, $meta_key_slug, true );
        if ( empty( $existe_meta ) ) {
            error_log( 'Aviso: Meta campo fixo "' . $meta_key_slug . '" não existe no post. Exclusão não necessária.' );
            return false;
        }
        $resultado = delete_post_meta(
            $post_id, // ID do Post
            $meta_key_slug  // A chave FIXA: 'imagem_destaque'
        );
        if ( $resultado === true ) {
            return true;
        }
        error_log( 'Erro DB: Falha na exclusão do meta campo "' . $meta_key_slug .
            '" (Post ID: ' . $this->post_id . ').' );
        return false;
    }
	public function exclui_imagem_destaque( ) {
		//$this->delete_post_thumbnail( $this->post_id );
        $this->delete_destaque_term( $this->post_id );
	}
	public function atualiza_imagem_destaque( $silent_mode = false ) {
		$this->delete_post_thumbnail( $this->post_id );
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( is_development_mode() ) {
            error_log('Salva Imagem Destaque: ' . print_r( $options, true ));
        }
		$imagem_destaque = $this->resolve_imagem_destaque( );
        if ( $imagem_destaque ) {
            if (!isset($options['fotos_demanda']) || (!$options['fotos_demanda'])) {
                $this->salva_post_imagem( $imagem_destaque, $silent_mode);
            } else {
                $this->update_destaque_term($imagem_destaque);
            }
        }
	}
	private function resolve_imagem_destaque( ) {
		if ( isset( $this->imovel['fotos'] ) && !empty( $this->imovel['fotos'] ) ) {
			$fotografias = $this->imovel['fotos'];
			if ( $fotografias != null && !empty( $fotografias ) ) {
				foreach ( $fotografias as $foto ) {
					if ( $foto['fotoBanner'] == true ) {
						return $foto;
					}
				}
			}
			return $fotografias[ 0 ];
		}
		return false;
	}
	private function salva_post_imagem( $imagem_destaque, $silent_mode = false ) {
        if ( is_development_mode( ) ) {
            error_log('Buscando Imagem Destaque:' . print_r($imagem_destaque, true));
        }
        $exibir = $imagem_destaque[ 'exibeInternet' ];
        if ( $exibir !== true ) return false;
        //
        $nome = $imagem_destaque[ 'nome' ];
        $image_url = sanitize_url( $imagem_destaque['big'] );
        if ( ! function_exists( 'download_url' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }
        if ( ! function_exists( 'wp_read_image_metadata' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }
        $tmp_name = download_url( $image_url );

        if ( is_wp_error( $tmp_name ) ) {
            if ( $silent_mode === true ) {
                error_log('1 - Falha ao baixar a URL remota: ' . $tmp_name->get_error_message() );
                return false;
            }
            wp_send_json_error( [ 'message' => 'Falha ao baixar a URL remota: ' . $tmp_name->get_error_message() ] );
            wp_die();
        }

        $file_array = array(
            'name'     => $nome,
            'tmp_name' => $tmp_name
        );

        // media_handle_sideload (Processamento e Criação do Anexo)
        $image_id = media_handle_sideload( $file_array, $this->post_id );

        // 3. Salvamento no Campo Personalizado (Ação Requerida)

        if ( is_wp_error( $image_id ) ) {
            @unlink( $file_array['tmp_name'] );
            if ( $silent_mode === true ) {
                error_log('2 - Falha ao processar a imagem: ' . $image_id->get_error_message() );
                return false;
            }
            wp_send_json_error( [ 'message' => 'Falha ao processar a imagem: ' . $image_id->get_error_message() ] );
            wp_die();
        } else {
            $pm = set_post_thumbnail( $this->post_id, $image_id );

            if ( $pm === false ) {
                if ( $silent_mode === true ) {
                    error_log('3 - Falha ao salvar metadados (Destaque pm).' );
                    return false;
                }
                wp_send_json_error( [ 'message' => 'Falha ao salvar metadados (Destaque).' ] );
                wp_die();
            }
        }

/*		$image_ulr = $imagem_destaque['big'];
		$alt_text = $imagem_destaque['nome'] ?? '';
		$title = $imagem_destaque['nome'] ?? '';
		$description = $imagem_destaque['descricao'] ?? '';
		$label = $imagem_destaque['nome'] ?? '';
		$attachment_id = $this->importa_foto( $image_ulr, $label, $title, $alt_text, $silent_mode );
        if ($attachment_id === false) {
            return false;
        }
		if ( $this->post_id && $attachment_id ) {
			set_post_thumbnail( $this->post_id, $attachment_id );
		}*/
		return $image_id;
	}
	public function apagar_fotografias( $fotografias ) {
		foreach( $fotografias as $foto ) {
			$this->delete_image( $foto['id'] );
		}
	}
	private function find_by_foto_id( $foto_id, $fotografias_post ) {
		foreach( $fotografias_post as $foto ) {
			if ( $foto['foto_id'] == $foto_id ) return $foto;
		}
		return false;
	}
	public function atualizar_fotografias( $fotografias_post, $silent_mode = false ) {
        if ( !empty( $fotografias_post ) ) {
            $this->apagar_fotografias( $fotografias_post );
        }
		$fotografias = $this->imovel[ 'fotos' ];
		if ( empty( $fotografias ) ) {
			return false;
		}
        $options = get_option( 'pinedu_imovel_options', [] );
		foreach( $fotografias as $foto ) {
            if ( ! isset( $options['fotos_demanda'] ) || ( ! $options['fotos_demanda'] ) ) {
                $this->salvar_fotografia( $foto, $silent_mode );
            } else {
                $this->salvar_fotografia_term( $foto );
            }
		}
	}
	public function excluir_fotografias( $fotografias_post ) {
		if ( !empty( $fotografias_post ) ) {
			$this->apagar_fotografias( $fotografias_post );
		}
	}
    private function salvar_fotografia( $foto, $silent_mode = false ) {
        if ( is_development_mode( ) ) {
            error_log('Buscando Foto:' . print_r($foto, true));
        }
        $exibir = $foto[ 'exibeInternet' ];
        if ( $exibir !== true ) return false;
        $nome = $foto[ 'nome' ];
        $image_url = sanitize_url( $foto['big'] );
        if ( ! function_exists( 'download_url' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }
        if ( ! function_exists( 'wp_read_image_metadata' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }
        $tmp_name = download_url( $image_url );

        if ( is_wp_error( $tmp_name ) ) {
            if ( $silent_mode === true ) {
                error_log('1 - Falha ao baixar a URL remota: ' . $tmp_name->get_error_message() );
                return false;
            }
            wp_send_json_error( [ 'message' => 'Falha ao baixar a URL remota: ' . $tmp_name->get_error_message() ] );
            wp_die();
        }

        $file_array = array(
            'name'     => $nome,
            'tmp_name' => $tmp_name
        );

        // media_handle_sideload (Processamento e Criação do Anexo)
        $image_id = media_handle_sideload( $file_array, $this->post_id );

        // 3. Salvamento no Campo Personalizado (Ação Requerida)
        if ( is_wp_error( $image_id ) ) {
            @unlink( $file_array['tmp_name'] );
            if ( $silent_mode === true ) {
                error_log('2 - Falha ao processar a imagem: ' . $image_id->get_error_message() );
                return false;
            }
            wp_send_json_error( [ 'message' => 'Falha ao processar a imagem: ' . $image_id->get_error_message() ] );
            wp_die();
        } else {
            $foto_id = $foto[ 'id' ];
            $nome = $foto[ 'nome' ];
            $descricao = $foto[ 'descricao' ];
            $ordem= $foto[ 'ordem' ];
            $fachada = $foto[ 'fotoBanner' ];

            $valor = [ 'nome' => $nome, 'descricao' => $descricao, 'ordem' => $ordem, 'fachada' => $fachada, 'foto_id' => $foto_id, 'id' => $image_id];
            $pm = add_post_meta( $this->post_id, 'fotografias', $valor, false );

            if ( $pm === false ) {
                if ( $silent_mode === true ) {
                    error_log('3 - Falha ao salvar metadados (fotografias pm).' );
                    return false;
                }
                wp_send_json_error( [ 'message' => 'Falha ao salvar metadados (fotografias).' ] );
                wp_die();
            }
        }
/*        $exibir = $foto[ 'exibeInternet' ];
        if ( !$exibir ) return false;
        $foto_id = $foto[ 'id' ];
        $nome = $foto[ 'nome' ];
        $descricao = $foto[ 'descricao' ];
        $ordem= $foto[ 'ordem' ];
        $fachada = $foto[ 'fotoBanner' ];
        //
        $image_ulr = $foto['big'];
        $alt_text = $nome;
        $title = $nome;
        $attachment_id = $this->importa_foto( $image_ulr, $foto['nome'], $title, $alt_text, $silent_mode );
        if (!$attachment_id) {
            return false;
        }
        $valor = [ 'nome' => $nome, 'descricao' => $descricao, 'ordem' => $ordem, 'exibir' => $exibir, 'fachada' => $fachada, 'foto_id' => $foto_id, 'id' => $attachment_id];
        $pm = add_post_meta( $this->post_id, 'fotografias', $valor, false );
        $x=$pm;*/
        return true;
    }
    private function atualizar_fotografia( $foto, &$fotografias_post ) {
        $exibir = $foto[ 'exibeInternet' ];
        if ( !$exibir ) return false;
        $foto_id = $foto[ 'id' ];
        $nome = $foto[ 'nome' ];
        $descricao = $foto[ 'descricao' ];
        $ordem= $foto[ 'ordem' ];
        $fachada = $foto[ 'fotoBanner' ];
        //
        $image_ulr = $foto['big'];
        $alt_text = $nome;
        $title = $nome;

        $valor = [ 'nome' => $nome, 'descricao' => $descricao, 'ordem' => $ordem, 'exibir' => $exibir, 'fachada' => $fachada, 'foto_id' => $foto_id];
        $ft = $this->find_by_foto_id( $foto_id, $fotografias_post );
        if ( $ft ) {
            $valor['id'] = $ft[ 'id' ];

            $chave = array_search($ft, $fotografias_post);
            if ($chave !== false) {
                unset( $fotografias_post[$chave] );
            }
        } else {
            $attachment_id = $this->importa_foto( $image_ulr, $foto['nome'], $title, $alt_text );
            $valor['id'] = $attachment_id;
        }

        $pm = add_post_meta( $this->post_id, 'fotografias', $valor, false );
        $x=$pm;
        return true;
    }
    public function salvar_fotografia_term( $foto ): bool {
        $meta_key_slug = 'fotos';
        if ( is_development_mode( ) && empty( $this->post_id ) || ! is_numeric( $this->post_id ) ) {
            error_log( 'Erro: ID do Post inválido ou ausente em salva_destaque_term.' );
            return false;
        }
        if ( is_development_mode( ) && ((! is_array( $foto )) || empty( $foto['big'] ) || empty( $foto['id'] ) )) {
            error_log( 'Erro: Dados de destaque (array) inválidos, URL principal (big) ou ID (id) ausentes.' );
            return false;
        }
        $imagem_url_sanitizada = esc_url_raw( $foto['big'] );
        $imagem_id_sanitizado  = sanitize_key( $foto['id'] );
        if ( is_development_mode( ) && (empty( $imagem_url_sanitizada ) || empty( $imagem_id_sanitizado )) ) {
            error_log( 'Erro: URL ou ID da imagem inválidos após a sanitização.' );
            return false;
        }
        $exibir = $foto[ 'exibeInternet' ];
        if ( !$exibir ) return false;

        $dados_para_salvar = array(
            'id' => $imagem_id_sanitizado
            , 'url' => $imagem_url_sanitizada
            , 'alt_text' => sanitize_text_field( $foto['nome'] )
            , 'title' => sanitize_text_field( $foto['nome'] )
            , 'description' => sanitize_textarea_field( $foto['descricao'] )
            , 'label' => sanitize_text_field( $foto['nome'] )
            , 'nome' => $foto['nome']
            , 'descricao' => $foto['descricao']
            , 'ordem' => $foto['ordem']
            , 'foto_id' => $foto['id']
            , 'big' => $foto['big']
            , 'fachada' => $foto[ 'fotoBanner' ]
            , 'exibir' => $foto[ 'exibeInternet' ]
        );
        $meta_id = add_post_meta(
            $this->post_id,         // ID do Post
            $meta_key_slug,         // A chave FIXA: 'imagem_destaque'
            $dados_para_salvar,     // O array de dados (serializado automaticamente)
            false                    // TRUE: Garante que esta chave é única
        );
        if ( $meta_id === false ) {
            // Falha: O meta campo já existe ou houve um erro no DB.
            // Se ele já existe, isso é esperado ao usar TRUE como quarto parâmetro.
            if ( is_development_mode( ) ) {
                error_log('Aviso: Meta campo fixo "' . $meta_key_slug . '" já existe ou falha na adição (Post ID: ' . $this->post_id . ').');
            }
            return false;
        }
        return true;
    }
	private function delete_image( $image_id ) {
		$deletou_midia = wp_delete_attachment( $image_id, true );
		if ( !$deletou_midia ) {
			return new WP_Error( 'falha_delecao_foto', 'A imagem foi removida do post, mas houve falha ao deletar o arquivo de mídia.' );
		}
		return true;
	}
	private function delete_post_thumbnail( ) {
		$thumbnail_id = get_post_thumbnail_id( $this->post_id );
		if ( $thumbnail_id ) {
			$removeu_thumbnail = delete_post_thumbnail( $this->post_id );
			if ( !$removeu_thumbnail ) {
				return new WP_Error( 'falha_remocao', 'Falha ao remover o featured image do post.' );
			}
			return $this->delete_image( $thumbnail_id );
		}
		return false;
	}

}
abstract class Pinedu_Foto_Util {
	public function __construct( ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
	}
	public function importa_foto( $url, $image_name = null, $image_title = null, $alt_text = null, $silent_mode = false ) {
		$image_path = $this->download_image_temp_file( $url, $image_name );
		if ( !$image_path ) {
            return false;
            //return new WP_Error( 'download_error', 'Erro ao fazer download do arquivo.' );
		}
		$attach_id = $this->upload_to_library( $image_path, $image_title, $alt_text, $silent_mode );
		if ( !$attach_id || is_wp_error( $attach_id ) ) {
			return false;
		}
        @unlink( $image_path );
		return $attach_id;
	}
	private function upload_to_library( $image_path, $image_title, $alt_text, $silent_mode = false ) {
		if ( !file_exists( $image_path ) ) {
            if ( $silent_mode === true ) {
                return false;
            }
			return new WP_Error( 'file_not_found', 'Arquivo não encontrado.' );
		}
		// Faz o upload do arquivo para o diretório de uploads do WordPress
		$upload = wp_upload_bits( basename( $image_path ), null, file_get_contents( $image_path ) );
		// Verifica se houve erro no upload
		if ( $upload[ 'error' ] ) {
            if ( $silent_mode === true ) {
                return false;
            }
            if (is_development_mode()) {
                error_log('Erro ao fazer upload da imagem: ' . $upload['error']);
            }
            if (is_string($upload['error'])) {
                return new WP_Error('upload_error', $upload['error']);
            } else {
                return new WP_Error('upload_error', $upload['error']->get_error_message());
            }
		}
		$filename = $upload[ 'file' ];
		// Prepara os dados do attachment
		$wp_filetype = wp_check_filetype( $filename );
		$attachment = array(
			'post_mime_type' => $wp_filetype[ 'type' ]
			, 'post_title' => sanitize_title( $image_title )
			, 'post_content' => ''
			, 'post_status' => 'inherit'
		);
		// Insere o attachment no banco de dados
		$attach_id = wp_insert_attachment( $attachment, $filename );

		// Verifica se a inserção foi bem-sucedida
		if ( is_wp_error( $attach_id ) ) {
            if ( $silent_mode === true ) {
                return false;
            }
			return new WP_Error( 'upload_error', $attach_id->get_error_message( ) );
		}
		// Gera os metadados da imagem
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		if ( !empty( $alt_text ) ) {
			// Define a propriedade 'alt' da imagem
			update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt_text );
		}
		return $attach_id; // Retorna o ID do attachment
	}
	/**
	 * Faz download de um arquivo para um diretório temporário
	 *
	 * @param string $url URL do arquivo a ser baixado
	 * @return string|false Caminho completo do arquivo temporário ou false em caso de falha
	 */
	private function download_image_temp_file( $url, $file_name = null ) {
		if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
/*		$temp_dir = get_temp_dir( );
		if ( !$file_name ) {
			$file_name = basename( parse_url( $url, PHP_URL_PATH ) ) . 'tmp';
			$file_name = sanitize_file_name( $file_name );
		}
		$temp_file = trailingslashit( $temp_dir ) . wp_unique_filename( $temp_dir, $file_name );
		$response = PineduRequest::getFile( $url, $temp_file, [] );
		if ( is_wp_error( $response ) ) {
			@unlink( $temp_file );
			return false;
		}
		if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			@unlink( $temp_file );
			return false;
		}
		if ( !file_exists( $temp_file ) ) {
			return false;
		}*/
        if ( ! function_exists( 'download_url' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }
        if ( ! function_exists( 'wp_read_image_metadata' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }
        $image_id = download_url( $url );
        if ( is_wp_error( $image_id ) ) {

        }
		return download_url( $url );
	}
}
