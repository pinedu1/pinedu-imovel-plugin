<?php
	require_once plugin_dir_path(__FILE__) . 'class-pinedu-foto-util.php';
class Pinedu_Imovel_Importa_Empresa extends Pinedu_Foto_Util {
	const TERMOS = array(
		'enderecos', 'emails', 'telefones'
	);
	const PROPRIEDADES = array( 'id' => 'id', 'observacoes' => 'observacoes', 'creci' => 'creci', 'site' => 'site', 'inscrMunicipal' => 'inscrMunicipal', 'inscrEstadual' => 'inscrEstadual', 'marketing' => 'marketing', 'p_id' => 'id', 'p_codigo' => 'codigo', 'p_nome' => 'nome', 'p_codNome' => 'codNome', 'p_sexo' => 'sexo', 'p_tipoPessoa' => 'tipoPessoa', 'p_estadoCivil' => 'estadoCivil', 'p_ativo' => 'ativo', 'p_sistema' => 'sistema', 'p_nascimento' => 'nascimento', 'p_cpf' => 'cpf', 'p_rg' => 'rg', 'p_rgOrgaoEmissor' => 'rgOrgaoEmissor', 'p_rgDataExpedicao' => 'rgDataExpedicao', 'p_profissao' => 'profissao', 'p_nacionalidade' => 'nacionalidade', 'p_inscricao' => 'inscricao', 'p_bloquearEnvioEmail' => 'bloquearEnvioEmail', 'p_observacoes' => 'observacoes', 'p_foto' => 'foto', 'p_site' => 'site', 'p_faceBook' => 'faceBook', 'p_twitter' => 'twitter', 'p_googlePlus' => 'googlePlus', 'p_skype' => 'skype', 'p_instagram' => 'instagram' );
	private $post_id;
	private $post;
	public function __construct(  ) {
	}
	public function importa( $empresa ) {
		if ( ! post_type_exists( 'empresa' ) ) {
			wp_send_json_error( ['message' => 'Post Type Empresa não existe!.'] );
			return;
		}
		$args = array(
			'meta_key' => 'codigo'
			, 'meta_value' => 1
			, 'post_type' => 'empresa'
			, 'post_status' => 'any'
			, 'numberposts' => 1
		);
		$post = get_posts( $args );
		if ( empty( $post ) ) {
			$this->post_id = $this->salvar( $empresa );
		} else {
			$this->post = $post[0];
			$this->post_id = $this->post->ID;
			$this->post_id = $this->atualizar( $this->post_id, $empresa );
		}
		wp_reset_postdata( );
	}
	private function salvar_metadados( $empresa ) {
		$properties = $this->recolhe_propriedades( $empresa );

		foreach( $properties as $key => $value ) {
			if (is_string($value) && trim($value) === '') {
				continue;
			}
			if (is_numeric($value) && $value <= 0) {
				continue;
			}
			if ((is_array($value) || is_object($value)) && empty($value)) {
				continue;
			}
			$mt = add_post_meta( $this->post_id, $key, $value,false );
			if ( is_wp_error( $mt ) ) {
				error_log( $mt );
			}
		}
		$emails = $this->recolhe_emails( $empresa['emails'] );
		foreach( $emails as $key => $value ) {
			if ( isset( $value['endereco'] ) ) {
				$endereco = $value['endereco'];
			}
			if ( ! empty( $endereco ) ) {
				add_post_meta( $this->post_id, 'email', $endereco,true );
			}
		}
		$enderecos = $this->recolhe_enderecos( $empresa['enderecos'] );
		foreach( $enderecos as $key => $value ) {
			$enderecoRenderizado = $value['enderecoRenderizado'];
			add_post_meta( $this->post_id, 'endereco', $enderecoRenderizado,false );
		}
		$telefones = $this->recolhe_telefones( $empresa['telefones'] );
		foreach( $telefones as $key => $value ) {
			$numero = $value['numero'];
			$tipo = strtolower( $value['tipo'] );
			if ( $tipo == 'whatsapp' ) {
				add_post_meta( $this->post_id, 'whatsapp', $numero,false );
			} else {
				add_post_meta( $this->post_id, 'telefone', $numero,false );
			}
		}
	}
	private function apagar_metadados( $post_id ) {
		global $wpdb;
		if ( !get_post( $post_id ) ) {
			return 0;
		}
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);
	}
	private function recolhe_propriedades( $empresa ) {
		$properties = array( );
		$set_propriedades = self::PROPRIEDADES;
		foreach ( $set_propriedades as $key => $value ) {
			if ( isset( $empresa[$key] ) ) {
				$properties[ $value ] = $empresa[ $key ];
			}
		}
		$properties['codigo'] = 1;
		return $properties;
	}
	public function salvar( $empresa ) {
        $obs = '';
        if ( isset($empresa['marketing']) ) {
            $obs = $empresa['marketing'];
        }
        if (empty($obs)) {
            if ( isset($empresa['nome']) ) {
                $obs = $empresa['nome'];
            }
        }
		$post_data = array(
			'post_title' => sanitize_title( $empresa['p_codNome']?? $empresa['nome'] )
			, 'post_content' => $obs
			, 'post_status' => 'publish'
			, 'post_type' => 'empresa'
			, 'post_date' => current_time( 'mysql' )
		);
		$result = wp_insert_post( $post_data);
		if ( is_wp_error( $this->post_id ) ) {
			wp_die( $this->post_id->get_error_messages( ) );
			return false;
		}
		$this->post_id = $result;
		$this->salvar_metadados( $empresa );
        if ( isset( $empresa['logo'] ) && !empty( $empresa['logo'] ) ) {
            $importa_fotos = new Pinedu_Imovel_Importa_Foto_Loja( $this->post_id, $empresa );
            $importa_fotos->salva_imagem_destaque();
        }
	}
	public function atualizar( $post_id, $empresa ) {
        if (!empty( $empresa['marketing'] )) {
            $obs = $empresa['marketing'];
        }
        if (empty($obs)) {
            if (!empty( $empresa['nome'] )) {
                $obs = $empresa['nome'];
            }
        }
		$post_data = array(
			'post_title' => sanitize_title( $empresa['p_codNome'] )
			, 'post_content' => $obs??''
			, 'post_status' => 'publish'
			, 'post_type' => 'empresa'
			, 'ID' => $post_id
		);
		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			error_log( 'Erro ao atualizar empresa: ' . $post_id->get_error_message( ) );
			return false;
		}
		$this->post_id = $result;
		$this->apagar_metadados( $post_id );
		$this->salvar_metadados( $empresa );
	}
	private function recolhe_telefones( $telefones ) {
		$tels = [];
		foreach ( $telefones as $tel ) {
			$tipoendereco = $tel['tipoendereco'];
			$tels[] = array(
				'numero' => $tel['telefone']
				, 'tipo' => $tipoendereco['nome']
			);
		}
		return $tels;
	}
	private function recolhe_emails( $emails ) {
		$ems = [];
		foreach ( $emails as $em ) {
			$ems[] = array(
				'endereco' => $em['endereco']
				, 'nome' => esc_html( $em['nome'] )
				, 'tipo' => $em['tipoendereco']['nome']
			);
		}
		return $ems;
	}
	private function recolhe_enderecos( $enderecos ) {
		$end = [];
		foreach ( $enderecos as $endereco ) {
			$end[] = array(
				'bairro' => $endereco[ 'bairro' ]
				, 'cep' => $endereco[ 'cep' ]
				, 'cidade' => $endereco[ 'cidade' ]
				, 'complemento' => $endereco[ 'complemento' ]
				, 'enderecoRenderizado' => $endereco[ 'enderecoRenderizado' ]
				, 'logradouro' => $endereco[ 'logradouro' ]
				, 'numero' => $endereco[ 'numero' ]
				, 'tipo' => $endereco[ 'tipo' ]
				, 'uf' => $endereco[ 'uf' ]
				, 'tipo' => $endereco['tipoendereco']['nome']
			);
		}
		return $end;
	}
}
