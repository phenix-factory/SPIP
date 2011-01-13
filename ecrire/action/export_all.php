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

include_spip('inc/lang');
include_spip('inc/actions');

// http://doc.spip.org/@action_export_all_dist
function action_export_all_dist()
{
	$securiser_action = charger_fonction('securiser_action', 'inc');
	$arg = $securiser_action();

	@list($quoi, $gz, $archive, $rub, $version) = explode(',', $arg);
	$meta = "status_dump_$rub_"  . $GLOBALS['visiteur_session']['id_auteur'];
	$tables = _request('export');
	// determine upload va aussi initialiser l'index "restreint"
	$maindir = determine_upload();
	if (!$GLOBALS['visiteur_session']['restreint'])
		$maindir = _DIR_DUMP;
	$dir = sous_repertoire($maindir, $meta);
	$file = $dir . $archive;

	utiliser_langue_visiteur();
	if ($quoi =='start'){
	// en mode partiel, commencer par les articles et les rubriques
	// pour savoir quelles parties des autres tables sont a sauver
		if ($rub) {
			if ($t = array_search('spip_rubriques', $tables)) {
				unset($tables[$t]);
				array_unshift($tables, 'spip_rubriques');
			}
			if ($t = array_search('spip_articles', $tables)) {
				unset($tables[$t]);
				array_unshift($tables, 'spip_articles');
			}
		}
		// creer l'en tete du fichier et retourner dans l'espace prive
		ecrire_fichier($file, export_entete($version),false);
		$v = serialize(array($gz, $archive, $rub, $tables, 1, 0));
		ecrire_meta($meta, $v, 'non');
		include_spip('inc/headers');
		  // rub=$rub sert AUSSI a distinguer cette redirection
		  // d'avec l'appel initial sinon FireFox croit malin
		  // d'optimiser la redirection
		redirige_url_ecrire('export_all',"rub=$rub");
	} elseif ($quoi=='end') export_all_fin($file, $meta, $rub);
}

// http://doc.spip.org/@export_all_fin
function export_all_fin($file, $meta, $rub)
{
	global $spip_lang_left,$spip_lang_right;

	$metatable = $meta . '_tables';
	$tables_sauvegardees = isset($GLOBALS['meta'][$metatable])?unserialize($GLOBALS['meta'][$metatable]):array();
	effacer_meta($meta);
	effacer_meta($metatable);

	$size = @(!file_exists($file) ? 0 : filesize($file));

	if (!$size) {
		$corps = _T('avis_erreur_sauvegarde', array('type'=>'.', 'id_objet'=>'. .'));
	
	} else {
		ecrire_fichier($file, export_enpied(),false,false);
		$subdir = dirname($file);
		$dir = dirname($subdir);
		$nom = basename($file);
		$dest = $dir . '/' . $nom;
		if (file_exists($dest)) {
			$n = 1;
			while (@file_exists($new = "$dir/$n-$nom")) $n++;
			@rename($dest, $new);
		}
		if (@rename($file, $dest)) {
			spip_unlink($subdir);
			spip_log("$file renomme en $dir/$nom");
		}
	// ne pas effrayer inutilement: il peut y avoir moins de fichiers
	// qu'annonce' si certains etaient vides

		$n = _T('taille_octets', array('taille' => number_format($size, 0, ' ', ' ')));
		
		// cette chaine est a refaire car il y a double ambiguite:
		// - si plusieurs SPIP dans une base SQL (cf table_prefix)
		// - si on exporte seulement une rubrique
#			  _T('info_sauvegarde_reussi_02',		

		if ($rub) {
			$titre = sql_getfetsel('titre', 'spip_rubriques', "id_rubrique=$rub");
			$titre = _T('info_sauvegarde_rubrique_reussi',
				    array('archive' => ':<br /><b>'.joli_repertoire("$dir/$nom")."</b> ($n)", 'titre' => "<b>$titre</b>"));
		}
		else
			$titre = _T('info_sauvegarde_reussi_02',
			      array('archive' => ':<br /><b>'.joli_repertoire("$dir/$nom")."</b> ($n)"));

		$corps = "<p style='text-align: $spip_lang_left'>".
			  $titre .
			  " <a href='" . generer_url_ecrire() . "'>".
			_T('info_sauvegarde_reussi_03')
			. "</a> "
			._T('info_sauvegarde_reussi_04')
			. "</p>\n";
			
			$corps .= "<p style='text-align: $spip_lang_right'>".
			  " <a href='" . generer_url_ecrire() . "'>" .
			  _T("retour") .
			  "</a></p>";
						
			// afficher la liste des tables qu'on a sauvegarde
			sort($tables_sauvegardees);
			$n = floor(count($tables_sauvegardees)/2);
			$corps .= "<div style='width:49%;float:left;'><ul><li>" . join('</li><li>', array_slice($tables_sauvegardees,0,$n)) . "</li></ul></div>"
			. "<div style='width:49%;float:left;'><ul><li>" . join('</li><li>', array_slice($tables_sauvegardees,$n)) . "</li></ul></div>"
			. "<br class='nettoyeur' />";
	}
	include_spip('inc/minipres');
	echo minipres(_T('info_sauvegarde'), $corps);
	exit;
}

// http://doc.spip.org/@export_entete
function export_entete($version_archive)
{
	return
"<" . "?xml version=\"1.0\" encoding=\"".
$GLOBALS['meta']['charset']."\"?".">\n" .
"<SPIP 
	version=\"" . $GLOBALS['spip_version_affichee'] . "\" 
	version_base=\"" . $GLOBALS['spip_version_base'] . "\" 
	version_archive=\"" . $version_archive . "\"
	adresse_site=\"" .  $GLOBALS['meta']["adresse_site"] . "\"
	dir_img=\"" . _DIR_IMG . "\"
	dir_logos=\"" . _DIR_LOGOS . "\"
>\n";
}

// production de l'entete du fichier d'archive
// http://doc.spip.org/@export_enpied
function export_enpied () { return  "</SPIP>\n";}

?>