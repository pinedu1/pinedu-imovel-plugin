<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.pinedu.com.br
 * @since             1.0.0
 * @package           Pinedu_Imovel_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Pinedu Imoveis
 * Plugin URI:        https://wordpress-plugin/pinedu.com.br
 * Description:       Plugin para Sites de Imobiliárias baseado no CRM Pinedu-Imóveis ( pndImo )
 * Version:           1.0.0
 * Author:            Eduardo Pinheiro da Silva
 * Author URI:        https://www.pinedu.com.br/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pinedu-imovel-plugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
define( 'PINEDU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PINEDU_IMOVEL_PLUGIN_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pinedu-imovel-plugin-activator.php
 */
function activate_pinedu_imovel_plugin( ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pinedu-imovel-plugin-activator.php';
	Pinedu_Imovel_Plugin_Activator::activate( );
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pinedu-imovel-plugin-deactivator.php
 */
function deactivate_pinedu_imovel_plugin( ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pinedu-imovel-plugin-deactivator.php';
	Pinedu_Imovel_Plugin_Deactivator::deactivate( );
}

register_activation_hook( __FILE__, 'activate_pinedu_imovel_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_pinedu_imovel_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pinedu-imovel-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pinedu_imovel_plugin( ) {
    require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-foto-util.php';
    require_once plugin_dir_path( __FILE__ ) . './includes/classes/Pinedu_Foto_Demanda_Controller.php';
	$plugin = new Pinedu_Imovel_Plugin( );
	$plugin->run( );
}
run_pinedu_imovel_plugin( );
function normalizar($texto) {
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    return strtoupper($texto);
}
function lista_contratos( ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-contrato.php';
	return Pinedu_Imovel_Importa_Contrato::list( );
}
function lista_tipo_imovel( ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-tipo-imovel.php';
	return Pinedu_Imovel_Importa_Tipo_Imovel::list( );
}
function lista_cidade( ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-cidade.php';
	return Pinedu_Imovel_Importa_Cidade::list( );
}
function lista_regiao( $cidade ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-regiao.php';
	return Pinedu_Imovel_Importa_Regiao::list( $cidade );
}
function lista_faixa_valor( $contrato ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-faixa-valor.php';
	return Pinedu_Imovel_Importa_Faixa_Valor::list( $contrato );
}
function lista_faixa_valor_valores( $contrato ) {
    require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-faixa-valor.php';
    if ( empty( $contrato ) ) {
        return [];
    }
    $lista = Pinedu_Imovel_Importa_Faixa_Valor::list( $contrato );
    $l = [ 0 ];
    foreach ((array) $lista as $key) {
        $m = get_term_meta( $key->term_id, 'valor-final', true );
        $l[] = floatval( $m );
    }
    return $l;
}
function registra_visita_imovel( $post = 0 ) {
	$post = get_post( $post );
	$post_id = $post->ID;
	$current_clicks = get_post_meta( $post_id, 'clicks', true );
	$current_visitas = get_post_meta( $post_id, 'visitas', false );
	$cookieId = getCookieId();
	$busca_cookie = function( $clicks, $cookie ) {
		foreach ( $clicks as $click_entry ) {
			if ( is_array( $click_entry ) && isset( $click_entry['cookie'] ) ) {
				if ( $click_entry['cookie'] == $cookie ) {
					return $click_entry;
				}
			}
		}
		return null;
	};
	if ( $cookieId ) {
		if ( empty( $current_clicks ) ) {
			$pm = add_post_meta( $post_id, 'clicks', 1, true );
		} else {
			$pm = update_post_meta( $post_id, 'clicks', $current_clicks + 1 );
		}
		$my_cookie = $busca_cookie( $current_visitas, $cookieId );
		if ( $my_cookie ) {
			$valor = ($my_cookie[ 'clicks' ] + 1);
			$pm = update_post_meta( $post_id, 'visitas', [ 'cookie' => $cookieId, 'clicks' => $valor ] );
		} else {
			$pm = add_post_meta( $post_id, 'visitas', [ 'cookie' => $cookieId, 'clicks' => 1 ], false );
		}
	}
}
function getCookie() {
	require_once plugin_dir_path( __FILE__ ) . './includes/classes/class-pinedu-imovel-cookie.php';
	return CookieUtil::getCookie();
}
function getCookieId() {
	require_once plugin_dir_path( __FILE__ ) . './includes/classes/class-pinedu-imovel-cookie.php';
	return CookieUtil::getCookieId();
}
function updateCookie( $nome, $telefone, $email ) {
	require_once plugin_dir_path( __FILE__ ) . './includes/classes/class-pinedu-imovel-cookie.php';
	return CookieUtil::update_cookie( $nome, $telefone, $email );
}
function criarCookie( ) {
	require_once plugin_dir_path( __FILE__ ) . './includes/classes/class-pinedu-imovel-cookie.php';
	return CookieUtil::criaCookie( );
}

function enviarCliente( $nome, $telefone, $email, $mensagem, $cookieId, $referencia = null ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-enviar-cliente.php';
	$enviar = new Pinedu_Imovel_Enviar_Cliente( $nome, $telefone, $email, $mensagem, $cookieId, $referencia );
	return $enviar->contato_cliente( );
}
function formataData_iso8601( $data ) {
	$timestamp = match(true) {
		is_numeric($data) => (int)$data,
		is_string($data) => (new DateTime($data))->getTimestamp(),
		$data instanceof DateTime => $data->getTimestamp(),
		default => time()
	};
	return wp_date("Y-m-d\TH:i:s.v\Z", $timestamp);
}
function formata_endereco( $endereco, $numero, $complemento, $bairro, $cidade, $estado, $cep, $pula_linha = false ) {
	$end = $endereco;
	if ( ! empty( $numero ) ) {
		$end .= ', ' . $numero;
	}
	if ( ! empty( $complemento ) ) {
		$end .= ' - ' . $complemento;
	}
	if ( $pula_linha ) {
		if ( ! empty( $bairro ) ) {
			$end .= '<br>' . $bairro;
		}
	} else {
		if ( ! empty( $bairro ) ) {
			$end .= ' - ' . $bairro;
		}
	}
	if ( ! empty( $cidade ) ) {
		$end .= ', ' . $cidade;
	}
	if ( $pula_linha ) {
		if ( ! empty( $estado ) ) {
			$end .= '<br>' . $estado . ', Brasil' ;
		}
	} else {
		if ( ! empty( $estado ) ) {
			$end .= ', ' . $estado . ', Brasil' ;
		}
	}
	if ( ! empty( $cep ) ) {
		$end .= ' - Cep: ' . formata_cep( $cep ) ;
	}
	return $end;
}
function formata_cep( $cep ) {
	$cep = preg_replace( '/[^0-9]/', '', $cep );
	$cep_formatado = substr( $cep, 0, 5 ) . '-' . substr( $cep, 5, 3 );
	return $cep_formatado;
}
function formata_telefone( $telefone ) {
	$telefone = preg_replace( '/[^0-9]/', '', $telefone );
	switch ( strlen( $telefone ) ) {
		case 11:
			$telefone_formatado = '( ' . substr( $telefone, 0, 2 ) . ' ) ' . substr( $telefone, 2, 1 ) . '.' . substr( $telefone, 3, 4 ) . '-' . substr( $telefone, 7 );
			break;
		case 10:
			$telefone_formatado = '( ' . substr( $telefone, 0, 2 ) . ' ) ' . substr( $telefone, 2, 4 ) . '-' . substr( $telefone, 6 );
			break;
		case 9:
			$telefone_formatado = substr( $telefone, 0, 1 ) . '.' . substr( $telefone, 1, 4 ) . '-' . substr( $telefone, 4 );
			break;
		case 8:
			$telefone_formatado = substr( $telefone, 0, 4 ) . '-' . substr( $telefone, 4 );
			break;
		default:
			$telefone_formatado = '( ' . substr( $telefone, 0, 2 ) . ' ) ' . substr( $telefone, 2, 4 ) . '-' . substr( $telefone, 6 );
			break;
	}
	return $telefone_formatado;
}
function get_tipo_dependencias_imovel( $post_id ) {
	require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-tipo-dependencia.php';
	$caracteristica_icons = [ 'DOR' => 'fa fa-bed', 'SUI' => 'fa fa-shower', 'BAN' => 'fa fa-bath', 'GAR' => 'fa fa-car', 'COZ' => 'fa-solid fa-kitchen-set', 'PIS' => 'fa-solid fa-person-swimming', 'PISPRV' => 'fa-solid fa-person-swimming', 'SAL' => 'fa-solid fa-couch', 'ARS' => 'fa fa-brush', 'INTERFON' => 'fa fa-bell', 'ARCOND' => 'fa fa-snowflake', 'ARUTIL' => 'fa-solid fa-ruler-combined', 'ARCONS' => 'fa-solid fa-draw-polygon', 'ARTOT' => 'fa-solid fa-circle-nodes' ];
	$dependencias = [];
	$tipo_dependencias = Pinedu_Imovel_Importa_Tipo_Dependencia::get_tipo_dependencias();

	$meta = get_post_meta( $post_id, '', true );
	foreach ( [ 'CARACTERISTICAS', 'CONDOMINIO', 'EDIFICIO', 'INFRAEXTRUTURA' ] as $relativo ) {
		$caracteristicas = $tipo_dependencias[ $relativo ];
		foreach ( $caracteristicas as $caracteristica) {
			$sigla = $caracteristica['sigla'];
			if ( isset($meta[ $sigla ] ) ) {
				if ( !isset($dependencias[ $relativo ])) {
					$dependencias[ $relativo ] = [];
				}
				$caracteristica['valor'] = $meta[$sigla][0];
				if ( $relativo == 'CARACTERISTICAS' ) {
					$caracteristica['icone'] = 'fa-solid fa-thumbtack';
                    if ( isset( $caracteristica_icons[ $sigla ] ) ) {
						$caracteristica['icone'] = 'fa-solid fa-thumbtack';;
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'GARAGE') || str_contains(strtolower( $caracteristica['nome'] ), 'VAGA') ) {
                        $caracteristica['icone'] = 'fa fa-car';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'DORMIT') || str_contains(strtolower( $caracteristica['nome'] ), 'QUARTO') ) {
                        $caracteristica['icone'] = 'fa fa-bed';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'SUITE') ) {
                        $caracteristica['icone'] = 'fa fa-shower';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'SALA') ) {
                        $caracteristica['icone'] = 'fa-solid fa-couch';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'BANHEIRO') || str_contains(strtolower( $caracteristica['nome'] ), 'WC')) {
                        $caracteristica['icone'] = 'fa fa-shower';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'COZINHA')) {
                        $caracteristica['icone'] = 'fa-solid fa-kitchen-set';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'AREA')) {
                        $caracteristica['icone'] = 'fa-solid fa-ruler-combined';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'PISCINA')) {
                        $caracteristica['icone'] = 'fa-solid fa-person-swimming';
                    } else if ( str_starts_with(normalizar( $caracteristica['nome'] ), 'VARANDA') || str_starts_with(normalizar( $caracteristica['nome'] ), 'SACADA')) {
                        $caracteristica['icone'] = 'fa-solid fa-chair';
                    } else if ( $caracteristica['tipo'] == 'BOOLEAN') {
                        $caracteristica['icone'] = 'fa fa-check-circle';
                    }
                } else {
					$caracteristica['icone'] = 'fa-solid fa-thumbtack';
				}
				$dependencias[ $relativo ][] = $caracteristica;
			}
		}
	}
	return $dependencias;
}
function get_meta_value( $post, $nome_meta = '' ) {
    if ( empty( $nome_meta ) ) return false;
    require_once plugin_dir_path( __FILE__ ) . './admin/classes/class-pinedu-imovel-importa-tipo-dependencia.php';
    $dependencias = [];
    $tipo_dependencias = Pinedu_Imovel_Importa_Tipo_Dependencia::get_tipo_dependencias();

    $meta = get_post_meta( $post->ID, '', true );
    foreach ( [ 'CARACTERISTICAS', 'CONDOMINIO', 'EDIFICIO', 'INFRAEXTRUTURA' ] as $relativo ) {
        $caracteristicas = $tipo_dependencias[ $relativo ];
        foreach ( $caracteristicas as $caracteristica) {
            $sigla = $caracteristica['sigla'];
            if ( isset($meta[ $sigla ] ) ) {
                if ( !isset($dependencias[ $relativo ])) {
                    $dependencias[ $relativo ] = [];
                }
                $caracteristica['valor'] = $meta[$sigla][0];
                if ( str_starts_with( normalizar( $caracteristica['nome'] ), normalizar( $nome_meta ) ) ) {
                    return $caracteristica;
                }
            }
        }
    }
    return false;
}
function corta_texto( $texto, $tamanho ): string {
    if (!is_string($texto)) {
        return '';
    }
    if ( ( strlen($texto) > $tamanho ) ) {
        return substr($texto, 0, $tamanho);
    }
    return $texto;
}
function formata_valor($valor, $decimais = 0, $moeda = ''): string {
	$valor = is_numeric($valor) ? (float)$valor : 0;

	$valor_formatado = '';

	if (!empty($moeda)) {
		$valor_formatado = 'R$ ';
	}

	return $valor_formatado . number_format($valor, $decimais, ',', '.');
}
function verificar_fotos_demanda(): bool {
    $options = get_option( 'pinedu_imovel_options', [] );
    if ( isset( $options['fotos_demanda'] ) && $options['fotos_demanda'] === 'on' ) {
        return true;
    }
    return false;
}
function get_the_post() {
    Pinedu_Foto_Demanda_Controller::the_post();
}
function baixar_fotos_destaque( $query, $apagar_destaque = true ) {
    Pinedu_Foto_Demanda_Controller::imagens_destaque( $query, $apagar_destaque );
}

