<?php
require_once plugin_dir_path( __FILE__ ) . 'classes/PaginasIniciais.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/MailConfig.php';

class Pinedu_Imovel_Plugin_Activator {

    public static function activate() {
        // 1. Registro de páginas iniciais
        PaginasIniciaisFactory::criar('PaginasIniciais')->registrar();

        // 2. Criação da Tabela de Geodata
        self::criar_tabelas();

        // 3. Criação dos Índices de Performance
        self::criar_indices();

        if ( defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development' ) {
            MailConfig::config_options_mail();
        }

        self::carregar_dados();
    }
    private static function carregar_dados() {
        global $wpdb;
        $table_geo = $wpdb->prefix . 'pnd_geodata';

        // Query otimizada: busca todos os imóveis com status 'D' e suas coordenadas em uma única consulta
        $query = "
            SELECT p.ID as post_id,
                   MAX(CASE WHEN pm.meta_key = 'latitude' THEN pm.meta_value END) as lat,
                   MAX(CASE WHEN pm.meta_key = 'longitude' THEN pm.meta_value END) as lng
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} ms ON p.ID = ms.post_id
            WHERE p.post_type = 'imovel'
              AND ms.meta_key = 'statusImovel' AND ms.meta_value = 'D'
              AND pm.meta_key IN ('latitude', 'longitude')
            GROUP BY p.ID
            HAVING lat IS NOT NULL AND lng IS NOT NULL
        ";

        $results = $wpdb->get_results($query);

        if ($results) {
            foreach ($results as $row) {
                // Sanitização e inserção (REPLACE INTO garante que não teremos duplicatas)
                $wpdb->query($wpdb->prepare(
                    "REPLACE INTO {$table_geo} (post_id, lat, lng) VALUES (%d, %f, %f)",
                    $row->post_id,
                    (float)str_replace(',', '.', $row->lat),
                    (float)str_replace(',', '.', $row->lng)
                ));
            }
        }
    }
    private static function criar_tabelas() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'pnd_geodata';

        $sql = "CREATE TABLE $table_name (
            post_id BIGINT(20) UNSIGNED NOT NULL,
            lat DECIMAL(10, 8) NOT NULL,
            lng DECIMAL(10, 8) NOT NULL,
            PRIMARY KEY (post_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        $options = get_option( 'pinedu_imovel_options', [] );
        $options['geoposicao_nativa'] = true;
        update_option( 'pinedu_imovel_options', $options );
    }

    private static function criar_indices() {
        global $wpdb;

        // Índices sugeridos
        $indices = [
            "CREATE INDEX IF NOT EXISTS idx_pnd_meta_key_value_post ON wp_postmeta (meta_key(32), meta_value(64), post_id);"
            , "CREATE INDEX IF NOT EXISTS idx_pnd_post_id_meta_key ON wp_postmeta (post_id, meta_key(32));"
            , "CREATE INDEX IF NOT EXISTS idx_object_taxonomy ON wp_term_relationships (object_id, term_taxonomy_id);"
        ];

        foreach ( $indices as $index_sql ) {
            $wpdb->query( $index_sql );
        }
    }
}