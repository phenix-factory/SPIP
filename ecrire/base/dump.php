<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2010                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

define('_VERSION_ARCHIVE', '1.3');

include_spip('base/serial');
include_spip('base/auxiliaires');
include_spip('public/interfaces'); // pour table_jointures

// NB: Ce fichier peut ajouter des tables (old-style)
// donc il faut l'inclure "en globals"
if ($f = find_in_path('mes_fonctions.php')) {
	global $dossier_squelettes;
	@include_once (_ROOT_CWD . $f);
}

if (@is_readable(_CACHE_PLUGINS_FCT)){
	// chargement optimise precompile
	include_once(_CACHE_PLUGINS_FCT);
}

function base_dump_meta_name($rub){
	return $meta = "status_dump_$rub_"  . $GLOBALS['visiteur_session']['id_auteur'];
}
function base_dump_dir($meta){
	// determine upload va aussi initialiser l'index "restreint"
	$maindir = determine_upload();
	if (!$GLOBALS['visiteur_session']['restreint'])
		$maindir = _DIR_DUMP;
	$dir = sous_repertoire($maindir, $meta);
	return $dir;
}

/**
 * Lister les tables non exportables par defaut
 * (liste completable par le pipeline lister_tables_noexport
 *
 * @staticvar array $EXPORT_tables_noexport
 * @return array
 */
function lister_tables_noexport(){
	// par defaut tout est exporte sauf les tables ci-dessous
	static $EXPORT_tables_noexport = null;
	if (!is_null($EXPORT_tables_noexport))
		return $EXPORT_tables_noexport;

	$EXPORT_tables_noexport= array(
		'spip_caches', // plugin invalideur
		'spip_resultats', // resultats de recherche ... c'est un cache !
		'spip_referers',
		'spip_referers_articles',
		'spip_visites',
		'spip_visites_articles',
		'spip_versions', // le dump des fragments n'est pas robuste
		'spip_versions_fragments' // le dump des fragments n'est pas robuste
		);

	if (!$GLOBALS['connect_toutes_rubriques']){
		$EXPORT_tables_noexport[]='spip_messages';
		$EXPORT_tables_noexport[]='spip_auteurs_messages';
	}

	$EXPORT_tables_noexport = pipeline('lister_tables_noexport',$EXPORT_tables_noexport);

	return $EXPORT_tables_noexport;
}

/**
 * Lister les tables non importables par defaut
 * (liste completable par le pipeline lister_tables_noexport
 *
 * @staticvar array $IMPORT_tables_noimport
 * @return array
 */
function lister_tables_noimport(){
	static $IMPORT_tables_noimport=null;
	if (!is_null($EXPORT_tables_noexport))
		return $EXPORT_tables_noexport;

	$IMPORT_tables_noimport = array();
	// par defaut tout est importe sauf les tables ci-dessous
	// possibiliter de definir cela tables via la meta
	// compatibilite
	if (isset($GLOBALS['meta']['IMPORT_tables_noimport'])){
		$IMPORT_tables_noimport = unserialize($GLOBALS['meta']['IMPORT_tables_noimport']);
		if (!is_array($IMPORT_tables_noimport)){
			include_spip('inc/meta');
			effacer_meta('IMPORT_tables_noimport');
		}
	}
	$IMPORT_tables_noimport = pipeline('lister_tables_noimport',$IMPORT_tables_noimport);
	return $IMPORT_tables_noimport;
}


/**
 * Lister les tables a ne pas effacer
 * (liste completable par le pipeline lister_tables_noerase
 *
 * @staticvar array $IMPORT_tables_noerase
 * @return array
 */
function lister_tables_noerase(){
	static $IMPORT_tables_noerase=null;
	if (!is_null($IMPORT_tables_noerase))
		return $IMPORT_tables_noerase;

	$IMPORT_tables_noerase = array(
		'spip_meta',
		// par defaut on ne vide pas les stats, car elles ne figurent pas dans les dump
		// et le cas echeant, un bouton dans l'admin permet de les vider a la main...
		'spip_referers',
		'spip_referers_articles',
		'spip_visites',
		'spip_visites_articles'
	);
	$IMPORT_tables_noerase = pipeline('lister_tables_noerase',$IMPORT_tables_noerase);
	return $IMPORT_tables_noerase;
}


/**
 * construction de la liste des tables pour le dump :
 * toutes les tables principales
 * + toutes les tables auxiliaires hors relations
 * + les tables relations dont les deux tables liees sont dans la liste
 *
 * @global <type> $tables_principales
 * @global <type> $tables_auxiliaires
 * @global <type> $tables_jointures
 * @param array $exclude_tables
 * @return array
 */
