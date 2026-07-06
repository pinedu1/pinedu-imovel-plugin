<?php
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * CLASSE BASE: Centraliza a inteligência de Upload e Exclusão (Anti-Órfãos)
 */
abstract class Pinedu_Foto_Util {

    /**
     * GARANTIA ANTI-ÓRFÃOS: Apaga fisicamente TODAS as mídias atreladas a um Post ID.
     */
    protected function apaga_todas_midias_do_post( $post_id ): void {
        if ( empty($post_id) ) return;

        // 1. Apaga a imagem destacada atual, se existir
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            $this->deletar_midia_fisica( $thumbnail_id );
        }

        // 2. Busca qualquer anexo (attachment) que tenha este post como "pai"
        $attachments = get_posts( array(
            'post_type'   => 'attachment',
            'post_parent' => $post_id,
            'numberposts' => -1,
            'post_status' => null,
            'fields'      => 'ids'
        ) );

        if ( $attachments ) {
            foreach ( $attachments as $attachment_id ) {
                $this->deletar_midia_fisica( $attachment_id );
            }
        }
    }

    /**
     * Deleta um arquivo de mídia fisicamente do disco
     */
    protected function deletar_midia_fisica( $attachment_id ) {
        if ( empty( $attachment_id ) ) return false;

        $deletou = wp_delete_attachment( $attachment_id, true ); // true força a deleção contornando a lixeira
        return ( $deletou !== false && $deletou !== null );
    }

    /**
     * Substitui a antiga rotina dupla por um Sideload limpo nativo do WP
     */
    protected function importa_foto_sideload( $url, $post_id, $nome, $silent_mode = false ) {
        if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) return false;

        $tmp_name = download_url( esc_url_raw($url) );

        if ( is_wp_error( $tmp_name ) ) {
            if ( !$silent_mode ) {
                wp_send_json_error( [ 'message' => 'Falha ao baixar URL: ' . $tmp_name->get_error_message() ] );
                wp_die();
            }
            return false;
        }

        // Extrai extensão ou força JPG
        $extensao = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ?: 'jpg';
        $nome_arquivo = sanitize_file_name( $nome . '.' . $extensao );

        $file_array = array(
            'name'     => $nome_arquivo,
            'tmp_name' => $tmp_name
        );

        $attachment_id = media_handle_sideload( $file_array, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $file_array['tmp_name'] );
            if ( !$silent_mode ) {
                wp_send_json_error( [ 'message' => 'Falha ao processar a imagem: ' . $attachment_id->get_error_message() ] );
                wp_die();
            }
            return false;
        }

        // Atualiza o Alt Text da imagem na biblioteca
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $nome ) );

        return $attachment_id;
    }
}

/**
 * Classes Entidades (Loja e Corretor unificadas na mesma lógica abstrata)
 */
class Pinedu_Imovel_Importa_Foto_Loja extends Pinedu_Foto_Util {
    private $loja;
    private $post_id;

    public function __construct( $post_id, $loja ) {
        $this->post_id = $post_id;
        $this->loja    = $loja;
    }

    public function salva_imagem_destaque() {
        $this->processar_destaque();
    }

    public function atualiza_imagem_destaque() {
        $this->apaga_todas_midias_do_post( $this->post_id );
        $this->processar_destaque();
    }

    private function processar_destaque() {
        if ( !empty( $this->loja['logo'] ) ) {
            $attachment_id = $this->importa_foto_sideload(
                $this->loja['logo'],
                $this->post_id,
                $this->loja['p_fotoNome']
            );
            if ( $attachment_id ) {
                set_post_thumbnail( $this->post_id, $attachment_id );
            }
        }
    }
}

class Pinedu_Imovel_Importa_Foto_Corretor extends Pinedu_Foto_Util {
    private $corretor;
    private $post_id;

    public function __construct( $post_id, $corretor ) {
        $this->post_id  = $post_id;
        $this->corretor = $corretor;
    }

    public function salva_imagem_destaque() {
        $this->processar_destaque();
    }

    public function atualiza_imagem_destaque() {
        $this->apaga_todas_midias_do_post( $this->post_id );
        $this->processar_destaque();
    }

    private function processar_destaque() {
        if ( !empty( $this->corretor['fotoNormal'] ) ) {
            $attachment_id = $this->importa_foto_sideload(
                $this->corretor['fotoNormal'],
                $this->post_id,
                $this->corretor['p_fotoNome']
            );
            if ( $attachment_id ) {
                set_post_thumbnail( $this->post_id, $attachment_id );
            }
        }
    }
}

/**
 * Classe principal de Imóveis
 */
class Pinedu_Imovel_Importa_Foto extends Pinedu_Foto_Util {
    private $imovel;
    private $post_id;

