<?php
require_once plugin_dir_path(__FILE__) . 'class-pinedu-foto-util.php';

class Pinedu_Imovel_Importa_Imovel {
    private $imoveis_importados = 0;

    public function __construct() {}

    public function getImoveisImportados(): int {
        return $this->imoveis_importados;
    }

    public function setImoveisImportados( int $imoveis_importados ): void {
        $this->imoveis_importados = $imoveis_importados;
    }

    public function importa_imoveis( $imoveis, $silent_mode = false ) {
        if ( ! post_type_exists( 'imovel' ) ) {
            if ( $silent_mode === true ) {
                return false;
            }
            wp_send_json_error( ['message' => 'Post Type Imóvel não existe!'] );
            wp_die();
        }

        foreach ( $imoveis as $imovel ) {
            $this->importa_imovel( $imovel, $silent_mode );
        }
    }

    private function importa_imovel( $imovel, $silent_mode = false ) {
        $referencia = $imovel['referencia'] ?? 'SEM_REF';

        // BLINDAGEM 2: A cada novo imóvel no loop, o cronômetro do PHP ZERA.
        // Ele ganha 150 segundos exclusivos para tentar baixar todas as fotos deste imóvel.
        @set_time_limit(150);

        if ( is_development_mode() ) {
            error_log( "=== Iniciando processamento do Imóvel Ref: {$referencia} ===" );
        }

        // BLINDAGEM 3: O bloco Try/Catch encapsulando TODO o imóvel individual.
        // Se a memória estourar por uma foto gigantesca, ele apenas ignora ESTE imóvel e volta pro Loop acima!
        try {
            $args = array(
                'meta_key'    => 'referencia',
                'meta_value'  => $referencia,
                'post_type'   => 'imovel',
                'post_status' => 'any',
                'numberposts' => 1
            );

            $posts = get_posts( $args );

            if ( empty( $posts ) ) {
                if ( is_development_mode() ) error_log( "Ação: CRIAR novo imóvel (Ref: {$referencia})" );
                $this->salvar( $imovel, $silent_mode );
            } else {
                $post_id = $posts[0]->ID;
                if ( is_development_mode() ) error_log( "Ação: ATUALIZAR imóvel existente (ID: {$post_id}, Ref: {$referencia})" );
                $this->atualizar( $post_id, $imovel, $silent_mode );
            }

            $this->setImoveisImportados( $this->getImoveisImportados() + 1 );

        } catch ( Throwable $e ) {
            // Rastreamento de erro severo (O erro não contamina o batch)
            error_log( "Falha severa ignorada no imóvel Ref: {$referencia}. Pulando para o próximo da fila. Erro: " . $e->getMessage() );
        }

        wp_reset_postdata();
    }

    // =========================================================================
    // EXCLUSÃO EM LOTES (MÉTODOS COMUNS UNIFICADOS)
    // =========================================================================

    public function trata_excluidos( $array_referencias ): bool {
        if (empty($array_referencias)) return true;
        $referencias = array_map('intval', array_column($array_referencias, 'referencia'));
        return $this->processa_exclusao_em_lotes( $referencias );
    }

    public function trata_excluidos_from_referecia_array( $array_referencias ): bool {
        if (empty($array_referencias)) return true;
        return $this->processa_exclusao_em_lotes( $array_referencias );
    }

