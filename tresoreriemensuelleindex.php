<?php
// --- 1. CHARGEMENT DE L'ENVIRONNEMENT DOLIBARR ---
$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; } // Sécurité supplémentaire
if (!$res) { die("Include of main fails"); }

// --- 2. CHARGEMENT DES CLASSES, LIBRAIRIES ET TRADUCTIONS ---
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
$langs->loadLangs(array("bills", "companies", "tresoreriemensuelle@tresoreriemensuelle"));

// ==============================================================================
// --- SECTION DES CALCULS PRÉALABLES ---
// ==============================================================================

// --- Définition des dates pour le mois en cours ---
$now = dol_now();
$date_start_month = dol_mktime(0, 0, 0, date('m'), 1, date('Y'));
$date_end_month = dol_mktime(23, 59, 59, date('m'), date('t'), date('Y'));

// --- Solde bancaire actuel ---
$solde_bancaire_actuel = 0;
$sql_bank = "SELECT SUM(b.solde) as total_solde FROM ".MAIN_DB_PREFIX."bank_account as b WHERE b.status = 1";
$resql_bank = $db->query($sql_bank);
if ($resql_bank) {
    $obj_bank = $db->fetch_object($resql_bank);
    if ($obj_bank) $solde_bancaire_actuel = $obj_bank->total_solde;
}

// --- Total des factures clients impayées dues ce mois-ci ---
$total_impayes_clients_ce_mois = 0;
$sql_impayes_clients = "SELECT SUM(f.total_ttc - f.paye) as total_impayes FROM ".MAIN_DB_PREFIX."facture as f WHERE f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_month)."' AND f.date_lim_reglement <= '".$db->idate($date_end_month)."'";
$resql_impayes_clients = $db->query($sql_impayes_clients);
if ($resql_impayes_clients) {
    $obj_impayes_clients = $db->fetch_object($resql_impayes_clients);
    if ($obj_impayes_clients) $total_impayes_clients_ce_mois = $obj_impayes_clients->total_impayes;
}

// --- Total des factures fournisseurs impayées dues ce mois-ci (CORRIGÉ) ---
$total_impayes_fournisseurs_ce_mois = 0;
$sql_impayes_fourn = "SELECT SUM(f.total_ttc - f.paye) as total_impayes FROM ".MAIN_DB_PREFIX."facture_fourn as f WHERE f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_month)."' AND f.date_lim_reglement <= '".$db->idate($date_end_month)."'";
$resql_impayes_fourn = $db->query($sql_impayes_fourn);
if ($resql_impayes_fourn) {
    $obj_impayes_fourn = $db->fetch_object($resql_impayes_fourn);
    if ($obj_impayes_fourn) $total_impayes_fournisseurs_ce_mois = $obj_impayes_fourn->total_impayes;
}

// --- Total des dépenses fixes mensuelles ---
$total_depenses_fixes = 0;
$sql_depenses_fixes = "SELECT SUM(t.amount) as total_depenses FROM " . MAIN_DB_PREFIX . "tresoreriemensuelle_depensefixe as t";
$resql_depenses_fixes = $db->query($sql_depenses_fixes);
if ($resql_depenses_fixes) {
    $obj_df = $db->fetch_object($resql_depenses_fixes);
    if ($obj_df) $total_depenses_fixes = $obj_df->total_depenses;
}

// --- Préparation des données pour le graphique Donut ---
$chartDonutLabels = array();
$chartDonutData = array();
if ($total_impayes_fournisseurs_ce_mois > 0) {
    $chartDonutLabels[] = 'Factures Fournisseurs';
    $chartDonutData[] = $total_impayes_fournisseurs_ce_mois;
}
$sql_depenses_fixes_details = "SELECT t.label, t.amount FROM " . MAIN_DB_PREFIX . "tresoreriemensuelle_depensefixe as t";
$resql_depenses_fixes_details = $db->query($sql_depenses_fixes_details);
if ($resql_depenses_fixes_details && $db->num_rows($resql_depenses_fixes_details) > 0) {
    while ($obj = $db->fetch_object($resql_depenses_fixes_details)) {
        $chartDonutLabels[] = $obj->label;
        $chartDonutData[] = $obj->amount;
    }
}

