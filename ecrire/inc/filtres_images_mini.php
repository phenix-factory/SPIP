<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2015                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) return;
include_spip('inc/filtres_images_lib_mini'); // par precaution

// http://code.spip.net/@couleur_html_to_hex
function couleur_html_to_hex($couleur){
	$couleurs_html=array(
		'aqua'=>'00FFFF','black'=>'000000','blue'=>'0000FF','fuchsia'=>'FF00FF','gray'=>'808080','green'=>'008000','lime'=>'00FF00','maroon'=>'800000',
		'navy'=>'000080','olive'=>'808000','purple'=>'800080','red'=>'FF0000','silver'=>'C0C0C0','teal'=>'008080','white'=>'FFFFFF','yellow'=>'FFFF00');
	if (isset($couleurs_html[$lc=strtolower($couleur)]))
		return $couleurs_html[$lc];
	return $couleur;
}

// http://code.spip.net/@couleur_foncer
function couleur_foncer ($couleur, $coeff=0.5) {
	$couleurs = _couleur_hex_to_dec($couleur);

	$red = $couleurs["red"] - round(($couleurs["red"])*$coeff);
	$green = $couleurs["green"] - round(($couleurs["green"])*$coeff);
	$blue = $couleurs["blue"] - round(($couleurs["blue"])*$coeff);

	$couleur = _couleur_dec_to_hex($red, $green, $blue);
	
	return $couleur;
}

// http://code.spip.net/@couleur_eclaircir
function couleur_eclaircir ($couleur, $coeff=0.5) {
	$couleurs = _couleur_hex_to_dec($couleur);

	$red = $couleurs["red"] + round((255 - $couleurs["red"])*$coeff);
	$green = $couleurs["green"] + round((255 - $couleurs["green"])*$coeff);
	$blue = $couleurs["blue"] + round((255 - $couleurs["blue"])*$coeff);

	$couleur = _couleur_dec_to_hex($red, $green, $blue);
	
	return $couleur;

}

// selectionner les images qui vont subir une transformation sur un critere de taille
// ls images exclues sont marquees d'une class filtre_inactif qui bloque les filtres suivants
// dans la fonction image_filtrer
// http://code.spip.net/@image_select
function image_select($img,$width_min=0, $height_min=0, $width_max=10000, $height_max=1000){
	if (!$img) return $img;
	list ($h,$l) = taille_image($img);
	$select = true;
	if ($l<$width_min OR $l>$width_max OR $h<$height_min OR $h>$height_max)
		$select = false;

	$class = extraire_attribut($img,'class');
	$p = strpos($class,'filtre_inactif');
	if (($select==false) AND ($p===FALSE)){
		$class .= " filtre_inactif";
		$img = inserer_attribut($img,'class',$class);
	}
	if (($select==true) AND ($p!==FALSE)){
		// no_image_filtrer : historique, a virer
		$class = preg_replace(",\s*(filtre_inactif|no_image_filtrer),","",$class);
		$img = inserer_attribut($img,'class',$class);
	}
	return $img;
}



