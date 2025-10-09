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
                    <button id="testar-servidor-btn" class="button secondary" <?php echo $importacao_andamento?'disabled':'' ?> onclick="return testarServidor(event);">Testar Servidor</button>
                </li>
                <li>
                    <button id="importar-btn" class="button secondary" <?php echo $importacao_andamento?'disabled':'' ?> onclick="return importarImoveisNormal(event);">Importar imóveis agora</button>
                </li>
                <li>
                    <button id="importar-forcado-btn" class="button secondary" <?php echo $importacao_andamento?'disabled':'' ?> onclick="return importarImoveisForcado(event);">Forçar Importação</button>
                </li>
            </ul>
        </div>
    </form>
</div>