    private function processa_exclusao_em_lotes( array $referencias ): bool {
        $lotes = array_chunk($referencias, 50);
        foreach ($lotes as $lote_ref) {
            $args = array(
                'post_type'      => 'imovel',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => 'referencia',
                        'value'   => $lote_ref,
                        'compare' => 'IN',
                        'type'    => 'NUMERIC'
                    ),
                ),
            );
            $query = new WP_Query($args);
            if (!empty($query->posts)) {
                $this->trata_excluidos_post_ids( $query->posts );
            }
        }
        return true;
    }

    private function trata_excluidos_post_ids( $post_ids ) {
        foreach ($post_ids as $post_id) {
            $this->excluir( intval( $post_id ) );
        }
    }

    private function excluir( $post_id ) {
        $status = get_post_status( $post_id );
        if ( $status !== false && is_numeric( $post_id ) ) {
            $importa_fotos = new Pinedu_Imovel_Importa_Foto( $post_id, array() );
            $importa_fotos->exclui_imagem_destaque();
            $fotografias_post = get_post_meta( $post_id, 'fotografias', false );
            $importa_fotos->excluir_fotografias( $fotografias_post );

            $resultado = wp_delete_post( $post_id, true );
            return $resultado !== false && $resultado !== null;
        }
        return false;
    }

    // =========================================================================
    // SALVAR / ATUALIZAR (MÉTODOS COMUNS UNIFICADOS)
    // =========================================================================

    private function salvar( $imovel, $silent_mode = false ) {
        $post_id = wp_insert_post( $this->prepara_dados_post(0, $imovel) );

        if ( is_wp_error( $post_id ) ) {
            error_log( "Erro ao salvar imóvel Ref: {$imovel['referencia']} - " . $post_id->get_error_message() );
            wp_die( $post_id->get_error_messages() );
        }

        $this->sincroniza_dados_relacionados( $post_id, $imovel, $silent_mode, false );
        return $post_id;
    }

    private function atualizar( $post_id, $imovel, $silent_mode = false ) {
        $this->apaga_attachments( $post_id );
        $result_id = wp_update_post( $this->prepara_dados_post($post_id, $imovel), true );

        if ( is_wp_error( $result_id ) ) {
            error_log( "Erro ao atualizar imóvel ID: {$post_id} - " . $result_id->get_error_message() );
            return false;
        }

        $this->sincroniza_dados_relacionados( $post_id, $imovel, $silent_mode, true );
        return $post_id;
    }

    private function prepara_dados_post( $post_id, $imovel ) {
        $dados = array(
            'post_title'   => $this->resolve_slug( $imovel ),
            'post_content' => $this->resolve_anuncio( $imovel ) ?? '',
            'post_status'  => 'publish',
            'post_type'    => 'imovel'
        );

        if ($post_id > 0) {
            $dados['ID'] = $post_id;
        } else {
            $dados['post_date'] = current_time( 'mysql' );
        }
        return $dados;
    }

    /**
     * BLINDAGEM 4: Motor comum que dispara taxonomias, metadados e FOTOS (com isolamento seguro)
     */
    private function sincroniza_dados_relacionados( $post_id, $imovel, $silent_mode, $is_update ) {
        $taxonomias = new Pinedu_Imovel_Importa_Taxonomias( $post_id, $imovel );
        $metadados  = new Pinedu_Imovel_Importa_Metadados( $post_id, $imovel );
        $fotos      = new Pinedu_Imovel_Importa_Foto( $post_id, $imovel );

        // 1º Passo: Salva dados vitais (Rápido e imune a timeout)
        try {
            if ( $is_update ) {
                $taxonomias->atualizar();
                $metadados->atualizar();
            } else {
                $taxonomias->salvar();
                $metadados->salvar();
            }
        } catch (Throwable $e) {
            error_log("Aviso: Falha nas taxonomias/metadados da Ref: {$imovel['referencia']} - " . $e->getMessage());
        }

        // 2º Passo: Isolamento absoluto do processo mais lento (As 64 fotos)
        try {
            if ( $is_update ) {
                $fotografias_post = get_post_meta( $post_id, 'fotografias', false );
                $fotos->atualiza_imagem_destaque( $silent_mode );
                $fotos->atualizar_fotografias( $fotografias_post, $silent_mode );
            } else {
                $fotos->salva_imagem_destaque( $silent_mode );
                $fotos->salvar_fotografias( $silent_mode );
            }
        } catch (Throwable $e) {
            // Se as fotos causarem Timeout no CURL, falha de memória ou qualquer fatal error,
            // as imagens são abortadas MAS o imóvel fica salvo com sucesso e o código vai para o próximo!
            error_log("Aviso: O download massivo de fotos falhou/foi interrompido na Ref: {$imovel['referencia']}. Erro: " . $e->getMessage());
        }
    }

    private function apaga_attachments( $post_id ): void {
        $attachments = get_posts( array(
            'post_type'   => 'attachment',
            'post_parent' => $post_id,
            'numberposts' => -1,
            'post_status' => null,
            'fields'      => 'ids'
        ) );
        if ( $attachments ) {
            foreach ( $attachments as $attachment_id ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            wp_delete_attachment( $thumbnail_id, true );
        }
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
                $slug_imovel .= ' / ' . trim( $imovel['locacaoSlug'] );
            }
        }
        if ( isset( $imovel['lancamentoSlug'] ) && !empty( trim( $imovel['lancamentoSlug'] ) ) ) {
            if ( empty( $slug_imovel ) ) {
                $slug_imovel = trim( $imovel['lancamentoSlug'] );
            } else {
                $slug_imovel .= ' / ' . trim( $imovel['lancamentoSlug'] );
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
        return $imovel['anuncioRenderizado'] ?? '';
    }
}