// --- Calcul final de la trésorerie attendue ---
$tresorerie_attendue_fin_de_mois = $solde_bancaire_actuel + $total_impayes_clients_ce_mois - $total_impayes_fournisseurs_ce_mois - $total_depenses_fixes;


// --- 3. PRÉPARATION DE L'AFFICHAGE ---
llxHeader("", "Tableau de Bord Trésorerie");
print load_fiche_titre("Tableau de Bord de Trésorerie pour " . dol_print_date($now, "%B %Y"), '', 'fas fa-chart-line');


// --- TABLEAU SYNTHÉTIQUE DES FLUX DU MOIS ---
$flux_net_du_mois = $total_impayes_clients_ce_mois - ($total_impayes_fournisseurs_ce_mois + $total_depenses_fixes);
$color = ($flux_net_du_mois >= 0) ? 'green' : 'red';
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="center">Total à Recevoir ce mois-ci</th>';
print '<th class="center">Total à Payer ce mois-ci</th>';
print '<th class="center">Impact sur la trésorerie ce mois-ci</th>';
print '</tr>';
print '<tr style="font-size: 1.5em; text-align: center;">';
print '<td style="color: green;">' . price($total_impayes_clients_ce_mois) . '</td>';
print '<td style="color: red;">' . price($total_impayes_fournisseurs_ce_mois + $total_depenses_fixes) . '</td>';
print '<td style="font-weight: bold; color: ' . $color . ';">' . price($flux_net_du_mois) . '</td>';
print '</tr>';
print '</table>';
print '<br>';


// --- SECTION GRAPHIQUES ---
// --- CALCUL DES DONNÉES PAR SEMAINE POUR LE GRAPHIQUE LIGNE ---
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

    $sql_fourn = "SELECT SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture_fourn as f WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_week)."' AND f.date_lim_reglement <= '".$db->idate($date_end_week)."'";
    $resql_fourn = $db->query($sql_fourn);
    $total_fourn_week = 0;
    if ($resql_fourn) {
        $obj = $db->fetch_object($resql_fourn);
        if ($obj && $obj->total !== null) $total_fourn_week = $obj->total;
    }
    $data_fournisseurs_graph[] = $total_fourn_week;

    $sql_cli = "SELECT SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture as f WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_week)."' AND f.date_lim_reglement <= '".$db->idate($date_end_week)."'";
    $resql_cli = $db->query($sql_cli);
    $total_cli_week = 0;
    if ($resql_cli) {
        $obj = $db->fetch_object($resql_cli);
        if ($obj && $obj->total !== null) $total_cli_week = $obj->total;
    }
    $data_clients_graph[] = $total_cli_week;
}
?>

