<?php

final class Pinedu_Foto_Demanda_Controller {

    // Propriedades estáticas para armazenar as instâncias únicas
    private static $importer_instance = null;
    private static $callback_closure  = null;
    private static $apagar_apos_processar = true;
    private const HOOK_PRIORITY      = 5; // Prioridade padrão do hook 'the_post'

    /**
     * Retorna a instância única da classe de importação de fotos em batch (Singleton).
     * @return Pinedu_Imovel_Importa_Foto_Batch
     */
    private static function get_batch_importer() {
        if ( self::$importer_instance === null ) {
            // Assumimos que a classe Pinedu_Imovel_Importa_Foto_Batch já foi carregada.
            self::$importer_instance = new Pinedu_Imovel_Importa_Foto_Batch();
        }
        return self::$importer_instance;
    }

    /**
     * Retorna a Closure única (callback) para o hook 'the_post'.
     * @return \Closure
     */
    private static function get_callback_closure() {
        if ( self::$callback_closure === null ) {
            $batch                 = self::get_batch_importer();
            $apagar_apos_processar = self::$apagar_apos_processar;
            // Define a closure que será usada em add_action e remove_action
            self::$callback_closure = function( $post ) use ( $batch, $apagar_apos_processar ) {
                self::invoca_foto_demanda( $post, $batch, $apagar_apos_processar );
            };
        }
        return self::$callback_closure;
    }

    /**
     * Verifica se a funcionalidade de importação de fotos sob demanda está ativa.
     * @return bool
     */
    public static function verificar_fotos_demanda(): bool {
        $options = get_option( 'pinedu_imovel_options', [] );
        if ( isset( $options['fotos_demanda'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Lógica principal que é executada pelo hook 'the_post'.
     * @param WP_Post $post Objeto do post atual.
     * @param Pinedu_Imovel_Importa_Foto_Batch $batch Instância do processador (injetada pela closure).
     * @param bool $apagar_apos_processar Flag para o processamento.
     */
    private static function invoca_foto_demanda( $post, $batch, $apagar_apos_processar ) {
        if ( is_singular( 'imovel' ) || is_page_template( 'single-imovel.php' ) ) {
            $batch->importa_fotos_post( $post, $apagar_apos_processar );
        }
    }

    // ----------------------------------------------------------------
    // Métodos Públicos (Controle de Hooks)
    // ----------------------------------------------------------------

    /**
     * Tenta registrar a action 'the_post' se a flag estiver ativa.
     * Este método deve ser conectado ao hook 'init'.
     */
    public static function instalar_foto_demanda() {
        if ( self::verificar_fotos_demanda() === true ) {
            self::add_foto_demanda();
        }
    }

    /**
     * Adiciona o hook 'the_post' para o callback da demanda.
     */
    public static function add_foto_demanda() {
        $callback = self::get_callback_closure();

        if ( ! has_action( 'the_post', $callback ) ) {
            add_action( 'the_post', $callback, self::HOOK_PRIORITY );
        }
    }

    /**
     * Remove o hook 'the_post' da demanda.
     * Usado tipicamente ao desabilitar a flag ou desativar o plugin.
     */
    public static function remove_foto_demanda() {
        $callback = self::get_callback_closure();

        // Verifica se a action está instalada antes de tentar remover
        if ( has_action( 'the_post', $callback ) ) {
            remove_action( 'the_post', $callback, self::HOOK_PRIORITY );
        }
    }
    public static function the_post() {
        global $post;
        if (verificar_fotos_demanda() === true) {
            $batch = self::get_batch_importer();
            $batch->importa_fotos_post( $post, self::$apagar_apos_processar );
        }
        the_post();
    }
}