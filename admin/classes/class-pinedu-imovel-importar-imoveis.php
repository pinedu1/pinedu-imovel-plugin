<?php

use JetBrains\PhpStorm\NoReturn;

require_once plugin_dir_path(__FILE__) . 'PineduRequest.php';
require_once plugin_dir_path(__FILE__) . 'class-pinedu-imovel-importa-imovel.php';

class Pinedu_Importa_Libs {
    protected function monta_get_url( $url, $args ) {
        return add_query_arg( $args, $url );
    }
}

class Pinedu_Imovel_Importar_Imoveis extends Pinedu_Importa_Libs {
    const ENDPOINT = '/wordpress/imoveis';
    const IMOVEIS_POR_BLOCO = 50;

    private $imoveis_importados = 0;
    private $ultima_atualizacao;
    private $token;
    private $imoveis_excluidos = array();

    public function __construct() {}

    public function getToken(): string {
        return $this->token ?? '';
    }

    public function getUltimaAtualizacao() {
        return $this->ultima_atualizacao;
    }

    public function getImoveisImportados(): int {
        return $this->imoveis_importados;
    }

    public function setImoveisImportados( int $imoveis_importados ): void {
        $this->imoveis_importados = $imoveis_importados;
    }

    private function obter_data_ultima_atualizacao(): DateTime {
        $options = get_option('pinedu_imovel_options', []);
        if ( isset( $options['ultima_atualizacao'] ) && $options['ultima_atualizacao'] instanceof DateTime ) {
            return $options['ultima_atualizacao'];
        }
        return new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
    }

    private function executar_requisicao_get( $url_base, $endpoint, $args ) {
        $fullUrl = trailingslashit( $url_base ) . ltrim( $endpoint, '/' );
        return PineduRequest::get( $fullUrl, $args );
    }

    private function executar_preparacao( $url_servidor, $endpoint, $forcar = false ) {
        $args = [ 'forcar' => $forcar ];

        $ultima_atualizacao = $this->obter_data_ultima_atualizacao();
        if ( $ultima_atualizacao ) {
            $args['ultimaAtualizacao'] = formataData_iso8601( $ultima_atualizacao );
        }

        if ( is_development_mode() ) {
            error_log( "Preparação {$endpoint}: " . print_r($args, true) );
        }

        return $this->executar_requisicao_get( $url_servidor, $endpoint, $args );
    }

    private function imoveis_clicks() {
        $query = new WP_Query( array(
            'post_type'   => 'imovel',
            'post_status' => 'any',
            'fields'      => 'ids',
            'meta_query'  => array( [ 'key' => 'visitas', 'compare' => 'EXISTS' ] )
        ) );

        $visitados = array();

        if ( !empty($query->posts) ) {
            foreach ($query->posts as $post_id) {
                $visitas = get_post_meta( $post_id, 'visitas', false );
                $referencia = (int) get_post_meta( $post_id, 'referencia', true );

                foreach ( $visitas as $visita ) {
                    $visitados[] = array(
                        'cookie'     => $visita['cookie'],
                        'clicks'     => $visita['clicks'],
                        'referencia' => $referencia
                    );
                }
            }
        }
        return json_encode( $visitados );
    }

    #[NoReturn]
    public function preparar_imoveis_excluidos( $url_servidor, $forcar = false ) {
        return $this->executar_preparacao( $url_servidor, '/wordpress/listaExcluidos', $forcar );
    }

    #[NoReturn]
    public function preparar_imoveis( $url_servidor, $forcar = false ): void {
        $data = $this->executar_preparacao( $url_servidor, '/wordpress/preparaImportacao', $forcar );

        if ( is_development_mode() ) {
            error_log( "Retorno Prepara Imoveis: " . print_r($data, true) );
        }

        wp_send_json( $data );
        wp_die();
    }

    public function importa_imoveis_front_end( $url, $ultima_atualizacao, $clicks = [], $forcar = false, $offset = 0, $max = 0 ) {
        $ignorar_excluidos = true;
        $data = $this->importa_imoveis_particao( $url, $ultima_atualizacao, $clicks, $forcar, $offset, $max, $ignorar_excluidos );

        if ( $data === false ) {
            wp_send_json( [ 'success' => false, 'message' => 'A importação não retornou dados.' ] );
        }

        wp_send_json( $data );
        wp_die();
    }

    public function importa_imoveis_particao( $url, $ultima_atualizacao, $clicks = [], $forcar = false, $offset = 0, $max = 0, $ignorar_excluidos = false ) {
        $fullUrl = trailingslashit( $url ) . ltrim( self::ENDPOINT, '/' );
        $dados_retornar = [ 'success' => true, 'returned' => 0, 'total' => 0, 'excluidos' => [] ];
        $imoveis_importar = new Pinedu_Imovel_Importa_Imovel();

        $data = $this->call_remote_server( $fullUrl, $max, $offset, $clicks, $ultima_atualizacao, $forcar, $ignorar_excluidos );

        if ( !$data ) return false;

        if ( isset( $data['token'] ) ) $dados_retornar['token'] = $data['token'];
        if ( !empty( $data['excluidos'] ) ) $dados_retornar['excluidos'] = $data['excluidos'];

        $dados_retornar['returned'] = (int)$data['pagination']['returned'];
        $dados_retornar['total']    = (int)$data['pagination']['total'];

        if ( !empty( $data['imoveis'] ) ) {
            $imoveis_importar->importa_imoveis( $data['imoveis'] );
        }

        return $dados_retornar;
    }