<div class="fichecenter">
    <div class="fichehalfleft">
        <div style="width: 100%; max-width: 250px; margin: auto; padding-bottom: 30px;">
            <h3 style="text-align: center;">Répartition des Dépenses du Mois</h3>
            <canvas id="donutChartExpenses"></canvas>
        </div>
    </div>
    <div class="fichehalfright">
        <div style="width: 100%; max-width: 500px; margin: auto; padding-bottom: 30px;">
            <h3 style="text-align: center;">Flux hebdomadaire (Impayés)</h3>
            <canvas id="tresorerieChart"></canvas>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Graphique Donut (Nouveau)
    if (<?php echo json_encode(count($chartDonutData)); ?> > 0) {
        var ctxDonut = document.getElementById('donutChartExpenses').getContext('2d');
        var donutChart = new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chartDonutLabels); ?>,
                datasets: [{
                    label: 'Répartition des Dépenses',
                    data: <?php echo json_encode($chartDonutData); ?>,
                    backgroundColor: ['rgba(255, 99, 132, 0.8)', 'rgba(54, 162, 235, 0.8)', 'rgba(255, 206, 86, 0.8)', 'rgba(75, 192, 192, 0.8)', 'rgba(153, 102, 255, 0.8)', 'rgba(255, 159, 64, 0.8)'],
                    borderColor: 'rgba(255, 255, 255, 0.5)',
                    borderWidth: 2
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } } }
        });
    }

    // Graphique Ligne (Votre original)
    var ctxLine = document.getElementById('tresorerieChart').getContext('2d');
    var tresorerieChart = new Chart(ctxLine, {
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
// --- SECTION TABLEAUX RÉCAPITULATIFS ---
// --- Factures fournisseurs à payer (Impayées) ---
print '<br>';
print_barre_liste('Factures fournisseurs à payer ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Fournisseur</td><td>Fournisseur</td><td class="right">Montant TTC</td><td class="center">Date limite de règlement</td></tr>';
$sql_fournisseurs_list = "SELECT f.rowid, f.ref_supplier, f.total_ttc, f.date_lim_reglement, s.nom as societe_nom FROM ".MAIN_DB_PREFIX."facture_fourn as f LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_month)."' AND f.date_lim_reglement <= '".$db->idate($date_end_month)."'";
$resql_fournisseurs_list = $db->query($sql_fournisseurs_list);
if ($resql_fournisseurs_list && $db->num_rows($resql_fournisseurs_list) > 0) {
    $facture_fourn_static = new FactureFournisseur($db);
    while ($obj = $db->fetch_object($resql_fournisseurs_list)) {
        echo '<tr class="oddeven"><td>'.$facture_fourn_static->getNomUrl(1, '', $obj->rowid).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->total_ttc).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_lim_reglement), 'day').'</td></tr>';
    }
    echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total à payer :</b></td><td class="right"><b>'.price($total_impayes_fournisseurs_ce_mois).'</b></td><td></td></tr>';
} else {
    echo '<tr><td colspan="4" class="opacitymedium">Aucune facture fournisseur à payer ce mois-ci.</td></tr>';
}
echo '</table>';

// --- TABLEAU DES DÉPENSES FIXES ---
print '<br><br>';
print_barre_liste('Dépenses fixes prévues ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Libellé</td><td class="center">Jour du prélèvement</td><td class="right">Montant</td></tr>';
$sql_depenses_fixes_list = "SELECT t.rowid, t.label, t.amount, t.day_of_month FROM " . MAIN_DB_PREFIX . "tresoreriemensuelle_depensefixe as t ORDER BY t.day_of_month ASC";
$resql_depenses_fixes_list = $db->query($sql_depenses_fixes_list);
if ($resql_depenses_fixes_list && $db->num_rows($resql_depenses_fixes_list) > 0) {
    while ($obj = $db->fetch_object($resql_depenses_fixes_list)) {
        echo '<tr class="oddeven"><td>' . $obj->label . '</td><td class="center">' . $obj->day_of_month . '</td><td class="right">' . price($obj->amount) . '</td></tr>';
    }
    echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total des dépenses fixes :</b></td><td class="right"><b>'.price($total_depenses_fixes).'</b></td></tr>';
} else {
    echo '<tr><td colspan="3" class="opacitymedium">Aucune dépense fixe définie. Vous pouvez en ajouter <a href="'.dol_buildpath('/custom/tresoreriemensuelle/depensefixe_list.php', 1).'">ici</a>.</td></tr>';
}
echo '</table>';


// --- Factures clients en attente de paiement (Impayées) ---
print '<br><br>';
print_barre_liste('Factures clients en attente de paiement ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Facture</td><td>Client</td><td class="right">Montant TTC</td><td class="center">Date limite de règlement</td></tr>';
$sql_clients_list = "SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement, s.nom as societe_nom FROM ".MAIN_DB_PREFIX."facture as f LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid WHERE f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement >= '".$db->idate($date_start_month)."' AND f.date_lim_reglement <= '".$db->idate($date_end_month)."'";
$resql_clients_list = $db->query($sql_clients_list);
if ($resql_clients_list && $db->num_rows($resql_clients_list) > 0) {
    $facture_client_static = new Facture($db);
    while ($obj = $db->fetch_object($resql_clients_list)) {
        echo '<tr class="oddeven"><td>'.$facture_client_static->getNomUrl(1, '', $obj->rowid).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->total_ttc).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_lim_reglement), 'day').'</td></tr>';
    }
    echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total attendu :</b></td><td class="right"><b>'.price($total_impayes_clients_ce_mois).'</b></td><td></td></tr>';
} else {
    echo '<tr><td colspan="4" class="opacitymedium">Aucune facture client en attente de paiement ce mois-ci.</td></tr>';
}
echo '</table>';