function base_liste_table_for_dump($exclude_tables = array()){
	$tables_for_dump = array();
	$tables_pointees = array();
	global $tables_principales;
	global $tables_auxiliaires;
	global $tables_jointures;

	// on construit un index des tables de liens
	// pour les ajouter SI les deux tables qu'ils connectent sont sauvegardees
	$tables_for_link = array();
	foreach($tables_jointures as $table => $liste_relations)
		if (is_array($liste_relations))
		{
			$nom = $table;
			if (!isset($tables_auxiliaires[$nom])&&!isset($tables_principales[$nom]))
				$nom = "spip_$table";
			if (isset($tables_auxiliaires[$nom])||isset($tables_principales[$nom])){
				foreach($liste_relations as $link_table){
					if (isset($tables_auxiliaires[$link_table])/*||isset($tables_principales[$link_table])*/){
						$tables_for_link[$link_table][] = $nom;
					}
					else if (isset($tables_auxiliaires["spip_$link_table"])/*||isset($tables_principales["spip_$link_table"])*/){
						$tables_for_link["spip_$link_table"][] = $nom;
					}
				}
			}
		}

	$liste_tables = array_merge(array_keys($tables_principales),array_keys($tables_auxiliaires));
	foreach($liste_tables as $table){
	  //		$name = preg_replace("{^spip_}","",$table);
	  if (		!isset($tables_pointees[$table])
	  		&&	!in_array($table,$exclude_tables)
	  		&&	!isset($tables_for_link[$table])){
			$tables_for_dump[] = $table;
			$tables_pointees[$table] = 1;
		}
	}
	foreach ($tables_for_link as $link_table =>$liste){
		$connecte = true;
		foreach($liste as $connect_table)
			if (!in_array($connect_table,$tables_for_dump))
				$connecte = false;
		if ($connecte)
			# on ajoute les liaisons en premier
			# si une restauration est interrompue,
			# cela se verra mieux si il manque des objets
			# que des liens
			array_unshift($tables_for_dump,$link_table);
	}
	return array($tables_for_dump, $tables_for_link);
}

/**
 * Vider les tables de la base de destination
 * pour la copie dans une base
 *
 * peut etre utilise pour l'import depuis xml,
 * ou la copie de base a base (mysql<->sqlite par exemple)
 *
 * @param array $tables
 * @param string $serveur
 */
function base_vider_tables_destination_copie($tables, $serveur=''){
	$trouver_table = charger_fonction('trouver_table', 'base');

	spip_log('Vider '.count($tables) . " tables sur serveur '$serveur' : " . join(', ', $tables),'base');
	foreach($tables as $table){
		// sur le serveur principal, il ne faut pas supprimer l'auteur loge !
		if (($table!='spip_auteurs') OR $serveur!=''){
			// regarder si il y a au moins un champ impt='non'
			$desc = $trouver_table($table);
			if (isset($desc['field']['impt']))
				sql_delete($table, "impt='oui'", $serveur);
			else
				sql_delete($table, "", $serveur);
		}
	}

	// sur le serveur principal, il ne faut pas supprimer l'auteur loge !
	// Bidouille pour garder l'acces admin actuel pendant toute la restauration
	if ($serveur=='') {
		spip_log('Conserver copieur '.$GLOBALS['visiteur_statut']['id_auteur'] . " dans id_auteur=0 pour le serveur '$serveur'",'dump');
		sql_delete("spip_auteurs", "id_auteur=0",$serveur);
		// utiliser le champ webmestre pour stocker l'ancien id ne marchera pas si l'id comporte plus de 3 chiffres...
		sql_updateq('spip_auteurs', array('id_auteur'=>0, 'webmestre'=>$GLOBALS['visiteur_statut']['id_auteur']), "id_auteur=".intval($GLOBALS['visiteur_statut']['id_auteur']),array(),$serveur);
		sql_delete("spip_auteurs", "id_auteur!=0",$serveur);
	}

}


/**
 * Effacement de la bidouille ci-dessus
 * Toutefois si la table des auteurs ne contient plus qu'elle
 * c'est que la copie etait incomplete et on restaure le compte
 * pour garder la connection au site
 *
 * (mais il doit pas etre bien beau
 * et ca ne marche que si l'id_auteur est sur moins de 3 chiffres)
 *
 * @param string $serveur
 */
