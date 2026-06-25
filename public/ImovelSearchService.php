<?php
/**
 * Serviço centralizado para construção da WP_Query de Imóveis.
 */
class ImovelSearchService {

    public static function apply($query) {
        $query->set('post_type', 'imovel');
        $max = (int) self::get_param('max', 12);
        $query->set('posts_per_page', $max > 0 ? $max : 12);

        $tax_query = ['relation' => 'AND'];
        $meta_query = ['key' => 'statusImovel', 'value' => 'D', 'compare' => '='];

        // Filtros de Taxonomia
        foreach (['contrato', 'tipo-imovel', 'cidade', 'regiao'] as $tax) {
            $termo = self::get_param($tax);
            if (!empty($termo)) {
                $tax_query[] = ['taxonomy' => $tax, 'field' => 'slug', 'terms' => [(string)$termo]];
            }
        }

        // Faixa de Preço
        $v_min = (float) self::get_param('valor-inicial');
        $v_max = (float) self::get_param('valor-final');
        if ($v_min && $v_max) {
            $contrato = self::get_param('contrato');
            $chaves = ['1' => 'vendaValor', '2' => 'locacaoValor', '3' => 'lancamentoValor'];

            if (array_key_exists($contrato, $chaves)) {
                $meta_query[] = ['key' => $chaves[$contrato], 'value' => [$v_min, $v_max], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
            } else {
                $or_meta = ['relation' => 'OR'];
                foreach ($chaves as $k) $or_meta[] = ['key' => $k, 'value' => [$v_min, $v_max], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
                $meta_query[] = $or_meta;
            }
        }

        // CHAMA O FILTRO DINÂMICO PASSANDO O META_QUERY ATUAL (COMO REFERÊNCIA)
        self::apply_if_tipo($meta_query);

        $query->set('tax_query', $tax_query);
        $query->set('meta_query', $meta_query);

        self::ordenar($query, self::get_param('contrato'), self::get_param('sort', 'dataPreco'), self::get_param('ordem', 'DESC'));
    }

    public static function ordenar($query, $contrato, $sort, $direction) {
        $meta_query = $query->get('meta_query') ?: [];
        $current_orderby = $query->get('orderby') ?: [];

        switch ($contrato) {
            case '1': $prefix = 'venda'; break;
            case '2': $prefix = 'locacao'; break;
            case '3': $prefix = 'lancamento'; break;
            default:  $prefix = 'todos'; break;
        }

        $mapa_generico = [
            'dataPreco'  => ['id' => 'ord_data', 'key' => $prefix.'DataAtualizacao', 'type' => 'DATETIME'],
            'valor'      => ['id' => 'ord_val',  'key' => $prefix.'Valor',           'type' => 'NUMERIC'],
            'referencia' => ['id' => 'ord_ref',  'key' => 'referencia',              'type' => 'NUMERIC'],
            'dormitorio' => ['id' => 'ord_dor',  'key' => 'DOR',                     'type' => 'NUMERIC'],
            'suite'      => ['id' => 'ord_sui',  'key' => 'SUI',                     'type' => 'NUMERIC'],
            'garagem'    => ['id' => 'ord_gar',  'key' => 'GAR',                     'type' => 'NUMERIC'],
            'iptu'       => ['id' => 'ord_iptu', 'key' => 'IPTU',                    'type' => 'NUMERIC'],
            'condominio' => ['id' => 'ord_cond', 'key' => 'COND',                    'type' => 'NUMERIC'],
        ];

        if ( $prefix === 'todos' ) {
            $mapa_generico[ 'dataPreco' ]['key'] = 'data';
            $mapa_generico[ 'valor' ]['key'] = 'valor';
        }

        if (array_key_exists($sort, $mapa_generico)) {
            $c = $mapa_generico[$sort];
            $meta_query[$c['id']] = ['key' => $c['key'], 'compare' => 'EXISTS', 'type' => $c['type']];
            $query->set('orderby', array_merge([$c['id'] => $direction], (array)$current_orderby));
        }

        $query->set('meta_query', $meta_query);

        add_filter('posts_request', function($request, $query) {
            // Filtra para logar apenas a query de imóveis e não poluir o log
            /* if ( $query->get('post_type') === 'imovel' ) {
                error_log('=== SQL GERADO PELO WP: ===');
                error_log($request);
            } */
            return $request;
        }, 10, 2);
    }

    private static function get_param($key, $default = '') {
        return isset($_REQUEST[$key]) ? sanitize_text_field($_REQUEST[$key]) : $default;
    }

    // Recebe o $meta_query por referência (&$) para modificá-lo diretamente
    private static function apply_if_tipo( &$meta_query ) {
        $tipo = self::get_param('tipo-imovel');

        if ($tipo) {
            global $wpdb;
            $like_pattern = $wpdb->esc_like( $tipo ) . '-%';

            // CORREÇÃO AQUI: TODAS as tabelas agora usam o prefixo dinâmico do $wpdb
            $query_tipo = $wpdb->prepare("
                SELECT DISTINCT
                    upper(t.slug) AS termo_slug,
                    tm_tipo.meta_value AS meta_tipo
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt
                    ON t.term_id = tt.term_id
                INNER JOIN {$wpdb->termmeta} tm_relativo
                    ON t.term_id = tm_relativo.term_id AND tm_relativo.meta_key = 'relativo'
                INNER JOIN {$wpdb->termmeta} tm_tipo
                    ON t.term_id = tm_tipo.term_id AND tm_tipo.meta_key = 'tipo'
                WHERE tt.taxonomy = 'tipo-dependencia'
                  AND t.slug LIKE %s
                  AND tm_relativo.meta_value = 'CARACTERISTICAS';
            ", $like_pattern );

            $dependencias = $wpdb->get_results( $query_tipo, ARRAY_A );

            if (!$dependencias) return;

            foreach ($dependencias as $dep) {
                $t = $dep['meta_tipo'];
                if ( $t == 'TEXTO' ) continue;
                $p = self::get_param($dep['termo_slug']);

                // Só processa se o parâmetro foi enviado e for maior que 0
                if ( !empty($p) && ( ( (int)$p ) > 0 || ( (float)$p ) > 0) ) {
                    $k = $dep['termo_slug'];

                    if ( $t === 'BOOLEAN' ) {
                        // Booleans marcam presença, então o valor é '1'
                        $meta_query[] = ['key' => $k, 'value' => '1', 'compare' => '='];
                    } else if ( $t === 'INTEIRO' || $t === 'INTEIRO_TEXTO' ) {
                        // Obrigatório dizer ao banco que é NUMERIC para operadores >= funcionarem
                        $meta_query[] = ['key' => $k, 'value' => (int)$p, 'compare' => '>=', 'type' => 'NUMERIC'];
                    } else if ( $t === 'FLOAT' || $t === 'FLOAT_TEXTO' ) {
                        // Ponto flutuante requer DECIMAL para comparações seguras no SQL
                        $meta_query[] = ['key' => $k, 'value' => (float)$p, 'compare' => '>=', 'type' => 'DECIMAL'];
                    }
                }
            }
        }
    }
}