// --- FACTURES FOURNISSEURS PAYÉES CE MOIS-CI ---
print '<br><br>';
print_barre_liste('Factures fournisseurs payées ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Fournisseur</td><td>Fournisseur</td><td class="right">Montant Payé</td><td class="center">Date de Paiement</td></tr>';
$sql_fourn_payees = "SELECT f.rowid, f.ref_supplier, s.nom as societe_nom, p.datep as date_paiement, pf.amount as montant_paye FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf JOIN ".MAIN_DB_PREFIX."paiementfourn as p ON pf.fk_paiementfourn = p.rowid JOIN ".MAIN_DB_PREFIX."facture_fourn as f ON pf.fk_facturefourn = f.rowid LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid WHERE p.datep >= '".$db->idate($date_start_month)."' AND p.datep <= '".$db->idate($date_end_month)."'";
$resql_fourn_payees = $db->query($sql_fourn_payees);
if ($resql_fourn_payees && $db->num_rows($resql_fourn_payees) > 0) {
    $total_paye_fournisseurs = 0;
    $facture_fourn_static = new FactureFournisseur($db);
    while ($obj = $db->fetch_object($resql_fourn_payees)) {
        $total_paye_fournisseurs += $obj->montant_paye;
        echo '<tr class="oddeven"><td>'.$facture_fourn_static->getNomUrl(1, '', $obj->rowid).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->montant_paye).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_paiement), 'day').'</td></tr>';
    }
    echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total payé :</b></td><td class="right"><b>'.price($total_paye_fournisseurs).'</b></td><td></td></tr>';
} else {
    echo '<tr><td colspan="4" class="opacitymedium">Aucune facture fournisseur payée ce mois-ci.</td></tr>';
}
echo '</table>';

// --- FACTURES CLIENTS RÉGLÉES CE MOIS-CI ---
print '<br><br>';
print_barre_liste('Factures clients réglées ce mois-ci', 0, $_SERVER["PHP_SELF"]);
echo '<table class="noborder centpercent">';
echo '<tr class="liste_titre"><td>Réf. Facture</td><td>Client</td><td class="right">Montant Reçu</td><td class="center">Date de Paiement</td></tr>';
$sql_cli_payees = "SELECT f.rowid, f.ref, s.nom as societe_nom, p.datep as date_paiement, pf.amount as montant_paye FROM ".MAIN_DB_PREFIX."paiement_facture as pf JOIN ".MAIN_DB_PREFIX."paiement as p ON pf.fk_paiement = p.rowid JOIN ".MAIN_DB_PREFIX."facture as f ON pf.fk_facture = f.rowid LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid WHERE p.datep >= '".$db->idate($date_start_month)."' AND p.datep <= '".$db->idate($date_end_month)."'";
$resql_cli_payees = $db->query($sql_cli_payees);
if ($resql_cli_payees && $db->num_rows($resql_cli_payees) > 0) {
    $total_recu_clients = 0;
    $facture_client_static = new Facture($db);
    while ($obj = $db->fetch_object($resql_cli_payees)) {
        $total_recu_clients += $obj->montant_paye;
        echo '<tr class="oddeven"><td>'.$facture_client_static->getNomUrl(1, '', $obj->rowid).'</td><td>'.$obj->societe_nom.'</td><td class="right">'.price($obj->montant_paye).'</td><td class="center">'.dol_print_date($db->jdate($obj->date_paiement), 'day').'</td></tr>';
    }
    echo '<tr class="liste_total"><td colspan="2" class="right"><b>Total reçu :</b></td><td class="right"><b>'.price($total_recu_clients).'</b></td><td></td></tr>';
} else {
    echo '<tr><td colspan="4" class="opacitymedium">Aucune facture client réglée ce mois-ci.</td></tr>';
}
echo '</table>';

// --- FIN DE LA PAGE ---
llxFooter();
$db->close();

?>