class Pinedu_Imovel_Importa_Metadados {
    const PROPRIEDADES = ['id', 'referencia', 'anoConstrucao', 'anuncio', 'anuncioRenderizado', 'ativarLancamento', 'ativarLocacao', 'ativarVenda', 'bairro', 'bairroCorretagem', 'captadorPrincipalId', 'captadorPrincipalNome', 'captadorPrincipalPessoaId', 'carteira', 'cep', 'chaves_id', 'cidade', 'cidadeCorretagem', 'condominio_id', 'custoAnuncio', 'dataCaptacao', 'dateCreated', 'descricaoChaves', 'desocupacao', 'edificio_id', 'enderecoRenderizado', 'enviarWeb', 'estado', 'estado_id', 'finalidade', 'finalidadeNome', 'horarioVisita', 'lancamento', 'lancamentoDataAtualizacao', 'lancamentoProxAtualizacao', 'lancamentoNome', 'lancamentoPromocao', 'lancamentoSlug', 'lancamentoValor', 'lastUpdated', 'latitude', 'logradouroDNE', 'loja_id', 'longitude', 'matAgua', 'matEner', 'matGaz', 'matIPTU', 'memorialDescritivo', 'nomeUsuCriador', 'novo', 'observacoes', 'obsLocal', 'padraoConstrucao', 'permiteIntermediacao', 'permitePlaca', 'permiteUnidades', 'placa_id', 'pontoReferencia', 'proprietario_id', 'regiao', 'regiaoCorretagem', 'segmento_id', 'statusImovel', 'tipoImovel_id', 'tipoOcupacao', 'tipoOcupacaoNome', 'tituloEdificio', 'version', 'zoneamento'];

    private $imovel;
    private $post_id;

    public function __construct( $post_id, $imovel ) {
        $this->imovel = $imovel;
        $this->post_id = $post_id;
    }

    public function salvar() {
        $this->salvar_metadados_imovel();
    }

    public function excluir() {
        $this->apagar_metadados_imovel();
    }

    public function atualizar() {
        $this->apagar_metadados_imovel();
        $this->salvar_metadados_imovel();
    }