function base_detruire_copieur_si_besoin($serveur='')
{
	// rien a faire si ce n'est pas le serveur principal !
	if ($serveur=='') {
		if (sql_countsel("spip_auteurs", "id_auteur<>0")) {
			spip_log("Detruire copieur id_auteur=0 pour le serveur '$serveur'",'dump');
			sql_delete("spip_auteurs", "id_auteur=0", $serveur);
		}
		else {
			spip_log("Restaurer copieur id_auteur=0 pour le serveur '$serveur' (aucun autre auteur en base)",'dump');
			sql_update('spip_auteurs', array('id_auteur'=>'webmestre', 'webmestre'=>"'oui'"), "id_auteur=0");
		}
	}
}

/**
 *
 * @param <type> $tables
 * @param <type> $serveur_source
 * @param <type> $serveur_dest
 * @param <type> $callback_progression
 * @param <type> $max_time
 * @param <type> $drop_source
 * @return <type>
 */
function base_copier_tables($tables, $serveur_source, $serveur_dest, $callback_progression = '', $max_time=0, $drop_source = false) {
	spip_log("Copier ".count($tables)." tables de '$serveur_source' vers '$serveur_dest'",'dump');

	$status_file = _DIR_TMP . "dump-copy-".md5(serialize($tables)."-$serveur_source-$serveur_dest").".txt";
	if (!lire_fichier($status_file, $status)
		OR !$status = unserialize($status))
		$status = array();

	// puis relister les tables a importer
	// et les vider si besoin, au moment du premier passage ici
	// (et seulement si ce n'est pas une fusion, comment le dit-on ?)
	$initialisation_copie = (!isset($status["dump_status_copie"])) ? 0 :
		$status["dump_status_copie"];

	// si init pas encore faite, vider les tables du serveur destination
	if (!$initialisation_copie) {
		base_vider_tables_destination_copie($tables, $serveur_dest);
		$status["dump_status_copie"]='ok';
		ecrire_fichier($status_file,serialize($status));
	}

	spip_log("Tables a copier :".implode(", ",$tables),'dump');

	// les tables auteurs et meta doivent etre copiees en dernier !
	if (in_array('spip_auteurs',$tables)){
		$tables = array_diff($tables,array('spip_auteurs'));
		$tables[] = 'spip_auteurs';
	}
	if (in_array('spip_meta',$tables)){
		$tables = array_diff($tables,array('spip_meta'));
		$tables[] = 'spip_meta';
	}

	foreach ($tables as $table){
		// verifier que la table est presente dans la base source
		if ($desc_source = sql_showtable($table,false,$serveur_source)){
			// $status['tables_copiees'][$table] contient l'avancement
			// de la copie pour la $table : 0 a N et -1 quand elle est finie
			if (!isset($status['tables_copiees'][$table]))
				$status['tables_copiees'][$table] = 0;

			// si la table n'existe pas dans la destination, la creer a l'identique !
			if (!$desc_dest = sql_showtable($table,false,$serveur_dest)) {
				$autoinc = (isset($desc_source['keys']['PRIMARY KEY']) AND strpos($desc_source['keys']['PRIMARY KEY'],',')===false);
				sql_create($table, $desc_source['field'], $desc_source['key'], $autoinc, false, $serveur_dest);
				$desc_dest = sql_showtable($table,false,$serveur_dest);
			}
			if ($status['tables_copiees'][$table]!==-1){
				if ($callback_progression)
					$callback_progression(0,0,$table);
				while (true) {
					$n = intval($status['tables_copiees'][$table]);
					// on copie par lot de 400
					$res = sql_select('*',$table,'','','',"$n,400",'',$serveur_source);
					while ($row = sql_fetch($res,$serveur_source)){
						// si l'enregistrement est deja en base, ca fera un echec ou un doublon
						sql_insertq($table,$row,$desc_dest,$serveur_dest);
						$status['tables_copiees'][$table]++;
						if ($max_time AND time()>$_SERVER['REQUEST_TIME']+$max_time)
							break;
					}
					if ($n == $status['tables_copiees'][$table])
						break;
					spip_log("recopie $table ".$status['tables_copiees'][$table],'dump');
					if ($callback_progression)
						$callback_progression($status['tables_copiees'][$table],0,$table);
					ecrire_fichier($status_file,serialize($status));
					if ($max_time AND time()>$_SERVER['REQUEST_TIME']+$max_time)
						return false; // on a pas fini, mais le temps imparti est ecoule
				}
				if ($drop_source) {
					sql_drop_table($table,'',$serveur_source);
					spip_log("drop $table sur serveur source '$serveur_source'",'dump');
				}
				$status['tables_copiees'][$table]=-1;
				ecrire_fichier($status_file,serialize($status));
				spip_log("tables_recopiees ".implode(',',$status['tables_copiees']),'dump');
			}
		}
	}

	base_detruire_copieur_si_besoin($serveur_dest);
	spip_unlink($status_file);
	// OK, copie complete
	return true;
}
?>