    public function importa_imoveis_particao_json( $imoveis = [] ) {
        if ( !empty( $imoveis ) ) {
            $imoveis_importar = new Pinedu_Imovel_Importa_Imovel();
            $imoveis_importar->importa_imoveis( $imoveis, true );

            wp_send_json( [
                'success' => true,
                'message' => count($imoveis) . ' imóveis importados com sucesso!'
            ] );
        } else {
            wp_send_json( [
                'success' => false,
                'message' => 'Nenhum imóvel para importar!'
            ] );
        }
        wp_die();
    }

    public function invoca_server( $url, $forcar = false ) {
        try {
            // BLINDAGEM 1: Tempo global de execução liberado (se o host permitir)
            @set_time_limit(0);

            if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
                wp_send_json_error( [ 'message' => 'URL inválida.' ] );
            }

            $offset = 0;
            $max = (int)self::IMOVEIS_POR_BLOCO;
            $this->imoveis_importados = 0;
            $ultima_atualizacao = $this->obter_data_ultima_atualizacao();
            $imoveis_importar = new Pinedu_Imovel_Importa_Imovel();

            do {
                $clicks = ( $offset <= 0 ) ? $this->imoveis_clicks() : [];
                $data = $this->importa_imoveis_particao( $url, $ultima_atualizacao, $clicks, $forcar, $offset, $max );

                if ( !$data ) break;

                if ( isset( $data['token'] ) ) $this->token = $data['token'];
                if ( !empty( $data['excluidos'] ) ) $this->imoveis_excluidos = $data['excluidos'];

                $returned = $data['returned'];
                $offset += $returned;
                $this->imoveis_importados += $returned;

            } while ( $offset < $data['total'] );

            if ( !empty( $this->imoveis_excluidos ) ) {
                $imoveis_importar->trata_excluidos( $this->imoveis_excluidos );
            }

            $this->ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );
            return true;

        } catch ( Throwable $e ) { // Throwable captura Erros Fatais (Timeout) e Exceptions
            if ( is_development_mode() ) {
                error_log('Erro fatal durante a importação de imóveis: ' . $e->getMessage());
            }
            wp_send_json_error( [
                'message'    => 'Ocorreu um erro durante o processamento: ' . $e->getMessage(),
                'error_code' => 'import_error',
                'trace'      => defined('WP_DEBUG') && WP_DEBUG ? $e->getTrace() : null,
            ], 400 );
            return false;
        }
    }

    public function importar_callback( $data ) {
        try {
            // BLINDAGEM 1: Tempo global de execução liberado para rotina assíncrona
            @set_time_limit(0);

            $options = get_option('pinedu_imovel_options', []);
            $imoveis_importar = new Pinedu_Imovel_Importa_Imovel();

            if ( !empty( $data['excluidos'] ) ) {
                $imoveis_importar->trata_excluidos( $data['excluidos'] );
            }
            if ( !empty( $data['imoveis'] ) ) {
                $imoveis_importar->importa_imoveis( $data['imoveis'], true );
            }
            if ( !empty( $this->imoveis_excluidos ) ) {
                $imoveis_importar->trata_excluidos( $this->imoveis_excluidos );
            }

            $this->ultima_atualizacao = new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') );

            $options['imoveis_importados'] = $imoveis_importar->getImoveisImportados();
            $options['ultima_atualizacao'] = $this->ultima_atualizacao;

            update_option('pinedu_imovel_options', $options);

            return [
                'success'            => true,
                'ultima_atualizacao' => $this->ultima_atualizacao,
                'imoveis_importados' => $options['imoveis_importados']
            ];

        } catch ( Throwable $e ) { // Throwable captura Erros Fatais e previne tela branca no frontend
            return [
                'success' => false, // O Frontend interceptará isso e mostrará na cortina de log
                'message' => 'Falha Crítica (Timeout/Memória) no processo: ' . $e->getMessage()
            ];
        }
    }

    private function call_remote_server( $url, $max = 0, $offset = 0, $clicados = [], $ultima_atualizacao = null, $forcar = false, $ignorar_excluidos = false ) {
        $args = [ 'max' => $max, 'offset' => $offset, 'forcar' => $forcar ];

        if ( !empty($clicados) ) {
            $args['visitas'] = $clicados;
        }
        if ( $ignorar_excluidos ) {
            $args['ignorarExcluidos'] = true;
        }

        if ( empty($ultima_atualizacao) || (is_string($ultima_atualizacao) && strtolower($ultima_atualizacao) === 'null') ) {
            $data_formatada = '1980-01-01T00:00:00.000Z';
        } else if ( $ultima_atualizacao instanceof DateTime ) {
            $data_formatada = formataData_iso8601( $ultima_atualizacao );
        } else {
            $data_formatada = $ultima_atualizacao;
        }

        $args['ultimaAtualizacao'] = $data_formatada;

        if ( is_development_mode() ) {
            error_log( "Argumentos Call Remote Server: " . print_r($args, true) );
        }

        $data = PineduRequest::get( $url, $args );

        if ( empty($data) || (isset($data['success']) && ((bool)$data['success']) !== true) ) {
            if ( is_development_mode() ) {
                error_log( "Falha no Retorno Remoto: " . print_r($data, true) );
            }
            wp_send_json_error( ['message' => $data['message'] ?? 'Formato do arquivo inválido (Imóveis)!'] );
            return null;
        }

        return $data;
    }
}