    private function salvar_dependencias( $dependencias = [] ) {
        if ( empty( $dependencias ) ) return false;
        foreach( $dependencias as $dependencia ) {
            $nome      = $dependencia['tipDep_nome'];
            $relativo  = $dependencia['tipDep_relativo'];
            $sigla     = $dependencia['tipDep_sigla'];
            $tipoCampo = $dependencia['tipDep_tipoCampo'];
            $valor = null;

            switch ( $tipoCampo ) {
                case 'TEXTO':
                    $valor = $dependencia['valorTexto'];
                    break;
                case 'INTEIRO':
                case 'INTEIRO_TEXTO':
                    $valor = (int)$dependencia['valorInteiro'];
                    break;
                case 'FLOAT':
                case 'FLOAT_TEXTO':
                    $valor = (float)$dependencia['valorFloat'];
                    break;
                case 'BOOLEAN':
                case 'BOOLEAN_TEXTO':
                    $valor = (bool)$dependencia['valorBoolean'];
                    break;
            }

            if ( !empty($valor) || $valor === true ) {
                add_post_meta( $this->post_id, $sigla, $valor, true );
                add_post_meta( $this->post_id, ( $sigla . 'Nome' ), $nome, true );
                if ( $tipoCampo !== 'TEXTO' ) {
                    add_post_meta( $this->post_id, ( $sigla . 'Relativo' ), $relativo, true );
                }
            }
        }
    }

    private function trata_data( $data_string ): DateTime {
        $dataLimpa = trim( (string) $data_string, "'\"" );

        try {
            return new DateTime( $dataLimpa );
        } catch ( Exception $e ) {
            if ( is_development_mode() ) {
                error_log( "Aviso: Falha ao fazer parse da data '{$data_string}' no imóvel ID {$this->post_id}. Adotando data fallback. Erro: " . $e->getMessage() );
            }
            return new DateTime('1980-01-01T00:00:00Z');
        }
    }

    private function processa_metadados_contrato( string $prefixo, &$properties ): array {
        $valor = (float)($properties[ $prefixo . 'Valor' ] ?? 0);
        $dataAtualizacao = $properties[ $prefixo . 'DataAtualizacao' ] ?? '1980-01-01T00:00:00Z';

        $campos_contrato = ['Valor', 'DataAtualizacao', 'Nome', 'Promocao', 'ProxAtualizacao', 'Slug'];

        foreach ($campos_contrato as $campo) {
            $chave_completa = $prefixo . $campo;
            $valor_campo = $properties[$chave_completa] ?? '';
            if ($campo === 'Valor') {
                $valor_campo = (float)$valor_campo;
            }
            add_post_meta( $this->post_id, $chave_completa, $valor_campo, true );
            unset( $properties[$chave_completa] );
        }

        return ['valor' => $valor, 'data' => $this->trata_data( $dataAtualizacao ) ];
    }

