<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modTresorerieMensuelle extends DolibarrModules
{
    public $hooks = array();
    public $rights = array();
    public $menu = array();
    public $const = array();
    public $tabs = array();
    public $boxes = array();
    public $dictionaries = array();
    public $cronjobs = array();

    public function __construct($db)
    {
        global $conf, $langs;

        $this->db = $db;
        $this->numero = 500002;
        $this->rights_class = 'tresoreriemensuelle';
        $this->family = "financial";
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Tableau de bord qui affiche la tresorerie mensuel du mois en cours";
        $this->editor_name = 'Youssef Fellah';
        $this->version = '2.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-chart-line'; // Icône principale du module

        // --- DÉFINITION DE LA NOUVELLE STRUCTURE DU MENU ---
        $r = 0;

        // 1. Entrée du menu principal (en haut)
        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Tableau de Bord Trésorerie',
            'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'mainmenu' => 'tresoreriemensuelle', // Nom propre sans chiffres
            'leftmenu' => '',
            'url' => '/custom/tresoreriemensuelle/tresoreriemensuelleindex.php',
            'langs' => 'tresoreriemensuelle@tresoreriemensuelle',
            'position' => 50, // Pour le positionner dans le menu financier
            'enabled' => 'isModEnabled("tresoreriemensuelle")',
            'perms' => '1',
            'user' => 2
        );

        // 2. Lien "Tableau de Bord" dans le menu de gauche
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=tresoreriemensuelle',
            'type' => 'left',
            'titre' => 'Tableau de Bord',
            'prefix' => img_picto('', 'fa-tachometer-alt', 'class="paddingright pictofixedwidth valignmiddle"'),
            'url' => '/custom/tresoreriemensuelle/tresoreriemensuelleindex.php',
            'position' => 10,
            'enabled' => 'isModEnabled("tresoreriemensuelle")',
            'perms' => '1',
            'user' => 2
        );

        // 3. Lien "Liste des Dépenses Fixes" dans le menu de gauche
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=tresoreriemensuelle',
            'type' => 'left',
            'titre' => 'Liste des Dépenses Fixes',
            'prefix' => img_picto('', 'fa-list', 'class="paddingright pictofixedwidth valignmiddle"'),
            'url' => '/custom/tresoreriemensuelle/depensefixe_list.php',
            'position' => 20,
            'enabled' => 'isModEnabled("tresoreriemensuelle")',
            'perms' => '1',
            'user' => 2
        );

        // 4. Lien "Nouvelle Dépense Fixe" dans le menu de gauche
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=tresoreriemensuelle',
            'type' => 'left',
            'titre' => 'Nouvelle Dépense Fixe',
            'prefix' => img_picto('', 'fa-plus', 'class="paddingright pictofixedwidth valignmiddle"'),
            'url' => '/custom/tresoreriemensuelle/depensefixe_card.php?action=create',
            'position' => 30,
            'enabled' => 'isModEnabled("tresoreriemensuelle")',
            'perms' => '1',
            'user' => 2
        );
    }

    public function init($options = '') { $sql = array(); return $this->_init($sql, $options); }
    public function remove($options = '') { $sql = array(); return $this->_remove($sql, $options); }
}