    public function __construct( $post_id, $imovel ) {
        $this->post_id = $post_id;
        $this->imovel  = $imovel;
    }

    public function exclui_imagem_destaque() {
        $this->apaga_todas_midias_do_post( $this->post_id );
        delete_post_meta( $this->post_id, 'imagem_destaque' );
    }

    public function salva_imagem_destaque( $silent_mode = false ) {
        $destaque = $this->resolve_imagem_destaque();
        if ( $destaque && $destaque['exibeInternet'] === true ) {
            $this->executa_salvamento_destaque( $destaque, $silent_mode );
        }
    }

    public function atualiza_imagem_destaque( $silent_mode = false ) {
        // Anti-órfão: Zera as mídias antigas vinculadas ao destaque antes de baixar as novas
        $thumbnail_id = get_post_thumbnail_id( $this->post_id );
        if ($thumbnail_id) {
            $this->deletar_midia_fisica( $thumbnail_id );
        }

        $destaque = $this->resolve_imagem_destaque();
        if ( $destaque && $destaque['exibeInternet'] === true ) {
            $this->executa_salvamento_destaque( $destaque, $silent_mode );
        }
    }

    private function executa_salvamento_destaque( $destaque, $silent_mode ) {
        $attachment_id = $this->importa_foto_sideload( $destaque['big'], $this->post_id, $destaque['nome'], $silent_mode );
        if ( $attachment_id ) {
            set_post_thumbnail( $this->post_id, $attachment_id );
            $this->salva_metadado_legado( 'imagem_destaque', $destaque, $attachment_id );
        }
    }

    public function salvar_fotografias( $silent_mode = false ) {
        if ( empty( $this->imovel['fotos'] ) ) return;

        foreach( $this->imovel['fotos'] as $foto ) {
            $this->processa_fotografia_individual( $foto, $silent_mode );
        }
    }

    public function excluir_fotografias( $fotografias_post ) {
        if ( empty($fotografias_post) ) return;

        foreach( $fotografias_post as $foto ) {
            if ( isset($foto['id']) ) {
                $this->deletar_midia_fisica( $foto['id'] );
            }
        }
        delete_post_meta( $this->post_id, 'fotografias' );
    }

    public function atualizar_fotografias( $fotografias_post, $silent_mode = false ) {
        // Anti-órfão: Limpa todas as fotos antigas do array de metadados antes de injetar as novas
        $this->excluir_fotografias( $fotografias_post );
        $this->salvar_fotografias( $silent_mode );
    }

    private function processa_fotografia_individual( $foto, $silent_mode ) {
        if ( empty($foto['exibeInternet']) ) return false;

        $attachment_id = $this->importa_foto_sideload( $foto['big'], $this->post_id, $foto['nome'], $silent_mode );

        if ( $attachment_id ) {
            $valor = [
                'nome'      => $foto['nome'],
                'descricao' => $foto['descricao'] ?? '',
                'ordem'     => $foto['ordem'] ?? 0,
                'fachada'   => $foto['fotoBanner'] ?? false,
                'foto_id'   => $foto['id'],
                'id'        => $attachment_id
            ];
            add_post_meta( $this->post_id, 'fotografias', $valor, false );
            return true;
        }
        return false;
    }

    private function resolve_imagem_destaque() {
        if ( !empty( $this->imovel['fotos'] ) ) {
            foreach ( $this->imovel['fotos'] as $foto ) {
                if ( !empty($foto['fotoBanner']) ) return $foto;
            }
            return $this->imovel['fotos'][0];
        }
        return false;
    }

    /**
     * Mantém a compatibilidade com o formato de array antigo salvo no DB
     */
    private function salva_metadado_legado( $meta_key, $foto, $attachment_id ) {
        $dados = array(
            'id'          => sanitize_key( $attachment_id ),
            'url'         => esc_url_raw( $foto['big'] ),
            'alt_text'    => sanitize_text_field( $foto['nome'] ),
            'title'       => sanitize_text_field( $foto['nome'] ),
            'description' => sanitize_textarea_field( $foto['descricao'] ?? '' ),
            'label'       => sanitize_text_field( $foto['nome'] ),
        );
        update_post_meta( $this->post_id, $meta_key, $dados );
    }
}

/**
 * Classe para processamento em lote (Batch)
 */
class Pinedu_Imovel_Importa_Foto_Batch extends Pinedu_Imovel_Importa_Foto {
    // A classe pai refatorada já possui todos os métodos de importação seguros.
    // Os métodos de batch antigos varriam o banco inteiro buscando chaves 'fotos' orfãs.
    // Com a nova estrutura baseada no wp_delete_attachment nativo, essa classe pode
    // simplesmente iterar IDs e acionar atualizar_fotografias().
}