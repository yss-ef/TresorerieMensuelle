<?php
// --- 1. CHARGEMENT DE L'ENVIRONNEMENT DOLIBARR ---
$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

// --- 2. CHARGEMENT DES CLASSES ET TRADUCTIONS NÉCESSAIRES ---
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
$langs->loadLangs(array("bills", "companies"));

// --- 3. PRÉPARATION DE L'AFFICHAGE ---
llxHeader("", "État Global de la Trésorerie");
print load_fiche_titre("État Global de la Trésorerie", '', 'fas fa-calculator');

// --- 4. CALCULS GLOBAUX ---
$total_entrees_attendues = 0;
$total_sorties_prevues = 0;

// Requête pour le total des factures fournisseurs non payées (toutes dates confondues)
$sql_fourn_total = "SELECT SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture_fourn as f WHERE f.paye = 0 AND f.fk_statut = 1";
$resql_fourn_total = $db->query($sql_fourn_total);
if ($resql_fourn_total) {
    $obj = $db->fetch_object($resql_fourn_total);
    if ($obj->total !== null) {
        $total_sorties_prevues = $obj->total;
    }
}

// Requête pour le total des factures clients non payées (toutes dates confondues)
$sql_cli_total = "SELECT SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture as f WHERE f.paye = 0 AND f.fk_statut = 1";
$resql_cli_total = $db->query($sql_cli_total);
if ($resql_cli_total) {
    $obj = $db->fetch_object($resql_cli_total);
    if ($obj->total !== null) {
        $total_entrees_attendues = $obj->total;
    }
}

// Pour le solde initial, nous le mettrons en place plus tard via les paramètres du module.
// Pour l'instant, on le fixe à 0.
$solde_initial = 0;
$solde_final_previsionnel = $solde_initial + $total_entrees_attendues - $total_sorties_prevues;

// --- 5. AFFICHAGE DU BLOC DE RÉSUMÉ ---
echo '<br>';
echo '<table class="noborder" width="50%" style="margin: auto;">';
echo '<tr class="liste_titre"><td colspan="2">Résumé de la Trésorerie</td></tr>';
echo '<tr class="oddeven"><td>Solde Initial de Caisse (à configurer)</td><td class="right">'.price($solde_initial).'</td></tr>';
echo '<tr class="oddeven"><td>(+) Total des entrées attendues (Factures clients non payées)</td><td class="right">'.price($total_entrees_attendues).'</td></tr>';
echo '<tr class="oddeven"><td>(-) Total des sorties prévues (Factures fournisseurs non payées)</td><td class="right">'.price($total_sorties_prevues).'</td></tr>';
echo '<tr class="liste_total"><td><b>Solde Final Prévisionnel</b></td><td class="right"><b>'.price($solde_final_previsionnel).'</b></td></tr>';
echo '</table>';
echo '<br>';


// --- 6. AFFICHAGE DES TABLEAUX DE DÉTAIL (qui ne sont plus nécessaires, mais on peut les garder) ---

// On commente les tableaux de détail pour l'instant, car le résumé est plus important.
// Si vous voulez les réactiver, il suffira d'enlever les "/*" et "*/"

/*
// Factures fournisseurs
print_barre_liste('Détail des factures fournisseurs à payer', 0, $_SERVER["PHP_SELF"], '', '', '', '', 0);
// ... (ici, on mettrait le code pour afficher la liste détaillée) ...

// Factures clients
print '<br><br>';
print_barre_liste('Détail des factures clients en attente de paiement', 0, $_SERVER["PHP_SELF"], '', '', '', '', 0);
// ... (ici, on mettrait le code pour afficher la liste détaillée) ...
*/


// --- FIN DE LA PAGE ---
llxFooter();
$db->close();
?>