function get_google_maps_key() {
    $options = get_option( 'pinedu_imovel_options', [] );
    if ( isset( $options[ 'chave_google_api' ] ) ) {
        return $options[ 'chave_google_api' ];
    }
    return '';
}
function formatar_title_case(string $endereco_maiusculo, string $encoding = 'UTF-8'): string {
    $endereco_minusculo = mb_strtolower($endereco_maiusculo, $encoding);
    $endereco_title_case = mb_convert_case($endereco_minusculo, MB_CASE_TITLE, $encoding);
    $substituicoes = [
        ' Cep' => ' CEP',
        ' Sp' => ' SP',
        '/sp' => '/SP',
        ' / Sp' => ' / SP',
        ' Rg' => ' RG',
        ' Cpf' => ' CPF',
        ' Pj' => ' PJ',
        ' Me' => ' ME',
        ' Epp' => ' EPP',
        ' Ltda' => ' LTDA',
    ];
    $endereco_final = str_replace(
        array_keys($substituicoes),
        array_values($substituicoes),
        $endereco_title_case
    );
    return $endereco_final;
}
function formata_link_telefone( $telefone ) {
	$telefone = preg_replace( '/[^0-9]/', '', $telefone );
    if (! str_starts_with( $telefone, 55 ) ) {
        $telefone = '+55' . $telefone;
    }
    return $telefone;
}