/**
 * Réduit les images à une taille maximale (chevauchant un rectangle)
 *
 * L'image possède un côté réduit dans les dimensions indiquées et
 * l'autre côté (hauteur ou largeur) de l'image peut être plus grand
 * que les dimensions du rectangle.
 * 
 * Alors que image_reduire produit la plus petite image tenant dans un
 * rectangle, image_passe_partout produit la plus grande image qui
 * remplit ce rectangle.
 *
 * @example
 *     ```
 *     [(#FICHIER
 *       |image_passe_partout{70,70}
 *       |image_recadre{70,70,center})]
 *     ```
 * @link http://www.spip.net/4562
 * @see image_reduire()
 *
 * @param string $img
 *     Chemin de l'image ou texte
 * @param int $taille_x
 *     - Largeur maximale en pixels désirée
 *     - -1 prend la taille de réduction des vignettes par défaut
 *     - 0 la taille s'adapte à la largeur
 * @param int $taille_y
 *     - Hauteur maximale en pixels désirée
 *     - -1 pour prendre pareil que la largeur
 *     - 0 la taille s'adapte à la hauteur
 * @param bool $force
 * @param bool $cherche_image
 * @param string $process
 * @return string
 *     Code HTML de l'image ou du texte.
**/
function image_passe_partout($img,$taille_x = -1, $taille_y = -1,$force = false,$cherche_image=false,$process='AUTO'){
	if (!$img) return '';
	list ($hauteur,$largeur) = taille_image($img);
	if ($taille_x == -1)
		$taille_x = isset($GLOBALS['meta']['taille_preview'])?$GLOBALS['meta']['taille_preview']:150;
	if ($taille_y == -1)
		$taille_y = $taille_x;

	if ($taille_x == 0 AND $taille_y > 0)
		$taille_x = 1; # {0,300} -> c'est 300 qui compte
	elseif ($taille_x > 0 AND $taille_y == 0)
		$taille_y = 1; # {300,0} -> c'est 300 qui compte
	elseif ($taille_x == 0 AND $taille_y == 0)
		return '';
	
	list($destWidth,$destHeight,$ratio) = ratio_passe_partout($largeur,$hauteur,$taille_x,$taille_y);
	$fonction = array('image_passe_partout', func_get_args());
	return process_image_reduire($fonction,$img,$destWidth,$destHeight,$force,$cherche_image,$process);
}

/**
 * Réduit les images à une taille maximale (inscrite dans un rectangle)
 * 
 * L'image possède un côté dans les dimensions indiquées et
 * l'autre côté (hauteur ou largeur) de l'image peut être plus petit
 * que les dimensions du rectangle.
 *
 * Peut être utilisé pour réduire toutes les images d'un texte.
 *
 * @example
 *     ```
 *     [(#LOGO_ARTICLE|image_reduire{130})]
 *     [(#TEXTE|image_reduire{600,0})]
 *     ```
 * @see image_reduire_par()
 * @see image_passe_partout()
 *
 * @param string $img
 *     Chemin de l'image ou texte
 * @param int $taille
 *     - Largeur maximale en pixels désirée
 *     - -1 prend la taille de réduction des vignettes par défaut
 *     - 0 la taille s'adapte à la largeur
 * @param int $taille_y
 *     - Hauteur maximale en pixels désirée
 *     - -1 pour prendre pareil que la largeur
 *     - 0 la taille s'adapte à la hauteur
 * @param bool $force
 * @param bool $cherche_image
 * @param string $process
 * @return string
 *     Code HTML de l'image ou du texte.
**/
function image_reduire($img, $taille = -1, $taille_y = -1, $force=false, $cherche_image=false, $process='AUTO') {
	// Determiner la taille x,y maxi
	// prendre le reglage de previsu par defaut
	if ($taille == -1)
		$taille = (isset($GLOBALS['meta']['taille_preview']) AND intval($GLOBALS['meta']['taille_preview']))?intval($GLOBALS['meta']['taille_preview']):150;
	if ($taille_y == -1)
		$taille_y = $taille;

	if ($taille == 0 AND $taille_y > 0)
		$taille = 10000; # {0,300} -> c'est 300 qui compte
	elseif ($taille > 0 AND $taille_y == 0)
		$taille_y = 10000; # {300,0} -> c'est 300 qui compte
	elseif ($taille == 0 AND $taille_y == 0)
		return '';

	$fonction = array('image_reduire', func_get_args());
	return process_image_reduire($fonction,$img,$taille,$taille_y,$force,$cherche_image,$process);
}


/**
 * Réduit les images d'un certain facteur
 * 
 * @see image_reduire()
 *
 * @param string $img
 *     Chemin de l'image ou texte
 * @param int $val
 *     Facteur de réduction
 * @param bool $force
 * @return string
 *     Code HTML de l'image ou du texte.
**/
function image_reduire_par ($img, $val=1, $force=false) {
	list ($hauteur,$largeur) = taille_image($img);

	$l = round($largeur/$val);
	$h = round($hauteur/$val);
	
	if ($l > $h) $h = 0;
	else $l = 0;
	
	$img = image_reduire($img, $l, $h, $force);

	return $img;
}

?>