    private function salvar_metadados_imovel() {
        global $wpdb; // Garantir acesso ao objeto de banco de dados
        $properties = $this->recolhe_propriedades();

        $dados_venda = $this->processa_metadados_contrato( 'venda', $this->imovel );
        $dados_locacao = $this->processa_metadados_contrato( 'locacao', $this->imovel );

        $valorCondominio = (float)$this->imovel[ 'valorCondominio' ];
        $valorIptu = (float)$this->imovel[ 'valorIptu' ];
        unset( $this->imovel[ 'valorCondominio' ], $this->imovel[ 'valorIptu' ] );

        add_post_meta( $this->post_id, 'valorCondominio', $valorCondominio, true );
        add_post_meta( $this->post_id, 'valorIptu', $valorIptu, true );

        $valorBase = $dados_venda['valor'];
        $dataBase  = $dados_venda['data'];

        if ( $dados_locacao['valor'] > $valorBase ) {
            $valorBase = $dados_locacao['valor'];
        }
        if ( $dados_locacao['data'] > $dataBase ) {
            $dataBase = $dados_locacao['data'];
        }

        $properties['visitas'] = $properties['visitas'] ?? 0;
        $properties['clicks']  = $properties['visitas'];

        add_post_meta( $this->post_id, 'valor', $valorBase, true );
        add_post_meta( $this->post_id, 'data', $dataBase->format('Y-m-d H:i:s'), true );

        $latitude = isset($properties['latitude']) ? (float)$properties['latitude'] : 0;
        $longitude = isset($properties['longitude']) ? (float)$properties['longitude'] : 0;

        foreach( $properties as $key => $value ) {
            if ( is_string( $value ) && trim( $value ) === '' ) continue;
            if ( is_numeric( $value ) && $value <= 0 ) continue;
            if ( ( is_array( $value ) || is_object( $value ) ) && empty( $value ) ) continue;

            add_post_meta( $this->post_id, $key, $value, true );
        }

        if ( $latitude != 0 && $longitude != 0 ) {
            add_post_meta( $this->post_id, 'latitude', $latitude, true );
            add_post_meta( $this->post_id, 'longitude', $longitude, true );

            // INSERÇÃO NA TABELA DE GEODATA
            $table_geo = $wpdb->prefix . 'pnd_geodata';
            $wpdb->query( $wpdb->prepare(
                "REPLACE INTO {$table_geo} (post_id, lat, lng) VALUES (%d, %s, %s)",
                $this->post_id,
                $latitude,
                $longitude
            ) );
        }

        if ( isset( $this->imovel['tipoImovel']['nome'] ) ) {
            add_post_meta( $this->post_id, 'tipoImovelNome', $this->imovel['tipoImovel']['nome'], true );
        }
        if ( isset( $this->imovel['dependencias'] ) ) {
            $this->salvar_dependencias( $this->imovel['dependencias'] );
        }
    }
    private function apagar_metadados_imovel() {
        global $wpdb;

        // Verifica se o post realmente existe antes de prosseguir
        if ( !get_post( $this->post_id ) ) return 0;

        // 1. Apaga os metadados padrão do WordPress (wp_postmeta)
        $wpdb->delete(
            $wpdb->postmeta,
            array( 'post_id' => $this->post_id ),
            array( '%d' )
        );

        // 2. Apaga a entrada correspondente na sua tabela de geolocalização
        $table_geo = $wpdb->prefix . 'pnd_geodata';
        $wpdb->delete(
            $table_geo,
            array( 'post_id' => $this->post_id ),
            array( '%d' )
        );
    }
    private function recolhe_propriedades() {
        $properties = array();
        foreach ( self::PROPRIEDADES as $propriedade ) {
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

    public function excluir() {
        $this->remove_post_taxonomias();
    }

    public function salvar() {
        $importa_contratos = new Pinedu_Imovel_Importa_Contratos( $this->post_id, $this->imovel );
        $importa_contratos->salvar();

        $mapa_termos = [
            'tipoImovel_id'    => 'tipo-imovel',
            'cidadeCorretagem' => 'cidade',
            'regiaoCorretagem' => 'regiao',
            'bairroCorretagem' => 'bairro'
        ];

        foreach ($mapa_termos as $chave_imovel => $taxonomia) {
            if ( isset( $this->imovel[$chave_imovel] ) ) {
                $valor = (string)$this->imovel[$chave_imovel];
                wp_set_object_terms( $this->post_id, $valor, $taxonomia, false );
                unset( $this->imovel[$chave_imovel] );
            }
        }
    }

    public function atualizar() {
        $this->remove_post_taxonomias();
        $this->salvar();
    }

    private function remove_post_taxonomias() {
        wp_delete_object_term_relationships($this->post_id, get_taxonomies());
    }
}

class Pinedu_Imovel_Importa_Contratos {
    private $imovel;
    private $post_id;

    public function __construct( $post_id, $imovel ) {
        $this->imovel = $imovel;
        $this->post_id = $post_id;
    }

    public function atualizar() {
        wp_remove_object_terms( $this->post_id, array('1', '2', '3'), 'contrato' );
        $this->salvar();
    }

    public function salvar() {
        $mapa_contratos = [
            'ativarVenda'      => '1',
            'ativarLocacao'    => '2',
            'ativarLancamento' => '3'
        ];

        foreach ($mapa_contratos as $chave => $id_termo) {
            if ( isset($this->imovel[$chave]) && $this->imovel[$chave] === true ) {
                wp_set_object_terms( $this->post_id, $id_termo, 'contrato', true );
                unset( $this->imovel[$chave] );
            }
        }
    }
}