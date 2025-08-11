<?php
// --- 1. CHARGEMENT DE L'ENVIRONNEMENT DOLIBARR ---
$res = 0;
// On utilise un bloc de chargement robuste
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

// --- 2. CHARGEMENT DES CLASSES ET TRADUCTIONS NÉCESSAIRES ---
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
$langs->loadLangs(array("bills", "companies"));

// --- 3. PRÉPARATION DE L'AFFICHAGE ---
llxHeader("", "Tableau de Bord Trésorerie");
print load_fiche_titre("Tableau de Bord de Trésorerie pour le mois en cours", '', 'fas fa-chart-line');

// --- CALCUL DES DONNÉES PAR SEMAINE POUR LE GRAPHIQUE ---
$weeks_labels = array();
$data_fournisseurs_graph = array();
$data_clients_graph = array();

$days_in_month = date('t');
$num_weeks = ceil($days_in_month / 7);

for ($week = 1; $week <= $num_weeks; $week++) {
    $weeks_labels[] = "Semaine " . $week;
    
    $day_start = (($week - 1) * 7) + 1;
    $day_end = min($week * 7, $days_in_month);
    
    $date_start_week = dol_mktime(0, 0, 0, date('m'), $day_start, date('Y'));
    $date_end_week = dol_mktime(23, 59, 59, date('m'), $day_end, date('Y'));

    // Calcul du total fournisseurs pour la semaine
    $sql_fourn = "SELECT SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture_fourn as f WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_week)."' AND f.date_lim_reglement <= '".$db->idate($date_end_week)."'";
    $resql_fourn = $db->query($sql_fourn);
    $total_fourn_week = 0;
    if ($resql_fourn) {
        $obj = $db->fetch_object($resql_fourn);
        if ($obj->total !== null) $total_fourn_week = $obj->total;
    }
    $data_fournisseurs_graph[] = $total_fourn_week;

    // Calcul du total clients pour la semaine
    $sql_cli = "SELECT SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture as f WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_week)."' AND f.date_lim_reglement <= '".$db->idate($date_end_week)."'";
    $resql_cli = $db->query($sql_cli);
    $total_cli_week = 0;
    if ($resql_cli) {
        $obj = $db->fetch_object($resql_cli);
        if ($obj->total !== null) $total_cli_week = $obj->total;
    }
    $data_clients_graph[] = $total_cli_week;
}
?>

<div style="width: 100%; max-width: 800px; margin: auto; padding-bottom: 30px;">
    <canvas id="tresorerieChart"></canvas>
</div>

<script>
$(document).ready(function() {
    var ctx = document.getElementById('tresorerieChart').getContext('2d');
    var tresorerieChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($weeks_labels); ?>,
            datasets: [
                {
                    label: 'Factures Fournisseurs (À Payer)',
                    data: <?php echo json_encode($data_fournisseurs_graph); ?>,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    fill: true,
                    tension: 0.1
                },
                {
                    label: 'Factures Clients (À Recevoir)',
                    data: <?php echo json_encode($data_clients_graph); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }
            ]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });
});
</script>

<?php
// --- 4. TABLEAUX RÉCAPITULATIFS POUR LE MOIS COMPLET ---

// On définit les dates pour le mois entier pour les tableaux
$date_start_month = dol_mktime(0, 0, 0, date('m'), 1, date('Y'));
$date_end_month = dol_mktime(23, 59, 59, date('m'), date('t'), date('Y'));

// --- Factures fournisseurs à payer (Impayées) ---
print '<br>';
print_barre_liste('Factures fournisseurs à payer ce mois-ci (Date limite de règlement)', 0, $_SERVER["PHP_SELF"], '', '', '', '', 0);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Fournisseur</td><td>Fournisseur</td><td class="right">Montant TTC</td><td class="center">Date limite de règlement</td></tr>';

$sql_fournisseurs_list = "SELECT f.rowid, f.ref_supplier, f.total_ttc, f.date_lim_reglement, s.nom as societe_nom FROM ".MAIN_DB_PREFIX."facture_fourn as f LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_month)."' AND f.date_lim_reglement <= '".$db->idate($date_end_month)."'";
$resql_fournisseurs_list = $db->query($sql_fournisseurs_list);
if ($resql_fournisseurs_list) {
    $num = $db->num_rows($resql_fournisseurs_list);
    $total_fournisseurs_month = 0;
    if ($num > 0) {
        $facture_fourn_static = new FactureFournisseur($db);
        while ($obj = $db->fetch_object($resql_fournisseurs_list)) {
            $facture_fourn_static->id = $obj->rowid;
            $facture_fourn_static->ref = $obj->ref_supplier;
            $total_fournisseurs_month += $obj->total_ttc;
            echo '<tr class="oddeven"><td>'.$facture_fourn_static->getNomUrl(1).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->total_ttc).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_lim_reglement), 'day').'</td></tr>';
        }
        echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total à payer :</b></td><td class="right"><b>'.price($total_fournisseurs_month).'</b></td><td></td></tr>';
    } else {
        echo '<tr><td colspan="4" class="opacitymedium">Aucune facture fournisseur à payer ce mois-ci.</td></tr>';
    }
}
echo '</table>';

