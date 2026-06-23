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

        $query->set('tax_query', $tax_query);
        $query->set('meta_query', $meta_query);
        self::ordenar($query, self::get_param('contrato'), self::get_param('sort', 'dataPreco'), self::get_param('ordem', 'DESC'));
    }

    public static function ordenar($query, $contrato, $sort, $direction) {
        $meta_query = $query->get('meta_query') ?: [];
        $current_orderby = $query->get('orderby') ?: [];
        switch ($contrato) {
            case '1':
                $prefix = 'venda';
                break;
            case '2':
                $prefix = 'locacao';
                break;
            case '3':
                $prefix = 'lancamento';
                break;
            default:
                $prefix = 'todos';
                break;
        }
        $mapa_generico = [
            'dataPreco'  => ['id' => 'ord_data', 'key' => $prefix.'DataAtualizacao', 'type' => 'DATETIME'],
            'valor'      => ['id' => 'ord_val',  'key' => $prefix.'Valor',           'type' => 'NUMERIC'],
            'referencia' => ['id' => 'ord_ref',  'key' => 'referencia', 'type' => 'NUMERIC'],
            'dormitorio' => ['id' => 'ord_dor',  'key' => 'DOR',        'type' => 'NUMERIC'],
            'suite'      => ['id' => 'ord_sui',  'key' => 'SUI',        'type' => 'NUMERIC'],
            'garagem'    => ['id' => 'ord_gar',  'key' => 'GAR',        'type' => 'NUMERIC'],
            'iptu'       => ['id' => 'ord_iptu', 'key' => 'IPTU',       'type' => 'NUMERIC'],
            'condominio' => ['id' => 'ord_cond', 'key' => 'COND',       'type' => 'NUMERIC'],
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
/*            if ( $query->get('post_type') === 'imovel' ) {
                error_log('=== SQL GERADO PELO WP: ===');
                error_log($request);
            }*/
            return $request;
        }, 10, 2);
    }
    private static function get_param($key, $default = '') {
        return isset($_REQUEST[$key]) ? sanitize_text_field($_REQUEST[$key]) : $default;
    }
}