<?php

	/**
	 * Provide a admin area view for the plugin
	 *
	 * This file is used to markup the admin-facing aspects of the plugin.
	 *
	 * @link       http://example.com
	 * @since      1.0.0
	 *
	 * @package    Pinedu_Imovel
	 * @subpackage Pinedu_Imovel/admin/partials
	 */
?>
<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div id="pinedu-imovel-config" class="wrap">
    <h1>Configurações Pinedu Imóveis</h1>
    <form method="post" action="options.php">
		<?php
			settings_fields('pinedu-imovel-group');
			do_settings_sections('pinedu-imovel');
			submit_button('Salvar Configurações');
		?>
    </form>
    <?php
        $options = get_option( 'pinedu_imovel_options', [] );
        $importacao_andamento = false;
        if ( isset( $options['importacao_andamento'] ) ) {
            $importacao_andamento = $options['importacao_andamento'];
        }
        if ( $importacao_andamento === true ) {
            echo '<div id="info" class="informacao info">Importação em andamento. Volte novamente mais tarde!</div>';
        } else {
            echo '<div id="info" class="informacao"></div>';
        }
    ?>
    <form method="post" action="">
		<?php
        wp_nonce_field('pinedu_imovel_actions', 'pinedu_imovel_nonce');
        $options = get_option( 'pinedu_imovel_options', [] );
        $importacao_andamento = $options['importacao_andamento'] ?? false;
        echo '<input type="hidden" name="importacao_andamento" value="' . (((bool)$importacao_andamento)?1:0) . '">';
        ?>
        <div class="form-table">
            <ul>
                <li>
                    <button id="testar-servidor-btn" class="button button-primary" <?php echo $importacao_andamento?'disabled':'' ?> onclick="return testarServidor(event);">Testar Servidor</button>
                </li>
                <li>
                    <button id="importar-btn" class="button secondary" style="display: none" <?php echo $importacao_andamento?'disabled':'' ?> onclick="return importarImoveisNormal(event);">Importar imóveis agora</button>
                </li>
                <li>
                    <button id="importar-forcado-btn" class="button secondary" style="display: none" <?php echo $importacao_andamento?'disabled':'' ?> onclick="return importarImoveisForcado(event);">Forçar Importação</button>
                </li>
                <li>
                    <button id="btnImportarFrontEnd" class="button button-primary" <?php echo $importacao_andamento?'disabled':'' ?>>Importar imóveis</button>
                </li>
                <li>
                    <button id="btnImportarForcadoFrontEnd" class="button secondary" <?php echo $importacao_andamento?'disabled':'' ?>>Forçar Importação</button>
                </li>
            </ul>
        </div>
    </form>
</div>

<section id="importacao-log" class="importacao-log" style="display: none;">
    <div class="overlay" id="importacao-overlay">
        <div class="spinner" id="importacao-spinner"></div>
        <div class="info" id="importacao-info">Informando tal coisa... Aguarde!</div>
        <div class="message" id="importacao-message">Processando tal coisa... Aguarde!</div>
        <div class="progress-bar">
            <div class="progress" id="importacao-progress"></div>
            <div class="progress-text" id="importacao-progress-text"></div>
        </div>
        <div class="fechar" id="importacao-fechar" style="display: none;">
            <button id="btnFechar" class="button button-primary">Fechar</button>
        </div>
    </div>
</section>

<?php
function listar_actions_ajax() {
    echo '<div class="wrap">';
    echo '<h1>Actions AJAX registradas</h1>';

    global $wp_filter;

    echo '<h2>wp_ajax_ (admin)</h2>';
    echo '<ul>';
    foreach ($wp_filter as $hook => $obj) {
        if (strpos($hook, 'wp_ajax_') === 0) {
            echo '<li><strong>' . esc_html($hook) . '</strong></li>';
        }
    }
    echo '</ul>';

    echo '<h2>wp_ajax_nopriv_ (visitantes)</h2>';
    echo '<ul>';
    foreach ($wp_filter as $hook => $obj) {
        if (strpos($hook, 'wp_ajax_nopriv_') === 0) {
            echo '<li><strong>' . esc_html($hook) . '</strong></li>';
        }
    }
    echo '</ul>';

    echo '</div>';
}
//listar_actions_ajax();