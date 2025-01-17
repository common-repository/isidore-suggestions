<?php
/**
 * Plugin Name:	Isidore Suggestions 
 * Description:	With Isidore Suggestions, you can expand your posts with recommendations from Isidore, the research platform in humanities with more than 5 millions datasets.
 * Version:		4.0.0
 * Author:		HUMA-NUM
 * Author URI:	http://www.huma-num.fr 
 * License:		GPL-2.0+
 * License URI:	https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:	/languages
 * Text Domain:	isidore-suggestions
 *
 *		Copyright 2019 HUMA-NUM
 *
 * 		This program is free software; you can redistribute it and/or modify
 * 		it under the terms of the GNU General Public License, version 2, as 
 * 		published by the Free Software Foundation.
 *
 * 		This program is distributed in the hope that it will be useful,
 * 		but WITHOUT ANY WARRANTY; without even the implied warranty of
 * 		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
 * 		GNU General Public License for more details.
 * 
 * 		You should have received a copy of the GNU General Public License
 * 		along with this program; if not, write to the Free Software
 * 		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Isidore_suggestions extends WP_Widget {

	const ISIDORE_API_ENDPOINT = 'https://api.isidore.science';
	const ISIDORE_WEBSITE_URL = '//isidore.science';
	const AFS_STATUS = 'stable';
	const AFS_SERVICE = '7010';
    	
	/**
	 * Configuration du widget
	 *
	 */
	function __construct() {
		$widget_ops = array(
			'classname'		=> 'isidore-suggestions',
			'description'	=> __( 'Displays suggestions to resources from Isidore for a post based on its tags.', 'isidore-suggestions' )
		);
		$control_ops = array(
			'width'		=> '100%',
			'height'	=> 350,
			'id_base'	=> 'isidore-suggestions'
		);
		//create the widget (since 4.3)
		parent::__construct(
			'isidore-suggestions',
			__('Isidore suggestions', 'isidore-suggestions'),
			$widget_ops,
			$control_ops
		);
		
	}
	
	/**
	 * Affichage du widget
	 *
	 */
	function widget( $args, $instance ) {
		
		extract( $args );
		
		$widget_content = ''; // Contenu du widget
		
		// Si seulement un article affiché
		if ( is_single() ) {
			global $post;
			
			$tags_names		= wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
			$spans_isidore	= $this->isidore_parse_post_content( $post->post_content );
			
			if ( ( count( $tags_names ) > 0 ) or ( count( $spans_isidore ) > 0 ) ) {
				
				$query = $this->isidore_query_builder( $tags_names, $instance['disciplines_suggestions'], $spans_isidore );
				
				if( $instance['title_isidore_search'] != '' ) {
					$widget_content .= $before_title . '<a href="' . self::ISIDORE_WEBSITE_URL . '" target="_blank"><img id="widget-isidore-suggestions-logo" src="' . plugins_url( 'images/logoisidore.png', __FILE__ ) . '" alt="logo_isidore" width="30px" style="display:inline-block;vertical-align: middle;margin-right:10px;"></a><span style="vertical-align:middle;">' . $instance['title_suggestions'] . '</span>' . $after_title;
				}
				// div contenant les paramètres nécessaires pour afficher les suggestions avec AJAX
				$widget_content .= '<div class="isidore-suggestions-list" data-isidore-suggestions-query="' . $query . '" data-isidore-suggestions-limit="' . $instance['nb_suggestions'] . '"><span class="isidore-loader"><img src="' . plugins_url( 'images/loader.gif', __FILE__ ) . '"></span><ul></ul></div>';
					
			}
		}
		
		// Champ de recherche vers http://rechercheisidore.fr
		if ( 'on' == $instance['isidore-input-search'] ) {
			
			if( $widget_content != '' ) {
				$widget_content .= '<br/><br/>';
			}
								
			if( $instance['title_isidore_search'] != '' ) {
				$widget_content .= $before_title . $instance['title_isidore_search'] . $after_title;
			}
						
			/* si au moins un critère de discipline est présent, 
			on prévoit une chaîne de caractère à ajouter à l'id de l'input 
			sur lequel se fait l'autocomplétion afin qu'elle n'ait pas lieu 
			(évite de proposer des requêtes sans résultats) */
			if( count( $instance['disciplines_isidore_search'] ) > 0 ) {
				$restricted = 'restricted-';
			} else {
				$restricted = '';
			}
			
			// Info à propos du thème utilisé
			$my_theme 			= wp_get_theme ();
			$my_theme_name 		= $my_theme->get('Name');
			$my_theme_template	= $my_theme->get('Template');
								
			$widget_content .= '<form id="isidore-search-form-' . $this->number . '" role="search" method="get" class="search-form" action="' . self::ISIDORE_WEBSITE_URL . '/s" autocomplete="off">
				<label>
				<span class="screen-reader-text">' . __( 'Search in Isidore', 'isidore-suggestions' ) . '</span>
				<input id="isidore-search-' . $restricted . 'input-' . $this->number . '" type="search" class="search-field" name="q" placeholder="' . __( 'Search in Isidore', 'isidore-suggestions' ) . ' &hellip;" title="' . __( 'Search in Isidore', 'isidore-suggestions' ) . '" />';
			// Intégration des disciplines aux critères de recherche
			foreach ( $instance['disciplines_isidore_search'] as $hal_domain ) {
				$widget_content .= '<input id="isidore-search-input-' . $this->number . '-' . str_replace( '.', '-', $hal_domain ) . '" type="hidden" name="discipline" value="http://aurehal.archives-ouvertes.fr/subject/' . $hal_domain . '" />';
			}
			$widget_content .= '</label>';
			
			// Intégration du bouton submit adapté en fonction du thème utilisé (traitement à part de Twenty Sixteen et ses clones)
			if (  'Twenty Sixteen' == $my_theme_name or 'twentysixteen' == $my_theme_template ) {
				$widget_content .= '<button id="isidore-search-button-' . $this->number . '" type="submit" class="search-submit"><span class="screen-reader-text">' . __( 'Search', 'isidore-suggestions' ) . '</span></button>';
			} else {		
				$widget_content .= '<input id="isidore-search-button-' . $this->number . '" type="submit" class="search-submit screen-reader-text" value="' . __( 'Search', 'isidore-suggestions' ) . '">';
			}
				
			$widget_content .= '</form>';	
		}
		
		// Affichage du contenu du widget
		if ( $widget_content != '' ) {
			echo $before_widget;
			echo $widget_content;
			echo $after_widget;
		}
	}
	
	/**
	 * Mise à jour des options
	 *
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		
		$instance['title_suggestions']			=	strip_tags( $new_instance['title_suggestions'] );
		$instance['nb_suggestions']				=	strip_tags( $new_instance['nb_suggestions'] );
		$instance['disciplines_suggestions']	=	array();
		if ( is_array( $new_instance['disciplines_suggestions']) ) {
			foreach ( $new_instance['disciplines_suggestions'] as $key => $value ) {
				$instance['disciplines_suggestions'][ $key ] = $value;
			}
		}
		$instance['isidore-input-search']		=	$new_instance['isidore-input-search'];
		$instance['title_isidore_search']		=	strip_tags( $new_instance['title_isidore_search'] );
		$instance['disciplines_isidore_search']	=	array();
		if ( is_array( $new_instance['disciplines_isidore_search']) ) {
			foreach ( $new_instance['disciplines_isidore_search'] as $key => $value ) {
				$instance['disciplines_isidore_search'][ $key ] = $value;
			}
		}
		
		return $instance;
	}
	
	/**
	 * Formulaire des réglages du widget
	 *
	 */
	function form( $instance ) { 
		$defaults = array(
			'title_suggestions' 		=>	__( 'Isidore suggestions', 'isidore-suggestions' ),
			'nb_suggestions'			=>	3,
			'disciplines_suggestions'	=>	array(),
			'isidore-input-search'		=>	'on',
			'title_isidore_search' 		=>	__( 'Search in Isidore', 'isidore-suggestions' ),
			'disciplines_isidore_search'	=>	array()
		);
		$instance = wp_parse_args( $instance, $defaults );
		
		$hal_domain = array(
			'shs.anthro-bio'	=>	__( 'Biological anthropology', 'isidore-suggestions' ),
			'shs.anthro-se'		=>	__( 'Social Anthropology and ethnology', 'isidore-suggestions' ),
			'shs.archi'			=>	__( 'Architecture, space management', 'isidore-suggestions' ),
			'shs.archeo'		=>	__( 'Archaeology and Prehistory', 'isidore-suggestions' ),
			'shs.art'			=>	__( 'Art and history', 'isidore-suggestions' ),
			'shs.droit'			=>	__( 'Law', 'isidore-suggestions' ),
			'shs.demo'			=>	__( 'Demography', 'isidore-suggestions' ),
			'shs.eco'			=>	__( 'Economies and finances', 'isidore-suggestions' ),
			'shs.edu'			=>	__( 'Education', 'isidore-suggestions' ),
			'shs.class'			=>	__( 'Classical studies', 'isidore-suggestions' ),
			'shs.envir'			=>	__( 'Environmental studies', 'isidore-suggestions' ),
			'shs.genre'			=>	__( 'Gender studies', 'isidore-suggestions' ),
			'shs.gestion'		=>	__( 'Management', 'isidore-suggestions' ),
			'shs.geo'			=>	__( 'Geography', 'isidore-suggestions' ),
			'shs.hist'			=>	__( 'History', 'isidore-suggestions' ),
			'shs.hisphilso'		=>	__( 'History, Philosophy and Sociology of Sciences', 'isidore-suggestions' ),
			'shs.museo'			=>	__( 'Cultural heritage and museology', 'isidore-suggestions' ),
			'shs.langue'		=>	__( 'Linguistics', 'isidore-suggestions' ),
			'shs.litt'			=>	__( 'Literature', 'isidore-suggestions' ),
			'shs.musiq'			=>	__( 'Musicology and performing arts', 'isidore-suggestions' ),
			'shs.stat'			=>	__( 'Methods and statistics', 'isidore-suggestions' ),
			'shs.phil'			=>	__( 'Philosophy', 'isidore-suggestions' ),
			'shs.psy'			=>	__( 'Psychology', 'isidore-suggestions' ),
			'shs.relig'			=>	__( 'Religions', 'isidore-suggestions' ),
			'shs.scipo'			=>	__( 'Political science', 'isidore-suggestions' ),
			'shs.info'			=>	__( 'Communication sciences', 'isidore-suggestions' ),
			'shs.socio'			=>	__( 'Sociology', 'isidore-suggestions' )
		);
		asort( $hal_domain );
		?>
		<h3><?php _e( 'Suggestions', 'isidore-suggestions' ); ?></h3>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'title_suggestions' ); ?>"><?php _e( 'Title', 'isidore-suggestions' ); ?></label>
			<input type="text" id="<?php echo $this->get_field_id( 'title_suggestions' ); ?>" name="<?php echo $this->get_field_name( 'title_suggestions' ); ?>" value="<?php echo $instance['title_suggestions']; ?>" style="width:100%" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'nb_suggestions' ); ?>"><?php _e( 'Number of suggestions', 'isidore-suggestions' ); ?></label>
			<select id="<?php echo $this->get_field_id( 'nb_suggestions' ); ?>" name="<?php echo $this->get_field_name( 'nb_suggestions' ); ?>" value="<?php echo $instance['nb_suggestions']; ?>" style="width:100%;" >
				<?php for ( $i = 1; $i <= 10; $i++ ) {
					if ( $instance['nb_suggestions'] == $i ) {
						$selected = ' selected="selected"';
					} else {
						$selected = '';
					}
					echo '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
				}
				?>
			</select>
		</p>
		<p>
			<label><?php _e( 'Disciplines', 'isidore-suggestions' ); ?></label><br/>
		<?php foreach ( $hal_domain as $key => $value ) {
			if ( in_array( $key, $instance['disciplines_suggestions'] ) ) {
				$checked_discipline = ' checked="checked"';
			} else {
				$checked_discipline = '';
			}
			echo '<input type="checkbox" class="checkbox" id="' . $this->get_field_id( 'disciplines_suggestions' ) . '-' . $key . '" name="' . $this->get_field_name( 'disciplines_suggestions' ) . '[]" value="' . $key . '"' . $checked_discipline . '>
			<label for="' . $this->get_field_id( 'disciplines_suggestions' ) . '-' . $key . '">' . $value . '</label><br/>';
		}
		?>
		</p>
		
		<h3><?php _e( 'Input search', 'isidore-suggestions' ); ?></h3>
		
		<p>
			<input type="checkbox" class="checkbox" <?php checked( $instance['isidore-input-search'], 'on' ); ?> id="<?php echo $this->get_field_id( 'isidore-input-search' ); ?>" name="<?php echo $this->get_field_name( 'isidore-input-search' ); ?>">
			<label for="<?php echo $this->get_field_id( 'isidore-input-search' ); ?>"><?php _e( 'Add an Isidore search box', 'isidore-suggestions' ); ?></label><br/>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'title_isidore_search' ); ?>"><?php _e( 'Title', 'isidore-suggestions' ); ?></label>
			<input type="text" id="<?php echo $this->get_field_id( 'title_isidore_search' ); ?>" name="<?php echo $this->get_field_name( 'title_isidore_search' ); ?>" value="<?php echo $instance['title_isidore_search']; ?>" style="width:100%" />
		</p>
		
		<p>
			<label><?php _e( 'Disciplines', 'isidore-suggestions' ); ?></label><br/>
		<?php foreach ( $hal_domain as $key => $value ) {
			if ( in_array( $key, $instance['disciplines_isidore_search'] ) ) {
				$checked_discipline = ' checked="checked"';
			} else {
				$checked_discipline = '';
			}
			echo '<input type="checkbox" class="checkbox" id="' . $this->get_field_id( 'disciplines_isidore_search' ) . '-' . $key . '" name="' . $this->get_field_name( 'disciplines_isidore_search' ) . '[]" value="' . $key . '"' . $checked_discipline . '>
			<label for="' . $this->get_field_id( 'disciplines_isidore_search' ) . '-' . $key . '">' . $value . '</label><br/>';
		}
		?>
		</p>
	<?php
	}
	
	/**
	 * Reconstitue une requête vers l'API d'Isidore à partir d'une liste de mots-clefs
	 * et d'une liste de disciplines.
	 *
	 * @param array $tags_array liste de mots-clefs d'un article
	 * @param array $disciplines_array liste d'identifiants de disciplines Isidore
	 * @param array $fts_array liste de données textuelles extraits d'un article
	 *
	 * @return string url d'une requête vers l'API d'Isidore
	 */
	function isidore_query_builder( $tags_array, $disciplines_array, $fts_array ) {
		$query 				= self::ISIDORE_API_ENDPOINT . '/afs/search?afs:query=' . 
								'&afs:service=' . self::AFS_SERVICE .
								'&afs:status=' . self::AFS_STATUS .
								'&afs:feed=DOCUMENTS' .
								'&afs:clientData=main' .
								'&afs:log=ISI_SUGGESTIONS_WP' .
                                '&afs:facetDefault=replies=0' .
                                '&afs:lang=' . urlencode( $this->isidore_get_lang() );
		$filter_subjects	= ''; //filtres pour les mots-clefs
		$filter_disciplines	= ''; //filtres pour les disciplines
		$filter_fts			= ''; //filtres fts (contenu des balises span isidore)
		
		$i = 0;
		//$tag_limit = 3; // Nombre limite de mots-clefs pris en compte (ex: les trois premiers)
		foreach ( $tags_array as $tag ) {
			//if ( $i < $tag_limit ) {
				if ( $i > 0 ) {
					$filter_subjects .= ' or ';
				}
				$filter_subjects .= 'vfts("subject", "' . $tag . '")';
				$i++;
			//}
		}
		if ( $filter_subjects != '' ) {
			$query .= '&afs:filter=' . urlencode( $filter_subjects );
		}
		
		$j = 0;
		foreach ( $disciplines_array as $discipline ) {
			if ( $j > 0 ) {
				$filter_disciplines .= ' or ';
			}
			$filter_disciplines .= '(topic="http://aurehal.archives-ouvertes.fr/subject/' . $discipline . '")';
			$j++;
		}
		if ( $filter_disciplines != '' and count( $disciplines_array ) != 27 ) { //27 quand on coche toutes les disciplines (plus d'intérêt de mettre en place un filtre !)
			$query .= '&afs:filter=' . urlencode( $filter_disciplines );
		}
		
		$k = 0;
		foreach ( $fts_array as $fts ) {
			if ( $k > 0 ) {
				$filter_fts .= ' or ';
			}
			$filter_fts .= 'fts("' . $fts . '")';
			$k++;
		}
		if ( $filter_fts != '' ) {
			$query .= '&afs:filter=' . urlencode( $filter_fts );
		}
		
		return $query;
	}
    
    /**
     * Récupère la langue utilisée pour requêter l'API d'ISIDORE
     *
     * @return string langue retenue entre fr|en|es
     */
    static function isidore_get_lang() {
        $locale = get_locale();
        if ( preg_match( '/fr/', $locale ) ) {
            $lang = 'fr';
        } else if ( preg_match( '/es/', $locale ) ) {
            $lang = 'es';
        } else {
            $lang = 'en';
        }
        return $lang;
    }
	
	/**
	 * Parse le contenu de l'article pour récupérer les contenus des éléments 
	 * <span class="isidore"></span> s'ils sont présents
	 *
	 * @param string $content le contenu d'un article wordpess
	 *
	 * @return array le contenu des balises span isidore si présentes
	 */
	function isidore_parse_post_content( $content ) {
		$spans_isidore = array();
		preg_match_all( "#<span class=\"isidore\">(.*?)<\/span>#", $content, $isidore );
		if ( isset( $isidore[1] ) ) {
			// Nettoyage
			foreach ( $isidore[1] as $span_content ) {
				$span_content = strip_tags( $span_content );
				$span_content = preg_replace('#\"#','', $span_content);
				$span_content = trim( $span_content );
				if ( $span_content != "" ){
					$spans_isidore[] = $span_content;
				}
			}
			
		}
		return $spans_isidore;
	}
	
	/**
	 * Chargement JS et CSS pour le frontEnd
	 *
	 */
	static function isidore_suggestions_front_header() {
		
    	if ( is_active_widget( false, false, 'isidore-suggestions', true ) ) {
        	
			wp_register_style( 'css_front_isidore_suggestions', plugins_url( 'css/front-isidore-suggestions.css', __FILE__) );
			wp_enqueue_style( 'css_front_isidore_suggestions' );
			
			wp_register_script( 'js_autocomplete_isidore_suggestions', plugins_url( 'js/autocomplete-isidore-suggestions.js', __FILE__), array( 'jquery', 'jquery-ui-autocomplete' ) );
        	wp_localize_script( 'js_autocomplete_isidore_suggestions', 'IsidoreSuggestionsAutocomplete', array( 
        		'url'		=>	admin_url( 'admin-ajax.php' ),
        		'action'	=>	'isidore_suggestions_autocomplete',
        		'nonce'		=>	wp_create_nonce( 'autocomplete_isidore_suggestions_nonce' )
        	) );
        	wp_enqueue_script( 'js_autocomplete_isidore_suggestions' );
        	
        	wp_register_script( 'js_load_list_isidore_suggestions', plugins_url( 'js/load-list-isidore-suggestions.js', __FILE__), array( 'jquery' ) );
        	wp_localize_script( 'js_load_list_isidore_suggestions', 'LoadListIsidoreSuggestions', array( 
        		'url'		=>	admin_url( 'admin-ajax.php' ),
        		'action'	=>	'load_list_isidore_suggestions',
        		'nonce'		=>	wp_create_nonce( 'load_list_isidore_suggestions_nonce' )
        	) );
        	wp_enqueue_script( 'js_load_list_isidore_suggestions' );
        	
    	}
	}
	
	/**
	 * Fonction pour l'autocomplétion du champ de recherche Isidore
	 *
	 * @return string liste de termes pour l'autocompletion au format json
	 */
	static function isidore_suggestions_autocomplete() {
		
		// Verification du nonce
		$retrieved_nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $retrieved_nonce, 'autocomplete_isidore_suggestions_nonce' ) ) die( 'Failed security check' );
	
		$term 			=	urlencode( strtolower( $_POST['term'] ) );
		$suggestions	=	array();
		
		// Constitution de la requête
		$url			=	self::ISIDORE_API_ENDPOINT . '/afs/acp?afs:service=' . self::AFS_SERVICE . '&afs:status=' . self::AFS_STATUS . '&afs:query=' . $term;
	
		// On récupère le flux XML de la requête AFS
		$reponse		=	wp_remote_fopen( $url );
	
		// Si on arrive bien à récupérer une réponse AFS
		if ( ! is_wp_error( $reponse ) ) {
		
			$afs_suggestions = json_decode( $reponse, true );
			// Mots-Clés
			if ( isset( $afs_suggestions['subjects'] ) ) {
				$subjects = $afs_suggestions['subjects'][1];
				if ( count( $subjects ) > 0 ) {
					foreach ( $subjects as $subject ) {
					
						$suggestion				= array();
						$suggestion['label']	= $subject;
						$suggestion['link']		= self::ISIDORE_WEBSITE_URL . '/s?q=' . urlencode( $subject );
						$suggestion['category'] = __( 'Keywords', 'isidore-suggestions' );
			
						$suggestions[]			= $suggestion;
					}
				}
			}
			// Auteurs
			if ( isset( $afs_suggestions['creators'] ) ) {
				$creators = $afs_suggestions['creators'][1];
				if ( count( $creators ) > 0 ) {
					foreach ( $creators as $creator ) {
					
						$suggestion				= array();
						$suggestion['label']	= $creator;
						$suggestion['link']		= self::ISIDORE_WEBSITE_URL . '/s?q=' . urlencode( $creator );
						$suggestion['category'] = __( 'Authors', 'isidore-suggestions' );
			
						$suggestions[]			= $suggestion;
					}
				}
			}
		} 
    	
    	$return = json_encode( $suggestions );
    	echo $return;
    	wp_die();

	}
	
	/**
	 * Fonction pour charger les suggestions
	 *
	 * @return string liste html des suggestions
	 */
    static function load_list_isidore_suggestions() {
	 
	 	// Verification du nonce
		$retrieved_nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $retrieved_nonce, 'load_list_isidore_suggestions_nonce' ) ) die( __('AJAX error : Failed security check !', 'isidore-suggestions') );
		
		$query	= $_POST['query'];
		$limit	= $_POST['limit'];
		$page 	= $_POST['page'];
		
		$ul		= '';
		
		// Verification des arguments $query et $limit
		if( ! preg_match( '#^' . self::ISIDORE_API_ENDPOINT . '/afs/search#', $query ) or ! ctype_digit( strval( $limit ) ) or ! ctype_digit( strval( $page ) ) ) {
			// Si les parametres de recherche ne sont pas valides
			echo '<li>' . __( 'API error : Cataclysmic error ! Contact the support...', 'isidore-suggestions' ) . '</li>';
			wp_die();
		}
		
		$reponse = wp_remote_fopen( $_POST['query'] . '&afs:page=' . $page . '&afs:replies=' . $limit);
		
		if ( is_wp_error( $reponse ) ) {
			echo '<li>' . __( 'API error : No response !', 'isidore-suggestions' ) . '</li>';
			wp_die();
		}

		$xml = new Domdocument();
		$load = @$xml->loadXML( $reponse );

		if ( ! $load ) {
			echo '<li>' . __( 'XML error : Unable to load the data !', 'isidore-suggestions' ) . '</li>';
			wp_die();
		}
					
		$xpath = new DOMXpath( $xml );
			
		$xpath_query = '//afs:clientData[@id="main"]/isidore';
		//requête XPATH pour récupérer la liste des noeuds isidore
		$nodes = $xpath->query( $xpath_query );
		// Si on a bien un résultat
		if ( 0 == $nodes->length ) {
			echo '<li>' . __( 'No suggestion', 'isidore-suggestions' ) . ' &hellip;</li>';
			wp_die();
		}
		
		foreach ( $nodes as $node ) {
			$uri = $node->getAttribute( 'uri' );
            $title = '';
			foreach ( $node->childNodes as $child ) {
				if ( 'title' == $child->nodeName ) {
                    if ( '' == $title ) {
                        $title = $child->nodeValue;
                    } elseif ( $child->hasAttribute('xml:lang') and $child->getAttribute('xml:lang') == self::isidore_get_lang() ) {
                        $title = $child->nodeValue;
                        break; // on prend en compte uniquement le premier titre dans la langue cible
                    }
				}
			}
			$ul .= '<li><a href="' . self::ISIDORE_WEBSITE_URL . '/document/' . $uri . '" target="_blank">' . $title . '</a></li>';
		}
		//ajout du lien vers plus de résultats (si possible)
		$nextPage = $xpath->query( '//afs:replySet/afs:pager/@nextPage' );
		if( $nextPage->length > 0 ) {
			$ul .= '<li style="text-align:center;list-style-type:none;padding-top:10px"><button class="isidore-suggestions-showmore" nextPage="' . $nextPage->item(0)->nodeValue . '">' . __( 'More results ...', 'isidore-suggestions' ) . '</button></li>';
		}
					
		echo $ul;
		wp_die();
	}		
}

