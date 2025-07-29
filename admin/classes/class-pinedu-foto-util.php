<?php
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
		return $this->corretor['fotoNormal'];
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
class Pinedu_Imovel_Importa_Foto extends Pinedu_Foto_Util {
	private $imovel;
	private $post_id;
	public function __construct( $post_id, $imovel ) {
		$this->imovel = $imovel;
		$this->post_id = $post_id;
	}
	public function salva_imagem_destaque( ) {
		$imagem_destaque = $this->resolve_imagem_destaque( );
		if ( $imagem_destaque && ! empty( $imagem_destaque ) ) {
			$this->salva_post_imagem( $imagem_destaque );
		}
	}
	public function exclui_imagem_destaque( ) {
		$this->delete_post_thumbnail( $this->post_id );
	}
	public function atualiza_imagem_destaque( ) {
		$this->delete_post_thumbnail( $this->post_id );
		$imagem_destaque = $this->resolve_imagem_destaque( );
		if ( $imagem_destaque && ! empty( $imagem_destaque ) ) {
			$this->salva_post_imagem( $imagem_destaque );
		}
	}
	private function resolve_imagem_destaque( ) {
		if ( isset( $this->imovel['fotos'] ) ) {
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
	private function salva_post_imagem( $imagem_destaque ) {
		$image_ulr = $imagem_destaque['big'];
		$alt_text = $imagem_destaque['nome'];
		$title = $imagem_destaque['nome'];
		$description = $imagem_destaque['descricao'];
		$label = $imagem_destaque['nome'];
		$attachment_id = $this->importa_foto( $image_ulr, $imagem_destaque['nome'], $title, $alt_text );
		if ( $this->post_id && $attachment_id ) {
			set_post_thumbnail( $this->post_id, $attachment_id );
		}
		return $attachment_id;
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
	public function atualizar_fotografias( $fotografias_post ) {
		$fotografias = $this->imovel[ 'fotos' ];
		if ( empty( $fotografias ) ) {
			return false;
		}
		foreach( $fotografias as $foto ) {
			$exibir = $foto[ 'exibeInternet' ];
			if ( !$exibir ) break;
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
		}
		if ( !empty( $fotografias_post ) ) {
			$this->apagar_fotografias( $fotografias_post );
		}
	}
	public function excluir_fotografias( $fotografias_post ) {
		if ( !empty( $fotografias_post ) ) {
			$this->apagar_fotografias( $fotografias_post );
		}
	}
	public function salvar_fotografias( ) {
		$fotografias = $this->imovel[ 'fotos' ];
		if ( empty( $fotografias ) ) return false;
		foreach( $fotografias as $foto ) {
			$exibir = $foto[ 'exibeInternet' ];
			if ( !$exibir ) break;
			$foto_id = $foto[ 'id' ];
			$nome = $foto[ 'nome' ];
			$descricao = $foto[ 'descricao' ];
			$ordem= $foto[ 'ordem' ];
			$fachada = $foto[ 'fotoBanner' ];
			//
			$image_ulr = $foto['big'];
			$alt_text = $nome;
			$title = $nome;
			$attachment_id = $this->importa_foto( $image_ulr, $foto['nome'], $title, $alt_text );

			$valor = [ 'nome' => $nome, 'descricao' => $descricao, 'ordem' => $ordem, 'exibir' => $exibir, 'fachada' => $fachada, 'foto_id' => $foto_id, 'id' => $attachment_id];
			$pm = add_post_meta( $this->post_id, 'fotografias', $valor, false );
			$x=$pm;
		}
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
	public function importa_foto( $url, $image_name = null, $image_title = null, $alt_text = null ) {
		$image_path = $this->download_image_temp_file( $url, $image_name );
		if ( !$image_path ) {
			return new WP_Error( 'download_error', 'Erro ao fazer download do arquivo.' );
		}
		$attach_id = $this->upload_to_library( $image_path, $image_title, $alt_text );
		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}
		return $attach_id;
	}
	private function upload_to_library( $image_path, $image_title, $alt_text ) {
		if ( !file_exists( $image_path ) ) {
			return new WP_Error( 'file_not_found', 'Arquivo não encontrado.' );
		}
		// Faz o upload do arquivo para o diretório de uploads do WordPress
		$upload = wp_upload_bits( basename( $image_path ), null, file_get_contents( $image_path ) );
		// Verifica se houve erro no upload
		if ( $upload[ 'error' ] ) {
			return new WP_Error( 'upload_error', $upload[ 'error' ]->get_error_message( ) );
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
		$temp_dir = get_temp_dir( );
		if ( !$file_name ) {
			$file_name = basename( parse_url( $url, PHP_URL_PATH ) ) . 'tmp';
			$file_name = sanitize_file_name( $file_name );
		}
		$temp_file = trailingslashit( $temp_dir ) . wp_unique_filename( $temp_dir, $file_name );
		$options = get_option('pinedu_imovel_options', []);
		$token = $options['token'] ?? '';
		$headers = [
			'timeout' => ( 60 * 5 )
			, 'headers' => [
				'Content-Type' => 'application/json'
				, 'Authorization' => 'Bearer ' . sanitize_text_field( $token )
			]
			, 'sslverify' => true
			, 'timeout' => 300
			, 'stream' => true
			, 'filename' => $temp_file
		];
		$args = [ 'username' => $options['token_username'], 'password' => $options['token_password'] ];
		$my_url = $this->monta_get_url( $url, $args );
		$response = wp_remote_get( $my_url, $headers );
		if ( is_wp_error( $response ) ) {
			@unlink( $temp_file ); // Remove o arquivo temporário se existir
			return false;
		}
		if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			@unlink( $temp_file );
			return false;
		}
		if ( !file_exists( $temp_file ) ) {
			return false;
		}

		return $temp_file;
	}
}