// --- Factures clients en attente de paiement (Impayées) ---
print '<br><br>';
print_barre_liste('Factures clients en attente de paiement ce mois-ci (Date limite de règlement)', 0, $_SERVER["PHP_SELF"], '', '', '', '', 0);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Facture</td><td>Client</td><td class="right">Montant TTC</td><td class="center">Date limite de règlement</td></tr>';

$sql_clients_list = "SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement, s.nom as societe_nom FROM ".MAIN_DB_PREFIX."facture as f LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_month)."' AND f.date_lim_reglement <= '".$db->idate($date_end_month)."'";
$resql_clients_list = $db->query($sql_clients_list);
if ($resql_clients_list) {
    $num = $db->num_rows($resql_clients_list);
    $total_clients_month = 0;
    if ($num > 0) {
        $facture_client_static = new Facture($db);
        while ($obj = $db->fetch_object($resql_clients_list)) {
            $facture_client_static->id = $obj->rowid;
            $facture_client_static->ref = $obj->ref;
            $total_clients_month += $obj->total_ttc;
            echo '<tr class="oddeven"><td>'.$facture_client_static->getNomUrl(1).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->total_ttc).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_lim_reglement), 'day').'</td></tr>';
        }
        echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total attendu :</b></td><td class="right"><b>'.price($total_clients_month).'</b></td><td></td></tr>';
    } else {
        echo '<tr><td colspan="4" class="opacitymedium">Aucune facture client en attente de paiement ce mois-ci.</td></tr>';
    }
}
echo '</table>';

// --- FACTURES FOURNISSEURS PAYÉES CE MOIS-CI ---
print '<br><br>';
print_barre_liste('Factures fournisseurs payées ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Fournisseur</td><td>Fournisseur</td><td class="right">Montant Payé</td><td class="center">Date de Paiement</td></tr>';

$sql_fourn_payees = "SELECT f.rowid, f.ref_supplier, s.nom as societe_nom, p.datep as date_paiement, pf.amount as montant_paye";
$sql_fourn_payees .= " FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf";
$sql_fourn_payees .= " JOIN ".MAIN_DB_PREFIX."paiementfourn as p ON pf.fk_paiementfourn = p.rowid";
$sql_fourn_payees .= " JOIN ".MAIN_DB_PREFIX."facture_fourn as f ON pf.fk_facturefourn = f.rowid";
$sql_fourn_payees .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
$sql_fourn_payees .= " WHERE p.datep >= '".$db->idate($date_start_month)."' AND p.datep <= '".$db->idate($date_end_month)."'";
$resql_fourn_payees = $db->query($sql_fourn_payees);
if ($resql_fourn_payees) {
    $num = $db->num_rows($resql_fourn_payees);
    $total_paye_fournisseurs = 0;
    if ($num > 0) {
        $facture_fourn_static = new FactureFournisseur($db);
        while ($obj = $db->fetch_object($resql_fourn_payees)) {
            $facture_fourn_static->id = $obj->rowid;
            $facture_fourn_static->ref = $obj->ref_supplier;
            $total_paye_fournisseurs += $obj->montant_paye;
            echo '<tr class="oddeven"><td>'.$facture_fourn_static->getNomUrl(1).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->montant_paye).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_paiement), 'day').'</td></tr>';
        }
        echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total payé :</b></td><td class="right"><b>'.price($total_paye_fournisseurs).'</b></td><td></td></tr>';
    } else {
        echo '<tr><td colspan="4" class="opacitymedium">Aucune facture fournisseur payée ce mois-ci.</td></tr>';
    }
}
echo '</table>';

// --- FACTURES CLIENTS RÉGLÉES CE MOIS-CI ---
print '<br><br>';
print_barre_liste('Factures clients réglées ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Facture</td><td>Client</td><td class="right">Montant Reçu</td><td class="center">Date de Paiement</td></tr>';

$sql_cli_payees = "SELECT f.rowid, f.ref, s.nom as societe_nom, p.datep as date_paiement, pf.amount as montant_paye";
$sql_cli_payees .= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf";
$sql_cli_payees .= " JOIN ".MAIN_DB_PREFIX."paiement as p ON pf.fk_paiement = p.rowid";
$sql_cli_payees .= " JOIN ".MAIN_DB_PREFIX."facture as f ON pf.fk_facture = f.rowid";
$sql_cli_payees .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
$sql_cli_payees .= " WHERE p.datep >= '".$db->idate($date_start_month)."' AND p.datep <= '".$db->idate($date_end_month)."'";
$resql_cli_payees = $db->query($sql_cli_payees);
if ($resql_cli_payees) {
    $num = $db->num_rows($resql_cli_payees);
    $total_recu_clients = 0;
    if ($num > 0) {
        $facture_client_static = new Facture($db);
        while ($obj = $db->fetch_object($resql_cli_payees)) {
            $facture_client_static->id = $obj->rowid;
            $facture_client_static->ref = $obj->ref;
            $total_recu_clients += $obj->montant_paye;
            echo '<tr class="oddeven"><td>'.$facture_client_static->getNomUrl(1).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->montant_paye).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_paiement), 'day').'</td></tr>';
        }
        echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total reçu :</b></td><td class="right"><b>'.price($total_recu_clients).'</b></td><td></td></tr>';
    } else {
        echo '<tr><td colspan="4" class="opacitymedium">Aucune facture client réglée ce mois-ci.</td></tr>';
    }
}
echo '</table>';

// --- FIN DE LA PAGE ---
llxFooter();
$db->close();
?>