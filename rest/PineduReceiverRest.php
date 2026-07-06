<?php
require_once plugin_dir_path(__FILE__) . '../admin/classes/PineduRequest.php';
require_once plugin_dir_path(__FILE__) . '../admin/classes/class-pinedu-imovel-importar-basicos.php';
require_once plugin_dir_path(__FILE__) . '../admin/classes/class-pinedu-imovel-importar-imoveis.php';

    class PineduReceiverRest extends PineduRequest {
    private static $instance = null;

    private function __construct() {
        // Construtor privado
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    private static function getInstance(): PineduReceiverRest {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function instala_rest_end_point() {
        register_rest_route('pinedu-imovel/v1', 'inicializar', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'inicializar_atualizacao' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
        register_rest_route('pinedu-imovel/v1', 'encerrar', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'encerrar_atualizacao' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
        register_rest_route('pinedu-imovel/v1', 'update_basicos', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'receber_basicos' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
        register_rest_route('pinedu-imovel/v1', 'update_imoveis', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'receber_imoveis' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));

        // Rota de Cliques
        register_rest_route('pinedu-imovel/v1', 'clicks', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'enviar_clicks' ),
            'permission_callback' => array( __CLASS__, 'verify_credentials' ),
        ));
    }

    public static function enviar_clicks( $request ) {
        global $wpdb;

        // Query SQL para buscar os eventos isolados e seus meta_ids (para exclusão segura posterior)
        $query = "
            SELECT 
                ref.meta_value AS referencia,
                vis.meta_value AS evento_dados,
                vis.meta_id AS meta_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} ref 
                ON p.ID = ref.post_id AND ref.meta_key = 'referencia'
            INNER JOIN {$wpdb->postmeta} vis 
                ON p.ID = vis.post_id AND vis.meta_key = 'visita_evento'
            WHERE p.post_type = 'imovel'
              AND p.post_status = 'publish'
        ";

        $resultados = $wpdb->get_results( $query );
        $clicks_formatados = [];
        $meta_ids_para_excluir = [];

        if ( ! empty( $resultados ) ) {
            foreach ( $resultados as $linha ) {
                $dados = maybe_unserialize( $linha->evento_dados );

                if ( is_array( $dados ) && isset( $dados['cookie'] ) && isset( $dados['data'] ) ) {
                    $clicks_formatados[] = [
                        'referencia' => $linha->referencia,
                        'data'       => $dados['data'],
                        'cookie'     => $dados['cookie'],
                        'clicks'     => 1 // É um registro unitário na fila de eventos
                    ];
                    // Guarda a chave primária da wp_postmeta para a exclusão
                    $meta_ids_para_excluir[] = $linha->meta_id;
                }
            }
        }

        // Exclui APENAS os registros que acabamos de colocar no JSON.
        // Isso previne que um clique que ocorreu enquanto este script rodava seja apagado acidentalmente.
        if ( ! empty( $meta_ids_para_excluir ) ) {
            // Monta uma string como "105,106,107" com blindagem de inteiros para segurança
            $ids_imploded = implode( ',', array_map( 'intval', $meta_ids_para_excluir ) );
            $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($ids_imploded)" );
        }

        $response = [
            'success' => count( $clicks_formatados ) > 0,
            'clicks'  => $clicks_formatados
        ];

        return rest_ensure_response( $response );
    }

    public static function inicializar_atualizacao( $request ) {
        $options = get_option('pinedu_imovel_options', []);
        if ( $options['importacao_andamento'] === true ) {
            wp_send_json_error( [
                'message' => 'Importação em andamento por outro processo. Tente novamente mais tarde!',
            ] );
            wp_die();
        }
        if ( ! isset( $options['ultima_atualizacao'] ) ) {
            $options['ultima_atualizacao'] = DateTime::createFromFormat(
                'd/m/Y H:i:sT',
                '01/01/1980 00:00:00-300'
            );
        }

        $options['importacao_andamento'] = true;
        $options['inicio_importacao'] = new DateTime();
        update_option('pinedu_imovel_options', $options);
        wp_send_json( [
            'importacao_andamento' => $options['importacao_andamento'],
            'inicio_importacao' => formataData_iso8601( $options['inicio_importacao'] ),
            'ultima_atualizacao' => formataData_iso8601( $options[ 'ultima_atualizacao' ] )
        ] );
    }

    public static function encerrar_atualizacao( $request ) {
        $json_string = $request->get_body();
        $data = json_decode( $json_string, true );
        if (is_development_mode()) {
            error_log( 'encerrar_atualizacao: ' . print_r( $data, true) );
        }

        $token = isset( $data['token'] ) ? sanitize_text_field( $data['token'] ) : '';
        $imoveis_importados = isset( $data['imoveis_importados'] ) ? sanitize_text_field( $data['imoveis_importados'] ) : 0;

        $options = get_option( 'pinedu_imovel_options', [] );
        $options['ultima_atualizacao'] = new \DateTime(); // Padronizado \DateTime
        if ( isset( $options['inicio_importacao'] ) && $options['inicio_importacao'] instanceof \DateTime ) {
            $options['tempo_utilizado'] = $options['inicio_importacao']->diff( $options['ultima_atualizacao'] );
        }
        $options['imoveis_importados'] = $imoveis_importados;
        $options['token'] = $token;
        $options['importacao_andamento'] = false;
        update_option( 'pinedu_imovel_options', $options );

        $resposta = [
            'status' => 'sucesso',
            'dataAtualizacao' => formataData_iso8601( $options['ultima_atualizacao'] ),
            'importacao_andamento' => false
        ];

        if ( isset( $data['POST_PROCESS_ACTION'] ) ) {
            $action = sanitize_text_field( $data['POST_PROCESS_ACTION'] );
            if ( function_exists( 'fastcgi_finish_request' ) ) {
                wp_send_json( $resposta );
                fastcgi_finish_request();
                // CORRIGIDO: Chamada como método estático da classe
                self::pinedu_trigger_action_callback( $action );
            } else {
                wp_schedule_single_event( time(), 'pinedu_trigger_action', [ $action ] );
                wp_send_json( $resposta );
            }
        } else {
            wp_send_json( $resposta );
        }
    }
    public static function receber_basicos( $request ) {
        $json_string = $request->get_body();
        if (is_development_mode()) {
            error_log('JSON recebido: ' . $json_string);
        }
        $data = json_decode( $json_string, true );
        $importa_basicos = new Pinedu_Imovel_Importar_Basicos();
        $result = $importa_basicos->importar_callback( $data );
        if ( $result === true ) {
            $data = [
                'success' => true
                , 'dataAtualizacao' => formataData_iso8601( new \DateTime('now', new \DateTimeZone('America/Sao_Paulo') ) )
            ];
            return $data;
        }
        $data = [
            'success' => false
            , 'message' => 'Erro na importação dos dados Básicos'
        ];
        return $data;
    }

    public static function receber_imoveis( $request ) {
        $json_string = $request->get_body();
        $data = json_decode( $json_string, true );
        $importa_imoveis = new Pinedu_Imovel_Importar_Imoveis();
        $result = $importa_imoveis->importar_callback( $data );
        return $result;
    }

    public static function verify_credentials( $request ) {
        $auth_header = $request->get_header('Authorization');
        if (is_development_mode()) {
            error_log('Authorization Header: ' . $auth_header);
        }
        if (empty($auth_header)) {
            error_log('Authorization header missing');
            return false;
        }
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $bearer_token = trim($matches[1]);
            if (is_development_mode()) {
                error_log('Bearer Token: ' . $bearer_token);
            }
            return self::validate_bearer_token($bearer_token);
        }
        error_log('Invalid Authorization format');
        return false;
    }
    private static function validate_bearer_token( $token ): bool {
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( isset( $options['token'] ) && $options['token'] === $token ) {
            return true;
        }
        error_log('Invalid Token');
        return false;
    }
    // CORRIGIDO: Transformado em método estático e público para ser acessado pelo add_action e internamente
    public static function pinedu_trigger_action_callback($action) {
        switch ($action) {
            case 'OPTIMIZE_TABLES':
                self::optimize_tables();
                break;
            case 'GENERATE_SITE_MAP':
                self::generate_site_map();
                break;
            case 'GENERATE_JSON_LD':
                self::generate_json_ld();
                break;
            case 'GENERATE_FEED':
                self::generate_feed();
                break;
        }
    }
    public static function optimize_tables() {
        global $wpdb;
        $wpdb->query("OPTIMIZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$wpdb->options}");
    }
    /**
     * Consulta centralizada e otimizada (apenas 1 hit no banco)
     */
    public static function obter_dados_imoveis_ativos() {
        global $wpdb;
        $query = "
            SELECT
                p.ID,
                p.post_title,
                p.post_name,
                p.post_modified,
                pm_tipo_nome.meta_value AS tipoImovelNome,
                pm_tipo_id.meta_value AS tipoImovelId,
                pm_cidade.meta_value AS cidade,
                pm_bairro.meta_value AS bairro,
                pm_ref.meta_value AS referencia,
                pm_thumb.meta_value AS thumbnail_id,
                pm_av.meta_value AS ativarVenda,
                pm_vv.meta_value AS vendaValor,
                pm_al.meta_value AS ativarLocacao,
                pm_lv.meta_value AS locacaoValor,
                pm_an.meta_value AS ativarLancamento,
                pm_nv.meta_value AS lancamentoValor
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_status
                ON p.ID = pm_status.post_id AND pm_status.meta_key = 'statusImovel' AND pm_status.meta_value = 'D'
            INNER JOIN {$wpdb->postmeta} pm_tipo_id
                ON p.ID = pm_tipo_id.post_id AND pm_tipo_id.meta_key = 'tipoImovel_id'
            INNER JOIN {$wpdb->postmeta} pm_tipo_nome
                ON p.ID = pm_tipo_nome.post_id AND pm_tipo_nome.meta_key = 'tipoImovelNome'
            LEFT JOIN {$wpdb->postmeta} pm_cidade
                ON p.ID = pm_cidade.post_id AND pm_cidade.meta_key = 'cidade'
            LEFT JOIN {$wpdb->postmeta} pm_bairro
                ON p.ID = pm_bairro.post_id AND pm_bairro.meta_key = 'bairro'
            LEFT JOIN {$wpdb->postmeta} pm_ref
                ON p.ID = pm_ref.post_id AND pm_ref.meta_key = 'referencia'
            LEFT JOIN {$wpdb->postmeta} pm_thumb
                ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} pm_av ON p.ID = pm_av.post_id AND pm_av.meta_key = 'ativarVenda'
            LEFT JOIN {$wpdb->postmeta} pm_vv ON p.ID = pm_vv.post_id AND pm_vv.meta_key = 'vendaValor'
            LEFT JOIN {$wpdb->postmeta} pm_al ON p.ID = pm_al.post_id AND pm_al.meta_key = 'ativarLocacao'
            LEFT JOIN {$wpdb->postmeta} pm_lv ON p.ID = pm_lv.post_id AND pm_lv.meta_key = 'locacaoValor'
            LEFT JOIN {$wpdb->postmeta} pm_an ON p.ID = pm_an.post_id AND pm_an.meta_key = 'ativarLancamento'
            LEFT JOIN {$wpdb->postmeta} pm_nv ON p.ID = pm_nv.post_id AND pm_nv.meta_key = 'lancamentoValor'
            WHERE p.post_type = 'imovel'
              AND p.post_status = 'publish'
        ";
        return $wpdb->get_results( $query );
    }
    public static function generate_site_map() {
        $resultados = self::obter_dados_imoveis_ativos();
        $file_path = ABSPATH . 'sitemap_imoveis.xml';
        $handle = fopen( $file_path, 'w' );
        if ( ! $handle ) {
            error_log( 'Não foi possível abrir sitemap_imoveis.xml para gravação.' );
            return;
        }
        $base_url = trailingslashit( home_url() ) . 'imoveis/';
        fwrite( $handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL );
        fwrite( $handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL );
        if ( ! empty( $resultados ) ) {
            foreach ( $resultados as $imovel ) {
                $loc = esc_url( $base_url . $imovel->post_name . '/' );
                $lastmod = date( 'c', strtotime( $imovel->post_modified ) );
                $node  = "\t<url>\n";
                $node .= "\t\t<loc>{$loc}</loc>\n";
                $node .= "\t\t<lastmod>{$lastmod}</lastmod>\n";
                $node .= "\t\t<changefreq>daily</changefreq>\n";
                $node .= "\t</url>\n";
                fwrite( $handle, $node );
            }
        }
        fwrite( $handle, '</urlset>' );
        fclose( $handle );
    }
    public static function generate_json_ld() {
        $resultados = self::obter_dados_imoveis_ativos();
        $file_path = ABSPATH . 'catalog_data.json';
        $handle = fopen( $file_path, 'w' );

        if ( ! $handle ) {
            error_log( 'Não foi possível abrir catalog_data.json para gravação.' );
            return;
        }

        $base_url = trailingslashit( home_url() ) . 'imoveis/';
        fwrite( $handle, '[' . PHP_EOL );
        $primeiro_item = true;

        if ( ! empty( $resultados ) ) {
            foreach ( $resultados as $imovel ) {
                $offers = [];

                // Formatando as strings para Title Case
                $tipo_imovel_formatado = mb_convert_case( $imovel->tipoImovelNome ?? '', MB_CASE_TITLE, 'UTF-8' );
                $cidade_formatada      = mb_convert_case( $imovel->cidade ?? '', MB_CASE_TITLE, 'UTF-8' );
                $bairro_formatado      = mb_convert_case( $imovel->bairro ?? '', MB_CASE_TITLE, 'UTF-8' );

                if ( '1' === $imovel->ativarVenda && ! empty( $imovel->vendaValor ) ) {
                    $offers[] = [
                        '@type'            => 'Offer',
                        'price'            => $imovel->vendaValor,
                        'priceCurrency'    => 'BRL',
                        'businessFunction' => 'http://purl.org/goodrelations/v1#Sell'
                    ];
                }

                if ( '1' === $imovel->ativarLancamento && ! empty( $imovel->lancamentoValor ) ) {
                    $offers[] = [
                        '@type'            => 'Offer',
                        'price'            => $imovel->lancamentoValor,
                        'priceCurrency'    => 'BRL',
                        'businessFunction' => 'http://purl.org/goodrelations/v1#Sell'
                    ];
                }

                if ( '1' === $imovel->ativarLocacao && ! empty( $imovel->locacaoValor ) ) {
                    $offers[] = [
                        '@type'            => 'Offer',
                        'price'            => $imovel->locacaoValor,
                        'priceCurrency'    => 'BRL',
                        'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut'
                    ];
                }

                $item = [
                    '@context' => 'https://schema.org',
                    '@type'    => 'Accommodation',
                    'name'     => $imovel->post_title,
                    'url'      => esc_url( $base_url . $imovel->post_name . '/' ),
                    'sku'      => $imovel->referencia ?? '',
                    'category' => $tipo_imovel_formatado
                ];

                // Construindo o endereço se a cidade ou bairro existirem
                if ( ! empty( $cidade_formatada ) || ! empty( $bairro_formatado ) ) {
                    $item['address'] = [
                        '@type' => 'PostalAddress'
                    ];
                    if ( ! empty( $cidade_formatada ) ) {
                        $item['address']['addressLocality'] = $cidade_formatada;
                    }
                    if ( ! empty( $bairro_formatado ) ) {
                        $item['address']['streetAddress'] = $bairro_formatado;
                    }
                }

                if ( ! empty( $offers ) ) {
                    $item['offers'] = ( count( $offers ) === 1 ) ? $offers[0] : $offers;
                }

                $json_string = json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

                if ( $primeiro_item ) {
                    fwrite( $handle, $json_string );
                    $primeiro_item = false;
                } else {
                    fwrite( $handle, ',' . PHP_EOL . $json_string );
                }
            }
        }

        fwrite( $handle, PHP_EOL . ']' );
        fclose( $handle );
    }

    public static function generate_feed() {
        $resultados = self::obter_dados_imoveis_ativos();
        $file_path = ABSPATH . 'feed_catalog.xml';
        $handle = fopen( $file_path, 'w' );

        if ( ! $handle ) {
            error_log( 'Não foi possível abrir feed_catalog.xml para gravação.' );
            return;
        }

        $base_url = trailingslashit( home_url() ) . 'imoveis/';
        fwrite( $handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL );
        fwrite( $handle, '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL );
        fwrite( $handle, '<channel>' . PHP_EOL );

        if ( ! empty( $resultados ) ) {
            foreach ( $resultados as $imovel ) {
                $loc = esc_url( $base_url . $imovel->post_name . '/' );
                $title = htmlspecialchars( $imovel->post_title, ENT_QUOTES | ENT_XML1, 'UTF-8' );
                $referencia = htmlspecialchars( $imovel->referencia ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8' );

                // Formatando as strings para Title Case e escapando para XML
                $tipo_imovel = htmlspecialchars( mb_convert_case( $imovel->tipoImovelNome ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
                $cidade      = htmlspecialchars( mb_convert_case( $imovel->cidade ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
                $bairro      = htmlspecialchars( mb_convert_case( $imovel->bairro ?? '', MB_CASE_TITLE, 'UTF-8' ), ENT_QUOTES | ENT_XML1, 'UTF-8' );

                $valor_feed = '0';
                if ( '1' === $imovel->ativarVenda && ! empty( $imovel->vendaValor ) ) {
                    $valor_feed = $imovel->vendaValor;
                } elseif ( '1' === $imovel->ativarLancamento && ! empty( $imovel->lancamentoValor ) ) {
                    $valor_feed = $imovel->lancamentoValor;
                } elseif ( '1' === $imovel->ativarLocacao && ! empty( $imovel->locacaoValor ) ) {
                    $valor_feed = $imovel->locacaoValor;
                }
                $preco = htmlspecialchars( $valor_feed, ENT_QUOTES | ENT_XML1, 'UTF-8' );

                $image_link = '';
                if ( ! empty( $imovel->thumbnail_id ) ) {
                    $img_url = wp_get_attachment_image_url( $imovel->thumbnail_id, 'full' );
                    if ( $img_url ) {
                        $image_link = esc_url( $img_url );
                    }
                }

                $item  = "\t<item>\n";
                $item .= "\t\t<g:id>{$referencia}</g:id>\n";
                $item .= "\t\t<g:title>{$title}</g:title>\n";
                $item .= "\t\t<g:link>{$loc}</g:link>\n";
                if ( ! empty( $image_link ) ) {
                    $item .= "\t\t<g:image_link>{$image_link}</g:image_link>\n";
                }
                $item .= "\t\t<g:price>{$preco} BRL</g:price>\n";
                $item .= "\t\t<g:product_type>{$tipo_imovel}</g:product_type>\n";

                if ( ! empty( $cidade ) ) {
                    $item .= "\t\t<g:city>{$cidade}</g:city>\n";
                }
                if ( ! empty( $bairro ) ) {
                    $item .= "\t\t<g:neighborhood>{$bairro}</g:neighborhood>\n";
                }

                $item .= "\t\t<g:availability>in stock</g:availability>\n";
                $item .= "\t</item>\n";

                fwrite( $handle, $item );
            }
        }

        fwrite( $handle, '</channel>' . PHP_EOL );
        fwrite( $handle, '</rss>' );
        fclose( $handle );
    }
}