/**
 * Calcula os pontos de um círculo (polígono) em torno de um ponto central
 * usando a fórmula da Grande Rota (Great Circle Distance).
 *
 * @param array<string, float> $center Um array com as chaves 'latitude' e 'longitude' (em graus).
 * @param int $radiusMeters O raio do círculo em metros.
 * @return array<int, array<string, float>> Uma lista de pontos (latitude e longitude em graus) que formam o círculo.
 */
function drawCircle(array $center, int $radiusMeters): array {
    // Constantes
    $EARTH_RADIUS_KM = 6371.0;

    // PHP usa a constante M_PI para o valor de Pi
    $PI = pi();

    // 1. Converter centro e raio para radianos
    // A fórmula original do Groovy já usa (graus * PI / 180.0)
    $latitudeRadians = $center['latitude'] * $PI / 180.0;
    $longitudeRadians = $center['longitude'] * $PI / 180.0;

    // Raio em radianos: (metros / 1000) / Raio da Terra em KM
    $radiusRadians = ($radiusMeters / 1000.0) / $EARTH_RADIUS_KM;

    // 2. Cálculos Prefixados (partes constantes do cálculo de latitude)
    $calcLatPrefix = sin($latitudeRadians) * cos($radiusRadians);
    $calcLatSuffix = cos($latitudeRadians) * sin($radiusRadians);

    $path = [];

    // 3. Loop (ângulo de 0 a 360, passos de 10)
    for ($angle = 0; $angle < 361; $angle += 10) {

        // Converter ângulo do loop para radianos
        $angleRadians = $angle * $PI / 180.0;

        // CÁLCULO DA LATITUDE (em radianos)
        // latitude = Math.asin(calcLatPrefix + calcLatSuffix * Math.cos(angleRadians));
        $latitude = asin($calcLatPrefix + $calcLatSuffix * cos($angleRadians));

        // CÁLCULO DA LONGITUDE (em radianos, antes da conversão final)
        // Numerador: Math.sin(angleRadians) * Math.sin(radiusRadians) * Math.cos(latitudeRadians)
        $numerator = sin($angleRadians) * sin($radiusRadians) * cos($latitudeRadians);

        // Denominador: Math.cos(radiusRadians) - Math.sin(latitudeRadians) * Math.sin(latitude)
        $denominator = cos($radiusRadians) - sin($latitudeRadians) * sin($latitude);

        // A expressão completa do Groovy é:
        // longitude = ((longitudeRadians + Math.atan2(Numerador, Denominador)) * 180) / Math.PI;
        $longitude = (($longitudeRadians + atan2($numerator, $denominator)) * 180) / $PI;

        // Converter Latitude de radianos para graus
        // latitude = latitude * 180.0 / Math.PI;
        $latitude = $latitude * 180.0 / $PI;

        // 4. Adicionar ao Path (Lista de arrays associativos)
        $path[] = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
    }
    return $path;
}
function formatCoordinatesToCircle(array $points): string {
    $formattedPoints = array_map(function($point) {
        return $point['latitude'] . ',' . $point['longitude'];
    }, $points);
    $resultString = implode('|', $formattedPoints);
    return $resultString;
}

function getEmpresa( $id = 1 ) {
    $empresa = null;
    $query = new \WP_Query( array(
        'post_type' => 'empresa',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key'     => 'id',
                'value'   => $id,
                'compare' => '='
            ]
        ]
    ) );

    if ($query->have_posts()) {
        $empresa = $query->posts[0];
    }
    wp_reset_postdata();
    return $empresa;
}
function getCorretor( $id ) {
    if (empty($id)) return false;
    $corretor = null;
    $query = new \WP_Query( array(
        'post_type' => 'corretor',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key'     => 'id',
                'value'   => $id,
                'compare' => '='
            ]
        ]
    ) );

    if ($query->have_posts()) {
        $corretor = $query->posts[0];
    }
    wp_reset_postdata();
    return $corretor;
}
function is_development_mode() {
    if ( wp_get_environment_type() === 'development' ) {
        return true;
    }
    return false;
}