// Affichage du widget grâce à un hook
add_action( 'widgets_init', function(){
    register_widget('Isidore_suggestions');
} );
load_plugin_textdomain( 'isidore-suggestions', false, basename( dirname( __FILE__ ) ) . '/languages/' );

// Hook pour charger les scripts et les feuilles de style frontend
add_action ( 'wp_enqueue_scripts', array( 'Isidore_suggestions', 'isidore_suggestions_front_header' ) );

// Hook ajax (pour l'autocompletion)
add_action( 'wp_ajax_isidore_suggestions_autocomplete', array( 'Isidore_suggestions', 'isidore_suggestions_autocomplete' ) );
add_action( 'wp_ajax_nopriv_isidore_suggestions_autocomplete', array( 'Isidore_suggestions', 'isidore_suggestions_autocomplete' ) );
// Hook ajax (pour le chargement des suggestions)
add_action( 'wp_ajax_load_list_isidore_suggestions', array( 'Isidore_suggestions', 'load_list_isidore_suggestions' ) );
add_action( 'wp_ajax_nopriv_load_list_isidore_suggestions', array( 'Isidore_suggestions', 'load_list_isidore_suggestions' ) );

/* TinyMCE */
//add style to the TinyMCE visual editor
add_action( 'admin_init', 'isidore_bouton_style' );
function isidore_bouton_style() {
    add_editor_style(  plugins_url( 'TinyMCE/css/back-isidore-suggestions.css', __FILE__) );
}
//register and add new button
add_action( 'admin_head', 'isidore_suggestions_button' );
function isidore_suggestions_button() {
	global $typenow;
    // check user permissions
    if ( ! current_user_can('edit_posts') ) {
    	return;
    }
    // verify the post type
    if( ! in_array( $typenow, array( 'post' ) ) ) {
        return;
    }
    // check if WYSIWYG is enabled
    if ( 'true' == get_user_option('rich_editing') ) {
    	add_filter( 'mce_external_plugins', 'isidore_suggestions_add_button' );
    	add_filter( 'mce_buttons', 'isidore_suggestions_register_button' );
    }
}
//specify the path to the script with our plugin for TinyMCE
function isidore_suggestions_add_button( $plugin_array ) {
    $plugin_array['isidoreSuggestions'] = plugins_url( 'TinyMCE/js/back-isidore-suggestions.js', __FILE__);
    return $plugin_array;
}
//add the button in the editor
function isidore_suggestions_register_button( $buttons ) {
    array_push( $buttons, 'addIsidoreSpan'); // ajout d'un bouton addIsidoreSpan
    return $buttons;
}

/* Gutenberg */
// add Isidore Suggestions custom format
add_action( 'init', 'isidore_suggestions_custom_format_script_register' );
function isidore_suggestions_custom_format_script_register() {
    wp_register_script(
        'isidore-suggestions-custom-format-js',
        plugins_url( 'js/isidore-suggestions-custom-format.js', __FILE__ ),
        array( 'wp-rich-text', 'wp-element', 'wp-editor' )
    );
}
add_action( 'enqueue_block_editor_assets', 'isidore_suggestions_custom_format_enqueue_assets_editor' );
function isidore_suggestions_custom_format_enqueue_assets_editor() {
	global $typenow;
	// check user permissions
    if ( ! current_user_can('edit_posts') ) {
    	return;
    }
    // verify the post type
    if( ! in_array( $typenow, array( 'post' ) ) ) {
        return;
    }
    wp_enqueue_script( 'isidore-suggestions-custom-format-js' );
}

?>